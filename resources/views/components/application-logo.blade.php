<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 select-none group']) }}>
    <div class="p-1 rounded-2xl bg-white border border-slate-200 shadow-xs group-hover:scale-105 transition-transform duration-300">
        <img src="{{ asset('logo-icon-transparent.png') }}?v=3" 
             alt="BT Bautechnik ERP" 
             class="h-9 w-auto object-contain drop-shadow-2xs">
    </div>
    <div class="flex flex-col text-left">
        <div class="flex items-center gap-1.5 leading-none">
            <span class="font-black tracking-tight text-slate-950 text-base">BT BAUTECHNIK</span>
            <span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300">UG</span>
        </div>
        <span class="text-[9px] font-extrabold text-blue-700 uppercase tracking-wider mt-0.5">ERP Cockpit</span>
    </div>
</div>
