@props(['name' => 'grid'])
@php
    $paths = [
        'grid' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'book' => 'M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V4c-3-1-6-1-9 1z M12 5v15',
        'warning' => 'M12 3 2 21h20L12 3z M12 9v5 M12 17v.1',
        'document' => 'M14 2H5v20h14V7l-5-5z M14 2v5h5 M8 12h8 M8 16h8',
        'ruler' => 'M3 15 15 3l6 6L9 21l-6-6z M8 10l3 3 M12 6l3 3 M4 14l3 3',
        'folder' => 'M3 7V4h6l3 3h9v13H3V7z',
        'users' => 'M16 21v-3a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v3 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M17 4a4 4 0 0 1 0 8 M22 21v-3a4 4 0 0 0-3-4',
        'calendar' => 'M3 5h18v16H3z M7 2v6 M17 2v6 M3 10h18 M7 14h2 M15 14h2',
        'truck' => 'M2 5h12v13H2z M14 10h4l4 4v4h-8 M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4 M18 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4',
        'chart' => 'M3 3v18h18 M7 16v-5 M12 16V7 M17 16v-9',
        'clock' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20 M12 6v6l4 2',
        'box' => 'M12 2 2 7v10l10 5 10-5V7L12 2z M2 7l10 5 10-5 M12 12v10 M7 4.5l10 5',
        'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3l1-2h4l1 2 3 2h3v4l2 1v4l-2 1v4h-3l-3 2-1 2h-4l-1-2-3-2H3v-4l-2-1v-4l2-1V5h3l3-2z',
        'sparkles' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3z',
        'search' => 'M11 3a8 8 0 1 0 0 16 8 8 0 0 0 0-16 M17 17l5 5',
        'plus' => 'M12 5v14 M5 12h14',
        'close' => 'M6 6l12 12 M6 18 18 6',
        'arrow' => 'M5 12h14 M13 6l6 6-6 6',
        'camera' => 'M3 6h4l2-3h6l2 3h4v15H3V6z M12 9a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'chevron' => 'M8 4l8 8-8 8',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}" /></svg>
