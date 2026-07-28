<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Project;
use App\Models\Budget;
use App\Models\Offer;
use App\Models\Defect;
use App\Models\DailyLog;
use App\Models\ProjectPhoto;
use App\Models\Invoice;
use App\Models\SubcontractorInvoice;
use App\Models\ActualCost;

return new class extends Migration
{
    /**
     * Run the migrations to deduplicate project records.
     */
    public function up(): void
    {
        $duplicateNames = Project::select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $projects = Project::where('name', $name)->orderBy('created_at', 'asc')->get();
            if ($projects->count() <= 1) {
                continue;
            }

            $keeper = $projects->first();
            $duplicates = $projects->slice(1);

            foreach ($duplicates as $duplicate) {
                // Re-link relations to keeper project
                Budget::where('project_id', $duplicate->id)->get()->each(function ($budget) use ($keeper) {
                    if (Budget::where('project_id', $keeper->id)->exists()) {
                        $budget->delete();
                    } else {
                        $budget->update(['project_id' => $keeper->id]);
                    }
                });

                Offer::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                Defect::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                DailyLog::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                ProjectPhoto::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                Invoice::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                SubcontractorInvoice::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);
                ActualCost::where('project_id', $duplicate->id)->update(['project_id' => $keeper->id]);

                // Delete duplicate project
                $duplicate->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-reversible data deduplication
    }
};
