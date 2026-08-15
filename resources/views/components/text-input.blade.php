@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full bg-slate-50 border border-slate-300 text-slate-950 placeholder-slate-400 focus:bg-white focus:border-slate-950 focus:ring-2 focus:ring-amber-500/20 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-medium transition shadow-2xs']) }}>
