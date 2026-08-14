@props(['variant' => 'full', 'size' => 'default'])

@php
    $imgHeight = match($size) {
        'small' => 'h-8',
        'large' => 'h-14',
        default => 'h-10',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none']) }}>
    @if(file_exists(public_path('logo.png')))
        <img src="{{ asset('logo.png') }}" alt="BT Bautechnik UG" class="{{ $imgHeight }} w-auto object-contain rounded-xl shadow-xs">
    @else
        <!-- High-Precision Architectural Vector Badge -->
        <div class="relative flex items-center justify-center">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-600 via-indigo-600 to-amber-500 flex items-center justify-center shadow-lg shadow-blue-500/25 border border-blue-400/30">
                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <!-- Crane / Blueprint Construction Tower Icon -->
                    <path d="M4 22h16" />
                    <path d="M8 22V7l8-4v19" />
                    <path d="M8 12h8" />
                    <path d="M8 17h8" />
                    <path d="M12 3v19" />
                    <circle cx="16" cy="7" r="1" fill="currentColor" />
                </svg>
            </div>
            <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-amber-400 border-2 border-slate-950 rounded-full flex items-center justify-center text-[7px] font-black text-slate-950 shadow-xs">
                ★
            </div>
        </div>
    @endif

    @if($variant === 'full')
        <div class="flex flex-col">
            <div class="flex items-center gap-1.5 leading-none">
                <span class="font-black tracking-tight text-white text-base sm:text-lg">BT BAUTECHNIK</span>
                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-amber-500/20 text-amber-300 border border-amber-500/30">UG</span>
            </div>
            <span class="text-[9.5px] font-bold text-slate-400 tracking-wider uppercase mt-0.5">Bauunternehmen & ERP-Cockpit</span>
        </div>
    @endif
</div>
