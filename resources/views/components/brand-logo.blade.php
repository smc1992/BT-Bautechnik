@props(['variant' => 'full', 'size' => 'default', 'theme' => 'light'])

@php
    $imgHeight = match($size) {
        'small' => 'h-10',
        'large' => 'h-16',
        'icon' => 'h-12',
        default => 'h-12 sm:h-13',
    };

    $titleSize = match($size) {
        'small' => 'text-sm',
        'large' => 'text-xl',
        default => 'text-base sm:text-lg',
    };

    $subSize = match($size) {
        'small' => 'text-[8.5px]',
        'large' => 'text-[11px]',
        default => 'text-[9.5px]',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none group']) }}>
    <!-- Architectural Building B + Crane T Monogram Icon (Bold & Uncut) -->
    <div class="relative flex items-center justify-center shrink-0">
        <img src="{{ asset('logo-icon-transparent.png') }}?v=5" 
             alt="BT Bautechnik (Gebäude B & Kran T)" 
             class="{{ $imgHeight }} w-auto object-contain drop-shadow-xs group-hover:scale-105 transition-transform duration-300">
    </div>

    @if($variant === 'full')
        <div class="flex flex-col text-left">
            <div class="flex items-center gap-1.5 leading-none">
                <span class="font-black tracking-tight text-slate-950 {{ $titleSize }}">BT BAUTECHNIK</span>
                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">UG</span>
            </div>
            <span class="{{ $subSize }} font-black text-blue-700 tracking-wider uppercase mt-1">Bauträger & Bauleiter-Software</span>
        </div>
    @endif
</div>
