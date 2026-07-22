<div {{ $attributes->merge(['class' => 'flex items-center space-x-3 select-none']) }}>
    <!-- Emblem Icon -->
    <div class="relative flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 via-indigo-600 to-cyan-500 p-0.5 shadow-lg shadow-blue-500/20">
        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
            <svg class="w-6 h-6 text-cyan-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Isometric B & T Structural Beam Icon -->
                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 12V22" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
    </div>
    <!-- Brand Typography -->
    <div class="flex flex-col">
        <div class="flex items-center space-x-1.5 leading-none">
            <span class="text-xl font-extrabold tracking-tight text-white font-sans">BT</span>
            <span class="text-xl font-light tracking-wide text-cyan-400 font-sans">BAUTECHNIK</span>
        </div>
        <span class="text-[9px] font-semibold tracking-widest text-slate-400 uppercase mt-0.5">Bauunternehmen & Sanierung</span>
    </div>
</div>
