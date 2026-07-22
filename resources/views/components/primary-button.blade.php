<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 border border-transparent rounded-xl font-bold text-xs text-white uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition shadow-md shadow-blue-500/10']) }}>
    {{ $slot }}
</button>
