<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-slate-100 border border-slate-300 rounded-xl font-bold text-xs text-slate-700 uppercase tracking-wider shadow-2xs hover:bg-slate-200 focus:outline-none disabled:opacity-25 transition duration-150']) }}>
    {{ $slot }}
</button>
