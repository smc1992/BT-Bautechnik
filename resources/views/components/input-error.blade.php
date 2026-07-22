@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-xs font-semibold text-rose-600 space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
