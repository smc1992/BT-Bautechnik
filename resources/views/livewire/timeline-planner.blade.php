<?php

use Livewire\Volt\Component;
use App\Models\Project;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?int $selectedYear = null;
    public string $filterWorkType = 'all';

    // Modal state
    public bool $showModal = false;
    public ?Project $selectedProject = null;
    public ?int $editStartWeek = 1;
    public ?int $editStartYear = 2026;
    public ?int $editEndWeek = 4;
    public ?int $editEndYear = 2026;
    public string $editStatus = 'active';
    public ?int $clickedKW = null;

    public function mount()
    {
        $this->selectedYear = (int) date('Y');
    }

    public function getAvailableYearsProperty()
    {
        $currentYear = (int) date('Y');

        $minDbYear = Project::min('start_year') ?: $currentYear;
        $maxDbYear = Project::max('end_year') ?: $currentYear;

        $start = min($currentYear - 2, $minDbYear);
        $end = max($currentYear + 10, $maxDbYear);

        return range($start, $end);
    }

    public function getProjectsProperty()
    {
        $y = $this->selectedYear;

        return Project::with(['contact', 'budget', 'actualCosts', 'invoices'])
            ->where(function ($q) use ($y) {
                // Include projects that overlap with $selectedYear
                $q->where(function ($sub) use ($y) {
                    $sub->where('start_year', '<=', $y)->where('end_year', '>=', $y);
                });
            })
            ->when($this->filterWorkType !== 'all', fn($q) => $q->where('work_type', 'LIKE', '%' . $this->filterWorkType . '%'))
            ->orderBy('start_year', 'asc')
            ->orderBy('start_week', 'asc')
            ->get();
    }

    public function openProjectModal(string $id, ?int $kw = null)
    {
        $this->selectedProject = Project::with(['contact', 'budget', 'actualCosts', 'invoices', 'photos'])->find($id);
        if ($this->selectedProject) {
            $this->editStartWeek = (int) ($this->selectedProject->start_week ?? 1);
            $this->editStartYear = (int) ($this->selectedProject->start_year ?? date('Y'));
            $this->editEndWeek = (int) ($this->selectedProject->end_week ?? 4);
            $this->editEndYear = (int) ($this->selectedProject->end_year ?? date('Y'));
            $this->editStatus = $this->selectedProject->status ?? 'active';
            $this->clickedKW = $kw;
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedProject = null;
    }

    public function saveProjectDetails()
    {
        if ($this->selectedProject) {
            $newStartWeek = max(1, min(52, (int) ($this->editStartWeek ?? 1)));
            $newEndWeek = max(1, min(52, (int) ($this->editEndWeek ?? 4)));
            $newStartYear = max(2020, min(2050, (int) ($this->editStartYear ?? date('Y'))));
            $newEndYear = max($newStartYear, min(2050, (int) ($this->editEndYear ?? date('Y'))));

            $this->selectedProject->update([
                'start_week' => $newStartWeek,
                'start_year' => $newStartYear,
                'end_week' => $newEndWeek,
                'end_year' => $newEndYear,
                'status' => $this->editStatus,
            ]);

            $this->closeModal();
            $this->dispatch('notify', '✨ Bauzeiten für ' . $this->selectedProject->name . ' erfolgreich aktualisiert!');
        }
    }

    public function updateProjectWeeks($id, $startWeek, $endWeek)
    {
        $project = Project::find($id);
        if ($project) {
            $newStart = max(1, min(52, $startWeek));
            $newEnd = max(1, min(52, $endWeek));
            $project->update([
                'start_week' => $newStart,
                'end_week' => $newEnd,
            ]);
            $this->dispatch('notify', '📅 Bauzeiten angepasst!');
        }
    }
}; ?>

