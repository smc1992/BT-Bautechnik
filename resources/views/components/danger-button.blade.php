<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent rounded-xl font-bold text-xs text-white uppercase tracking-wider hover:bg-rose-700 focus:outline-none shadow-xs transition duration-150']) }}>
    {{ $slot }}
</button>
