<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\ActualCost;
use App\Models\SubcontractorInvoice;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $timeRange = 'year'; // year, last_year, all

    public function getTimeRange(): array
    {
        return match ($this->timeRange) {
            'last_year' => [(date('Y') - 1) . '-01-01', (date('Y') - 1) . '-12-31'],
            'year' => [date('Y') . '-01-01', date('Y') . '-12-31'],
            default => ['2000-01-01', '2099-12-31'],
        };
    }

    public function getMetricsProperty()
    {
        [$start, $end] = $this->getTimeRange();

        $invoicesQuery = Invoice::whereBetween('invoice_date', [$start, $end]);
        $totalInvoicedNet = (clone $invoicesQuery)->sum('total_net');
        $totalInvoicedGross = (clone $invoicesQuery)->sum('total_gross');

        $paidInvoicesNet = (clone $invoicesQuery)->where('status', 'paid')->sum('total_net');
        $unpaidInvoicesNet = (clone $invoicesQuery)->whereIn('status', ['sent', 'draft', 'overdue'])->sum('total_net');

        $costsQuery = ActualCost::whereBetween('date', [$start, $end]);
        $totalCosts = (clone $costsQuery)->sum('cost_amount');
        $materialCosts = (clone $costsQuery)->where('type', 'material')->sum('cost_amount');
        $subcontractorCosts = (clone $costsQuery)->whereIn('type', ['subcontractor', 'internal_wage'])->sum('cost_amount');

        $netProfit = $totalInvoicedNet - $totalCosts;
        $totalInvoicedNetFloat = (float) $totalInvoicedNet;
        $profitMargin = $totalInvoicedNetFloat > 0 ? ($netProfit / $totalInvoicedNetFloat) * 100 : 0;

        return [
            'invoiced_net' => $totalInvoicedNet,
            'invoiced_gross' => $totalInvoicedGross,
            'paid_net' => $paidInvoicesNet,
            'unpaid_net' => $unpaidInvoicesNet,
            'total_costs' => $totalCosts,
            'material_costs' => $materialCosts,
            'subcontractor_costs' => $subcontractorCosts,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }

    public function getMonthlyBreakdownProperty()
    {
        [$start, $end] = $this->getTimeRange();
        $year = (int) substr($start, 0, 4);

        $months = [];
        $maxAmount = 1;

        for ($m = 1; $m <= 12; $m++) {
            $monthStr = sprintf('%04d-%02d', $year, $m);
            $monthLabel = match ($m) {
                1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr',
                5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
            };

            $invoiced = Invoice::where('invoice_date', 'LIKE', $monthStr . '%')->sum('total_net');
            $costs = ActualCost::where('date', 'LIKE', $monthStr . '%')->sum('cost_amount');

            if ($invoiced > $maxAmount) $maxAmount = $invoiced;
            if ($costs > $maxAmount) $maxAmount = $costs;

            $months[] = [
                'month' => $monthLabel,
                'invoiced' => $invoiced,
                'costs' => $costs,
                'profit' => $invoiced - $costs,
            ];
        }

        return [
            'months' => $months,
            'max_amount' => $maxAmount,
        ];
    }

    public function getProjectRankingsProperty()
    {
        $projects = Project::with(['budget', 'actualCosts', 'invoices'])->get();

        return $projects->map(function ($p) {
            $invoicedNet = $p->invoices->sum('total_net');
            $totalCosts = $p->actualCosts->sum('cost_amount');
            $budgetTotal = (float) ($p->budget?->total_with_buffer ?? 0);
            $netProfit = $invoicedNet > 0 ? ($invoicedNet - $totalCosts) : ($budgetTotal - $totalCosts);
            $margin = $budgetTotal > 0 ? ($netProfit / $budgetTotal) * 100 : 0;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'city' => $p->city_street,
                'work_type' => $p->work_type,
                'budget' => $budgetTotal,
                'invoiced' => $invoicedNet,
                'costs' => $totalCosts,
                'profit' => $netProfit,
                'margin' => $margin,
                'status' => $p->status,
            ];
        })->sortByDesc('profit')->values();
    }
}; ?>