<div class="space-y-6 font-sans" x-data="{ scrollToKW(kw) {
    const el = $refs.timelineScrollContainer;
    if (!el) return;
    const kwWidth = 48;
    const targetScroll = Math.max(0, (kw - 1) * kwWidth - 100);
    el.scrollTo({ left: targetScroll, behavior: 'smooth' });
}}">
    <!-- Top Command Center Banner (Corporate Slate & Navy Blue Gradient) -->
    <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white rounded-2xl p-6 shadow-xl border border-blue-500/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-1 relative z-10">
            <h2 class="text-xl font-black text-white tracking-tight flex items-center gap-2.5">
                <span>📅 Bauzeiten- & Ressourcen-Planer</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-black bg-blue-600 text-white border border-blue-400/40">Dynamische Jahre</span>
            </h2>
            <p class="text-xs text-slate-300 font-medium">Zeitleiste für kurzfristige & mehrjährige Bauprojekte mit unbegrenzter Zukunftssicherheit</p>
        </div>

        <!-- Year Selector Dropdown & Stepper -->
        <div class="flex flex-wrap items-center gap-3 relative z-10">
            <div class="flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-xl border border-slate-800 text-xs font-bold">
                <button wire:click="$set('selectedYear', {{ $selectedYear - 1 }})" 
                        class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer" title="Vorheriges Jahr">
                    ◀
                </button>

                <select wire:model.live="selectedYear" class="bg-slate-800 text-white border border-slate-700 rounded-lg px-3 py-1 font-black focus:outline-none cursor-pointer">
                    @foreach ($this->availableYears as $yr)
                        <option value="{{ $yr }}">Jahr {{ $yr }}</option>
                    @endforeach
                </select>

                <button wire:click="$set('selectedYear', {{ $selectedYear + 1 }})" 
                        class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer" title="Nächstes Jahr">
                    ▶
                </button>
            </div>

            <!-- Quarter Schnell-Navigation -->
            <div class="bg-slate-900/90 p-1.5 rounded-xl border border-slate-800 flex items-center gap-1 text-xs font-bold">
                <button @click="scrollToKW(1)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer">Q1</button>
                <button @click="scrollToKW(14)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer">Q2</button>
                <button @click="scrollToKW(27)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer">Q3</button>
                <button @click="scrollToKW(40)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg transition cursor-pointer">Q4</button>
                <button @click="scrollToKW({{ intval(date('W')) }})" class="px-3 py-1 bg-blue-600 text-white rounded-lg transition shadow-md cursor-pointer">Heute</button>
            </div>
        </div>
    </div>

    <!-- Timeline Gantt Container -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <!-- Header Strip -->
        <div class="p-5 border-b border-slate-200/80 bg-slate-50/60 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                    Bauzeitenplan & Termine für das Jahr {{ $selectedYear }} (KW 1 – KW 52)
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Jahresübergreifende Projekte sind mit Pfeil-Indikatoren (◀ {{ $selectedYear - 1 }} | {{ $selectedYear + 1 }} ▶) gekennzeichnet</p>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-4 text-xs font-bold">
                <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-gradient-to-r from-blue-700 to-cyan-600"></span> Aktive Baustelle</span>
                <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-gradient-to-r from-slate-700 to-indigo-600"></span> Jahresübergreifend</span>
                <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-emerald-500"></span> Abgeschlossen</span>
            </div>
        </div>

        <!-- Main Gantt Layout: Left Fixed Column + Right Scrollable Grid -->
        <div class="flex items-start">
            <!-- Fixed Left Project Info Panel -->
            <div class="w-80 min-w-[320px] shrink-0 border-r border-slate-200 bg-white z-20 shadow-md">
                <!-- Left Header Cell -->
                <div class="h-20 bg-slate-950 p-4 border-b border-slate-800 flex flex-col justify-center">
                    <span class="font-black text-xs uppercase tracking-wider text-slate-200">Baustelle & Gewerk</span>
                    <span class="text-[10px] text-slate-400 font-medium">Jahr {{ $selectedYear }} • Projektdetails & Anpassung</span>
                </div>

                <!-- Left Project Rows -->
                <div class="divide-y divide-slate-100">
                    @forelse ($this->projects as $p)
                        @php
                            $startYr = $p->start_year ?? (int)date('Y');
                            $endYr = $p->end_year ?? (int)date('Y');
                            $isMultiYear = ($startYr !== $endYr);
                        @endphp
                        <div class="h-28 p-4 space-y-2 bg-white hover:bg-slate-50/80 transition flex flex-col justify-between cursor-pointer"
                             wire:click="openProjectModal('{{ $p->id }}')">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="font-extrabold text-slate-900 text-xs tracking-tight line-clamp-1 hover:text-blue-600 transition" title="{{ $p->name }}">
                                    {{ $p->name }}
                                </h4>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $isMultiYear ? 'bg-indigo-100 text-indigo-800 border border-indigo-300' : ($p->status === 'active' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200') }}">
                                    @if ($isMultiYear)
                                        KW {{ $p->start_week }}/{{ $startYr }} – {{ $p->end_week }}/{{ $endYr }}
                                    @else
                                        KW {{ $p->start_week }}–{{ $p->end_week }}
                                    @endif
                                </span>
                            </div>

                            <div class="text-[11px] text-slate-500 font-medium space-y-0.5">
                                <p class="truncate text-slate-700 font-semibold"><span class="text-slate-400">🏗️</span> {{ $p->work_type }}</p>
                                @if ($p->city_street)
                                    <p class="truncate text-[10px] text-slate-400"><span class="text-slate-400">📍</span> {{ $p->city_street }}</p>
                                @endif
                            </div>

                            <!-- Interactive KW Adjusters -->
                            <div class="flex items-center justify-between text-[10px] font-bold text-slate-600 pt-0.5 border-t border-slate-100" @click.stop>
                                <div class="flex items-center gap-1">
                                    <button wire:click="updateProjectWeeks('{{ $p->id }}', {{ $p->start_week - 1 }}, {{ $p->end_week }})" 
                                            class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-700 rounded transition cursor-pointer">KW-</button>
                                    <button wire:click="updateProjectWeeks('{{ $p->id }}', {{ $p->start_week + 1 }}, {{ $p->end_week }})" 
                                            class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-700 rounded transition cursor-pointer">KW+</button>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button wire:click="openProjectModal('{{ $p->id }}')" 
                                            class="px-2 py-0.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded font-bold transition border border-blue-200">⚙️ Edit</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">Keine Baustellen für {{ $selectedYear }} vorhanden</div>
                    @endforelse
                </div>
            </div>

            <!-- Right Scrollable Gantt Timeline Area (2496px wide: 52 weeks * 48px) -->
            <div x-ref="timelineScrollContainer" class="flex-1 overflow-x-auto bg-slate-50/20">
                <div class="w-[2496px]">
                    <!-- Header Months Row (52 * 48px = 2496px) -->
                    <div class="h-10 bg-slate-950 text-white border-b border-slate-800 flex text-[10px] font-black uppercase tracking-wider text-center">
                        @php
                            $months = [
                                'Januar' => 4, 'Februar' => 4, 'März' => 5, 'April' => 4,
                                'Mai' => 4, 'Juni' => 5, 'Juli' => 4, 'August' => 4,
                                'September' => 5, 'Oktober' => 4, 'November' => 4, 'Dezember' => 4
                            ];
                        @endphp
                        @foreach ($months as $mName => $mWeeks)
                            <div class="border-r border-slate-800/80 flex items-center justify-center bg-slate-900/60" style="width: {{ $mWeeks * 48 }}px">
                                {{ $mName }} {{ $selectedYear }}
                            </div>
                        @endforeach
                    </div>

                    <!-- Header Weeks Row (52 * 48px = 2496px) -->
                    <div class="h-10 bg-slate-900 text-slate-300 border-b border-slate-800 flex text-[10px] font-extrabold text-center">
                        @php $currentKW = ((int)date('Y') === $selectedYear) ? intval(date('W')) : -1; @endphp
                        @for ($w = 1; $w <= 52; $w++)
                            <div class="w-[48px] min-w-[48px] border-r border-slate-800/80 flex items-center justify-center font-mono {{ $currentKW === $w ? 'bg-blue-600 text-white font-black shadow-inner' : '' }}">
                                KW{{ $w }}
                            </div>
                        @endfor
                    </div>

                    <!-- Project Gantt Bars Rows -->
                    <div class="divide-y divide-slate-100 bg-white">
                        @forelse ($this->projects as $p)
                            <div class="h-28 relative flex items-center hover:bg-slate-50/50 transition">
                                <!-- Background 52 Grid Guideline Columns -->
                                <div class="absolute inset-0 flex pointer-events-none">
                                    @for ($w = 1; $w <= 52; $w++)
                                        <div class="w-[48px] min-w-[48px] border-r border-slate-100/80 {{ $currentKW === $w ? 'bg-blue-50/30' : '' }}"></div>
                                    @endfor
                                </div>

                                <!-- Multi-Year Calculation for $selectedYear -->
                                @php
                                    $startYr = $p->start_year ?? (int)date('Y');
                                    $endYr = $p->end_year ?? (int)date('Y');
                                    $isMultiYear = ($startYr !== $endYr);

                                    // Determine start & end week FOR THIS SELECTED YEAR
                                    $effectiveStart = ($startYr < $selectedYear) ? 1 : $p->start_week;
                                    $effectiveEnd = ($endYr > $selectedYear) ? 52 : $p->end_week;

                                    $start = max(1, min(52, $effectiveStart));
                                    $end = max($start, min(52, $effectiveEnd));
                                    $span = max(1, $end - $start + 1);

                                    $leftPx = ($start - 1) * 48;
                                    $widthPx = $span * 48;

                                    $startsInEarlierYear = ($startYr < $selectedYear);
                                    $extendsToNextYear = ($endYr > $selectedYear);
                                @endphp

                                <div wire:click="openProjectModal('{{ $p->id }}')" 
                                     class="absolute z-10 h-12 rounded-xl shadow-md border border-white/20 flex items-center justify-between px-3 text-xs font-extrabold text-white transition-all duration-300 group/pill cursor-pointer hover:scale-[1.01] hover:shadow-xl {{ $isMultiYear ? 'bg-gradient-to-r from-slate-800 via-indigo-600 to-blue-600' : ($p->status === 'active' ? 'bg-gradient-to-r from-blue-700 via-blue-600 to-cyan-600' : 'bg-gradient-to-r from-emerald-600 to-teal-600') }}"
                                     style="left: {{ $leftPx }}px; width: {{ $widthPx }}px;"
                                     title="{{ $p->name }} (KW {{ $p->start_week }}/{{ $startYr }} bis KW {{ $p->end_week }}/{{ $endYr }})">

                                    <!-- Left Multi-Year Indicator -->
                                    @if ($startsInEarlierYear)
                                        <span class="px-2 py-0.5 rounded bg-black/40 text-[10px] font-black font-mono tracking-wider flex items-center gap-1 border border-white/20">
                                            ◀ {{ $startYr }}
                                        </span>
                                    @endif

                                    <!-- Main Title & Range Badge -->
                                    <div class="flex items-center gap-1.5 truncate px-0.5">
                                        @if ($span >= 3)
                                            <span class="truncate font-bold text-white drop-shadow-xs">{{ $p->name }}</span>
                                        @endif
                                        <span class="shrink-0 text-[10px] bg-black/30 px-2 py-0.5 rounded-full font-mono font-black backdrop-blur-xs border border-white/20 whitespace-nowrap">
                                            @if ($isMultiYear)
                                                KW {{ $p->start_week }}/{{ $startYr }}–{{ $p->end_week }}/{{ $endYr }}
                                            @elseif ($span === 1)
                                                KW {{ $start }}
                                            @elseif ($span === 2)
                                                KW {{ $start }}–{{ $end }}
                                            @else
                                                {{ $span }} KW (KW {{ $start }}–{{ $end }})
                                            @endif
                                        </span>
                                    </div>

                                    <!-- Right Multi-Year Indicator -->
                                    @if ($extendsToNextYear)
                                        <span class="px-2 py-0.5 rounded bg-black/40 text-[10px] font-black font-mono tracking-wider flex items-center gap-1 border border-white/20">
                                            {{ $endYr }} ▶
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="h-28 flex items-center justify-center text-slate-400 text-xs">
                                Keine Baustellen für {{ $selectedYear }} vorhanden.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- INTERACTIVE MULTI-YEAR PROJECT DETAIL & EDIT MODAL -->
    @if ($showModal && $selectedProject)
        @php
            $proj = $selectedProject;
            $budgetTotal = $proj->budget?->total_with_buffer ?: 0;
            $costsTotal = $proj->actualCosts->sum('cost_amount');
            $invoicedTotal = $proj->invoices->sum('total_net');
            $isMultiYr = ($editStartYear !== $editEndYear);
        @endphp
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header (Corporate Slate & Navy Blue Gradient) -->
                <div class="p-6 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white flex justify-between items-start relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl"></div>

                    <div class="space-y-1.5 relative z-10">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider {{ $proj->status === 'active' ? 'bg-blue-600 text-white' : 'bg-emerald-600 text-white' }}">
                                {{ $proj->status === 'active' ? 'Aktiv in Bearbeitung' : 'Abgeschlossen' }}
                            </span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-slate-200 border border-white/20 font-mono">
                                KW {{ $editStartWeek }}/{{ $editStartYear }} – KW {{ $editEndWeek }}/{{ $editEndYear }}
                            </span>
                            @if ($isMultiYr)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-indigo-600 text-white">
                                    Jahresübergreifend
                                </span>
                            @endif
                        </div>

                        <h2 class="text-2xl font-black text-white tracking-tight pt-1">{{ $proj->name }}</h2>
                        <p class="text-xs text-slate-300 flex items-center gap-2">
                            <span>🏗️ {{ $proj->work_type }}</span>
                            <span>•</span>
                            <span>📍 {{ $proj->city_street ?: 'Keine Adresse hinterlegt' }}</span>
                        </p>
                    </div>

                    <button wire:click="closeModal" class="p-2 text-slate-400 hover:text-white rounded-full bg-white/10 hover:bg-white/20 transition cursor-pointer relative z-10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto space-y-6">
                    <!-- Financial Quick Overview -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase text-slate-500">Soll-Budget</span>
                            <p class="text-lg font-black text-slate-900 mt-1">{{ number_format($budgetTotal, 2, ',', '.') }} €</p>
                        </div>
                        <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase text-slate-500">Ist-Kosten</span>
                            <p class="text-lg font-black text-rose-600 mt-1">{{ number_format($costsTotal, 2, ',', '.') }} €</p>
                        </div>
                        <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 shadow-2xs">
                            <span class="text-[10px] font-extrabold uppercase text-slate-500">Fakturiert</span>
                            <p class="text-lg font-black text-blue-700 mt-1">{{ number_format($invoicedTotal, 2, ',', '.') }} €</p>
                        </div>
                    </div>

                    <!-- Multi-Year Bauzeiten & Status Bearbeiten Formular -->
                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                            <span>📅 Bauzeitraum & Jahre festlegen</span>
                        </h4>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Start-KW</label>
                                <select wire:model="editStartWeek" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:outline-none">
                                    @for ($w = 1; $w <= 52; $w++)
                                        <option value="{{ $w }}">KW {{ $w }}</option>
                                    @endfor
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Start-Jahr</label>
                                <select wire:model="editStartYear" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:outline-none">
                                    @foreach ($this->availableYears as $yr)
                                        <option value="{{ $yr }}">{{ $yr }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Ende-KW</label>
                                <select wire:model="editEndWeek" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:outline-none">
                                    @for ($w = 1; $w <= 52; $w++)
                                        <option value="{{ $w }}">KW {{ $w }}</option>
                                    @endfor
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Ende-Jahr</label>
                                <select wire:model="editEndYear" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:outline-none">
                                    @foreach ($this->availableYears as $yr)
                                        <option value="{{ $yr }}">{{ $yr }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Projektstatus</label>
                            <select wire:model="editStatus" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:border-blue-600 focus:outline-none">
                                <option value="active">Aktiv in Bearbeitung</option>
                                <option value="completed">Abgeschlossen</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-between items-center">
                    <button wire:click="closeModal" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-bold transition">
                        Schließen
                    </button>
                    <button wire:click="saveProjectDetails" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-md transition flex items-center gap-2 cursor-pointer">
                        <span>💾 Mehrjahres-Bauzeiten speichern</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
