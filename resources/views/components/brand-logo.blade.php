@props(['variant' => 'full', 'size' => 'default', 'theme' => 'light'])

@php
    $iconSize = match($size) {
        'small' => 'w-8 h-8',
        'large' => 'w-14 h-14',
        'icon' => 'w-10 h-10',
        default => 'w-10 h-10',
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
    <!-- Architectural Precision Vector Monogram Icon -->
    <div class="relative flex items-center justify-center shrink-0">
        <div class="{{ $iconSize }} rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-blue-900 flex items-center justify-center shadow-md shadow-blue-700/20 border border-blue-600/30 group-hover:scale-105 transition-transform duration-300">
            <svg class="w-3/5 h-3/5 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Architectural Isometric Tower & Crane Monogram -->
                <path d="M3 21H21" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                <path d="M6 21V7L14 3V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M14 8H20V12H14" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="#F59E0B" fill-opacity="0.3" />
                <path d="M6 12H14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <path d="M6 16H14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <path d="M10 3V21" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 2" />
            </svg>
        </div>
        <!-- Safety Amber Accent Pill Dot -->
        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-amber-500 border-2 border-white rounded-full shadow-xs"></span>
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
