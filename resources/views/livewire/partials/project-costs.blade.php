<div data-project-section class="space-y-4">
                            <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                                <x-ui-icon name="chart" class="inline-block align-middle" /> Budget- & Kosten-Kalkulation
                            </h4>

                            <div class="bg-slate-50 p-4 rounded-2xl space-y-4 border border-slate-200/80">
                                <!-- Material Budget -->
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-blue-900 flex items-center gap-1.5 font-bold">
                                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Materialbudget
                                        </span>
                                        <span class="text-slate-900 font-bold">{{ number_format((float) ($proj->budget?->material_budget ?? 0), 2, ',', '.') }} €</span>
                                    </div>
                                    @php
                                        $matCosts = (float) $proj->actualCosts->where('type', 'material')->sum('cost_amount');
                                        $matBudget = (float) ($proj->budget?->material_budget ?? 0);
                                        $matPercent = $matBudget > 0 ? ($matCosts / $matBudget) * 100 : 0;
                                    @endphp
                                    <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                        <div class="bg-blue-500 h-full rounded-full transition-all" style="width: {{ min(max($matPercent, 0), 100) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                        <span>Verbucht: {{ number_format($matCosts, 2, ',', '.') }} €</span>
                                        <span>{{ $matBudget > 0 ? number_format($matPercent, 1, ',', '.') . '%' : 'Budget fehlt' }}</span>
                                    </div>
                                </div>

                                <!-- Wage Budget -->
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-slate-900 flex items-center gap-1.5 font-bold">
                                            <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span> Lohn- & Subunternehmer
                                        </span>
                                        <span class="text-slate-900 font-bold">{{ number_format((float) ($proj->budget?->wage_budget ?? 0), 2, ',', '.') }} €</span>
                                    </div>
                                    @php
                                        $wageCosts = (float) $proj->actualCosts->whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');
                                        $wageBudget = (float) ($proj->budget?->wage_budget ?? 0);
                                        $wagePercent = $wageBudget > 0 ? ($wageCosts / $wageBudget) * 100 : 0;
                                    @endphp
                                    <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden border border-slate-300/60 p-0.5">
                                        <div class="bg-slate-500 h-full rounded-full transition-all" style="width: {{ min(max($wagePercent, 0), 100) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-600 mt-1 font-semibold">
                                        <span>Verbucht: {{ number_format($wageCosts, 2, ',', '.') }} €</span>
                                        <span>{{ $wageBudget > 0 ? number_format($wagePercent, 1, ',', '.') . '%' : 'Budget fehlt' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cost Receipts -->
                            <div class="space-y-3 pt-2">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">Ist-Kosten Belege</h4>
                                    <button wire:click="openAddCost" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition cursor-pointer">+ Beleg erfassen</button>
                                </div>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    @forelse ($proj->actualCosts as $cost)
                                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 flex justify-between items-center text-xs">
                                            <div>
                                                <p class="font-bold text-slate-900">{{ $cost->description }}</p>
                                                <p class="text-[10px] text-slate-500 font-medium">{{ date('d.m.Y', strtotime($cost->date)) }} • {{ ucfirst($cost->type) }}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if ($cost->receipt_path)
                                                    <a href="{{ asset('storage/' . $cost->receipt_path) }}" target="_blank" class="px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg text-[10px] font-bold transition flex items-center gap-1 shadow-2xs">
                                                        <span><x-ui-icon name="document" class="inline-block align-middle" /> Beleg PDF</span>
                                                    </a>
                                                @endif
                                                <p class="font-bold text-rose-600">-{{ number_format($cost->cost_amount, 2, ',', '.') }} €</p>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500 italic">Keine Belege vorhanden.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