<div class="space-y-6 sm:space-y-8 font-sans max-w-full overflow-x-hidden">
    <!-- Command Center Top Header Banner -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-4 sm:p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
            <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>📈 Finanz- & Umsatz-Analytics</span>
            </h2>
            <p class="text-xs text-slate-300 font-medium">Gewinn- & Verlustrechnung, Monatsverläufe & Baustellen-Rendite</p>
        </div>

        <!-- Time Range Selector (Mobile-First Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 bg-slate-900/90 p-1.5 rounded-xl border border-slate-800 w-full md:w-auto relative z-10">
            <button wire:click="$set('timeRange', 'year')" 
                    class="px-3.5 py-2 sm:py-1.5 text-xs font-bold rounded-lg transition cursor-pointer text-center {{ $timeRange === 'year' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white' }}">
                Dieses Jahr ({{ date('Y') }})
            </button>
            <button wire:click="$set('timeRange', 'last_year')" 
                    class="px-3.5 py-2 sm:py-1.5 text-xs font-bold rounded-lg transition cursor-pointer text-center {{ $timeRange === 'last_year' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white' }}">
                Letztes Jahr ({{ date('Y') - 1 }})
            </button>
            <button wire:click="$set('timeRange', 'all')" 
                    class="px-3.5 py-2 sm:py-1.5 text-xs font-bold rounded-lg transition cursor-pointer text-center {{ $timeRange === 'all' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white' }}">
                Gesamter Zeitraum
            </button>
        </div>
    </div>

    <!-- 4 Key Financial Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- Metric Card 1: Invoiced Net -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-lg transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-600 to-indigo-600"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Fakturierter Umsatz (Netto)</p>
                <span class="p-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200/60">
                    💶
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-3 tracking-tight">{{ number_format($this->metrics['invoiced_net'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 text-xs">
                <span class="text-slate-500">Brutto: {{ number_format($this->metrics['invoiced_gross'], 2, ',', '.') }} €</span>
                <span class="font-bold text-blue-600">Rechnungen</span>
            </div>
        </div>

        <!-- Metric Card 2: Total Costs -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-lg transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-amber-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gesamte Ist-Kosten</p>
                <span class="p-2.5 rounded-xl bg-rose-50 text-rose-600 border border-rose-200/60">
                    📉
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-rose-600 mt-3 tracking-tight">{{ number_format($this->metrics['total_costs'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 text-xs text-slate-500">
                <span>Material: {{ number_format($this->metrics['material_costs'], 0, ',', '.') }} €</span>
                <span>Sub: {{ number_format($this->metrics['subcontractor_costs'], 0, ',', '.') }} €</span>
            </div>
        </div>

        <!-- Metric Card 3: Net Profit -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-lg transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Echter Reingewinn</p>
                <span class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200/60">
                    💎
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-3 tracking-tight">{{ number_format($this->metrics['net_profit'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 text-xs">
                <span class="text-slate-500">Gewinnmarge:</span>
                <span class="font-black text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full border border-emerald-200">
                    {{ number_format($this->metrics['profit_margin'], 1) }} %
                </span>
            </div>
        </div>

        <!-- Metric Card 4: Unpaid / Cashflow -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-lg transition duration-200 relative overflow-hidden group">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-orange-500"></div>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Offene Forderungen</p>
                <span class="p-2.5 rounded-xl bg-amber-50 text-amber-600 border border-amber-200/60">
                    ⏳
                </span>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 mt-3 tracking-tight">{{ number_format($this->metrics['unpaid_net'], 2, ',', '.') }} €</p>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-100 text-xs">
                <span class="text-slate-500">Bezahlt: {{ number_format($this->metrics['paid_net'], 0, ',', '.') }} €</span>
                <span class="font-bold text-amber-700">Im Zahlungsziel</span>
            </div>
        </div>
    </div>

    <!-- Monthly Chart & Profit Breakdown Section -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-6 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-200/80 pb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    Monatliche Umsatz- & Kosten-Entwicklung
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Vergleich von Ausgangsrechnungen (Blau) zu Ist-Kosten (Rosé) pro Monat</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-bold">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-blue-600"></span> Umsätze (Netto)</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-md bg-rose-500"></span> Ist-Kosten</span>
            </div>
        </div>

        <!-- Visual Bar Chart (Scrollable on Mobile) -->
        @php
            $chartData = $this->monthlyBreakdown;
            $maxAmt = max($chartData['max_amount'], 100);
        @endphp
        <div class="overflow-x-auto pb-2 scrollbar-none max-w-full">
            <div class="grid grid-cols-12 gap-2 sm:gap-4 items-end h-64 pt-6 border-b border-slate-100 pb-2 min-w-[550px] sm:min-w-0">
                @foreach ($chartData['months'] as $m)
                    @php
                        $invHeight = min(($m['invoiced'] / $maxAmt) * 100, 100);
                        $costHeight = min(($m['costs'] / $maxAmt) * 100, 100);
                    @endphp
                    <div class="flex flex-col items-center gap-2 h-full justify-end group">
                        <div class="w-full flex items-end justify-center gap-1 h-full relative">
                            <!-- Invoiced Bar -->
                            <div class="w-1/2 bg-blue-600 hover:bg-blue-700 rounded-t-md transition-all duration-300 relative group/bar"
                                 style="height: {{ max($invHeight, 2) }}%">
                                @if ($m['invoiced'] > 0)
                                    <div class="opacity-0 group-hover/bar:opacity-100 absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] font-bold px-2 py-0.5 rounded shadow-md pointer-events-none whitespace-nowrap z-20">
                                        {{ number_format($m['invoiced'], 0, ',', '.') }} €
                                    </div>
                                @endif
                            </div>
                            <!-- Costs Bar -->
                            <div class="w-1/2 bg-rose-500 hover:bg-rose-600 rounded-t-md transition-all duration-300 relative group/bar"
                                 style="height: {{ max($costHeight, 2) }}%">
                                @if ($m['costs'] > 0)
                                    <div class="opacity-0 group-hover/bar:opacity-100 absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] font-bold px-2 py-0.5 rounded shadow-md pointer-events-none whitespace-nowrap z-20">
                                        {{ number_format($m['costs'], 0, ',', '.') }} €
                                    </div>
                                @endif
                            </div>
                        </div>
                        <span class="text-[11px] font-bold text-slate-600">{{ $m['month'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Project Profitability Ranking Section (Mobile Card View + Desktop Table) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-slate-200/80 bg-slate-50/60 flex justify-between items-center">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>🏆 Baustellen-Rentabilität & Deckungsbeitrag</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Ertrags-Ranking aller Baustellen nach absolutem Reingewinn</p>
            </div>
        </div>

        <!-- Mobile Card View (< sm) -->
        <div class="block sm:hidden divide-y divide-slate-100">
            @forelse ($this->projectRankings as $p)
                <div class="p-4 space-y-3 bg-white">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <h4 class="font-black text-slate-900 text-sm tracking-tight">{{ $p['name'] }}</h4>
                            <p class="text-slate-500 text-xs">📍 {{ $p['city'] }} · <span class="text-slate-600 font-medium">{{ $p['work_type'] }}</span></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-black shrink-0 {{ $p['margin'] >= 15 ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : ($p['margin'] >= 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-rose-100 text-rose-800 border border-rose-200') }}">
                            {{ number_format($p['margin'], 1, ',', '.') }} %
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-200/60">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Soll-Budget</span>
                            <span class="font-semibold text-slate-800">{{ number_format($p['budget'], 2, ',', '.') }} €</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-blue-500 font-bold uppercase block">Fakturiert (Netto)</span>
                            <span class="font-bold text-blue-700">{{ number_format($p['invoiced'], 2, ',', '.') }} €</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-rose-400 font-bold uppercase block">Ist-Kosten</span>
                            <span class="font-bold text-rose-600">{{ number_format($p['costs'], 2, ',', '.') }} €</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Reingewinn</span>
                            <span class="font-black {{ $p['profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ number_format($p['profit'], 2, ',', '.') }} €
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-xs text-slate-400">Keine Baustellen-Daten vorhanden.</div>
            @endforelse
        </div>

        <!-- Desktop Table View (>= sm) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 text-slate-200 text-[11px] font-bold uppercase tracking-wider">
                        <th class="p-4">Baustelle / Gewerk</th>
                        <th class="p-4">Ort</th>
                        <th class="p-4 text-right">Soll-Budget</th>
                        <th class="p-4 text-right">Fakturiert</th>
                        <th class="p-4 text-right">Ist-Kosten</th>
                        <th class="p-4 text-right">Reingewinn (€)</th>
                        <th class="p-4 text-right">Marge (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse ($this->projectRankings as $p)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4">
                                <span class="font-extrabold text-slate-900 text-sm block">{{ $p['name'] }}</span>
                                <span class="text-slate-500 text-[11px]">{{ $p['work_type'] }}</span>
                            </td>
                            <td class="p-4 text-slate-600">{{ $p['city'] }}</td>
                            <td class="p-4 text-right font-semibold text-slate-800">{{ number_format($p['budget'], 2, ',', '.') }} €</td>
                            <td class="p-4 text-right font-bold text-blue-700">{{ number_format($p['invoiced'], 2, ',', '.') }} €</td>
                            <td class="p-4 text-right font-bold text-rose-600">{{ number_format($p['costs'], 2, ',', '.') }} €</td>
                            <td class="p-4 text-right font-black {{ $p['profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ number_format($p['profit'], 2, ',', '.') }} €
                            </td>
                            <td class="p-4 text-right">
                                <span class="px-2.5 py-1 rounded-full text-xs font-black {{ $p['margin'] >= 15 ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : ($p['margin'] >= 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-rose-100 text-rose-800 border border-rose-200') }}">
                                    {{ number_format($p['margin'], 1, ',', '.') }} %
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">Keine Baustellen-Daten für die Berechnung vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
