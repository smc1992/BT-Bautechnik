<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-slate-950 hover:bg-slate-800 active:bg-slate-900 border border-slate-800 rounded-xl font-bold text-xs text-white uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-amber-500/30 transition shadow-md shadow-slate-950/10 cursor-pointer btn-press']) }}>
    {{ $slot }}
</button>
