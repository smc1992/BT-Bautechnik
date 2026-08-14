@props(['variant' => 'full', 'size' => 'default', 'theme' => 'light'])

@php
    $imgHeight = match($size) {
        'small' => 'h-9',
        'large' => 'h-14',
        'icon' => 'h-10',
        default => 'h-11',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none']) }}>
    <!-- New Architectural Monogram Icon -->
    <div class="relative flex items-center justify-center shrink-0">
        <img src="{{ asset('logo-icon-transparent.png') }}" alt="BT Bautechnik" class="{{ $imgHeight }} w-auto object-contain drop-shadow-xs">
    </div>

    @if($variant === 'full')
        <div class="flex flex-col text-left">
            <div class="flex items-center gap-1.5 leading-none">
                <span class="font-black tracking-tight text-slate-900 text-base sm:text-lg">BT BAUTECHNIK</span>
                <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-amber-500/15 text-amber-700 border border-amber-500/30">UG</span>
            </div>
            <span class="text-[9.5px] font-extrabold text-blue-700 tracking-wider uppercase mt-0.5">Bauträger & Bauleiter-Software</span>
        </div>
    @endif
</div>
