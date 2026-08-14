@props(['variant' => 'full', 'size' => 'default', 'theme' => 'light'])

@php
    $imgHeight = match($size) {
        'small' => 'h-8 sm:h-10',
        'large' => 'h-12 sm:h-16',
        'icon' => 'h-9 sm:h-12',
        default => 'h-9 sm:h-12',
    };

    $titleSize = match($size) {
        'small' => 'text-xs sm:text-sm',
        'large' => 'text-lg sm:text-xl',
        default => 'text-sm sm:text-base lg:text-lg',
    };

    $subSize = match($size) {
        'small' => 'text-[7.5px] sm:text-[8.5px]',
        'large' => 'text-[9.5px] sm:text-[11px]',
        default => 'text-[7.5px] sm:text-[9.5px]',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 sm:gap-3 select-none group']) }}>
    <!-- Architectural Building B + Crane T Monogram Icon (Bold & Uncut) -->
    <div class="relative flex items-center justify-center shrink-0">
        <img src="{{ asset('logo-icon-transparent.png') }}?v=5" 
             alt="BT Bautechnik (Gebäude B & Kran T)" 
             class="{{ $imgHeight }} w-auto object-contain drop-shadow-xs group-hover:scale-105 transition-transform duration-300">
    </div>

    @if($variant === 'full')
        <div class="flex flex-col text-left">
            <div class="flex items-center gap-1 sm:gap-1.5 leading-none">
                <span class="font-black tracking-tight text-slate-950 {{ $titleSize }} whitespace-nowrap">BT BAUTECHNIK</span>
                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-wider px-1 sm:px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">UG</span>
            </div>
            <span class="{{ $subSize }} font-black text-blue-700 tracking-wider uppercase mt-0.5 sm:mt-1 whitespace-nowrap">Bauträger & Bauleiter-Software</span>
        </div>
    @endif
</div>
