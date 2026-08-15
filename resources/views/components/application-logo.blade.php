<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 select-none group']) }}>
    <div class="relative flex items-center justify-center shrink-0">
        <img src="{{ asset('images/branding/bt-monogram-v2.png') }}?v=1" 
             alt="BT Bautechnik Monogramm" 
             class="h-9 w-auto object-contain drop-shadow-xs group-hover:scale-105 transition-transform duration-300">
    </div>
    <div class="flex flex-col text-left">
        <div class="flex items-center gap-1.5 leading-none">
            <span class="font-black tracking-tight text-slate-950 text-sm sm:text-base whitespace-nowrap">BT BAUTECHNIK</span>
            <span class="text-[8px] sm:text-[9px] font-black uppercase px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">UG</span>
        </div>
        <span class="text-[8px] sm:text-[9px] font-black text-amber-700 tracking-wider uppercase mt-0.5 whitespace-nowrap">Cockpit & Bauführung</span>
    </div>
</div>
