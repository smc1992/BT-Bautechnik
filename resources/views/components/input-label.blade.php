@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1']) }}>
    {{ $value ?? $slot }}
</label>
