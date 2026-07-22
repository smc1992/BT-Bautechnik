@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1.5 pt-1 border-b-2 border-blue-600 text-sm font-extrabold leading-5 text-blue-600 focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1.5 pt-1 border-b-2 border-transparent text-sm font-bold leading-5 text-slate-600 hover:text-blue-600 hover:border-slate-300 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
