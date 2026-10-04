@props(['disabled' => false])
<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full bg-white border border-slate-300 text-slate-950 placeholder-slate-500 rounded-xl px-3.5 py-2.5 text-base transition']) }}>
