<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Offer;
use App\Models\OfferSection;
use App\Models\OfferItem;
use App\Models\Budget;
use App\Services\OpenAiParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Str;

class ParseOfferPdfJob implements ShouldQueue
{
    use Queueable;

    protected Project $project;
    protected string $textContents;

    /**
     * Create a new job instance.
     */
    public function __construct(Project $project, string $textContents)
    {
        $this->project = $project;
        $this->textContents = $textContents;
    }

    /**
     * Execute the job.
     */
    public function handle(OpenAiParserService $parser): void
    {
        Log::info("Starting ParseOfferPdfJob for project: " . $this->project->id);

        try {
            // 1. Call OpenAI to parse unstructured text
            $parsedData = $parser->parseOfferDocument($this->textContents);

            // 2. Perform DB insert inside transaction
            DB::transaction(function () use ($parsedData) {
                // Generate a unique offer number (e.g., matching input or random if empty)
                $offerNumber = 'AN-' . date('Ymd') . '-' . strtoupper(Str::random(4));
                
                $offer = Offer::create([
                    'project_id' => $this->project->id,
                    'offer_number' => $offerNumber,
                    'date' => date('Y-m-d'),
                    'status' => 'draft',
                    'total_net' => 0.00,
                    'total_gross' => 0.00,
                ]);

                $totalNet = 0.00;
                $predictedMaterialBudget = 0.00;
                $predictedWageBudget = 0.00;

                foreach ($parsedData['sections'] as $secIndex => $secData) {
                    $section = OfferSection::create([
                        'offer_id' => $offer->id,
                        'title' => $secData['title'],
                        'sort_order' => $secIndex + 1,
                    ]);

                    foreach ($secData['items'] as $itemData) {
                        $itemTotal = floatval($itemData['quantity']) * floatval($itemData['unit_price']);
                        $totalNet += $itemTotal;

                        OfferItem::create([
                            'section_id' => $section->id,
                            'pos_number' => $itemData['pos_number'],
                            'description' => $itemData['description'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $itemTotal,
                        ]);

                        // Basic heuristic budgeting split logic
                        $descLower = mb_strtolower($itemData['description']);
                        $isWage = Str::contains($descLower, ['montage', 'lohn', 'arbeit', 'betonieren', 'abbruch', 'stunden', 'lfm', 'entsorgung']) 
                                  && !Str::contains($descLower, ['tür', 'fenster', 'material']);
                        
                        if ($isWage) {
                            $predictedWageBudget += $itemTotal;
                        } else {
                            // General rule: split mixed items (like doors + material) or pure material
                            if (Str::contains($descLower, ['tür', 'fenster', 'material'])) {
                                // 80% material, 20% wage/assembly
                                $predictedMaterialBudget += $itemTotal * 0.8;
                                $predictedWageBudget += $itemTotal * 0.2;
                            } else {
                                $predictedMaterialBudget += $itemTotal;
                            }
                        }
                    }
                }

                // 3. Update the Offer totals (assuming 19% standard VAT for gross calculation)
                $vatAmount = $totalNet * 0.19;
                $offer->update([
                    'total_net' => $totalNet,
                    'total_gross' => $totalNet + $vatAmount,
                ]);

                // 4. Intelligently update the Project Budget
                $budget = Budget::where('project_id', $this->project->id)->first();
                if ($budget) {
                    $subtotal = $predictedMaterialBudget + $predictedWageBudget;
                    $bufferAmount = $subtotal * ($budget->buffer_rate / 100);
                    $budget->update([
                        'material_budget' => $predictedMaterialBudget,
                        'wage_budget' => $predictedWageBudget,
                        'buffer_amount' => $bufferAmount,
                        'total_with_buffer' => $subtotal + $bufferAmount,
                    ]);
                }

                Log::info("Successfully created structured offer: " . $offer->id . " with total: " . $totalNet);
            });

        } catch (Exception $e) {
            Log::error("Failed ParseOfferPdfJob execution: " . $e->getMessage());
            throw $e;
        }
    }
}
