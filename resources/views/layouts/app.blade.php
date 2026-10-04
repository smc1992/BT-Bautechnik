<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-y-scroll" style="scrollbar-gutter: stable;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BT Bautechnik') }} - Cockpit & Controlling</title>

        <!-- PWA Manifest & Icons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=4">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}?v=4">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}?v=4">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=4">
        <link rel="manifest" href="{{ asset('manifest.json') }}?v=4">
        <meta name="theme-color" content="#0f172a">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="BT Bautechnik">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Custom Stylesheets -->
        <link rel="stylesheet" href="{{ asset('css/invoice-style.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="ui-app font-sans antialiased" data-user-id="{{ auth()->id() }}">
        <a href="#main-content" class="ui-skip-link">Zum Inhalt springen</a>
        <div class="ui-network-status" data-network-banner hidden role="status">Verbindung unterbrochen. Tagesbericht-Entwürfe bleiben auf diesem Gerät. Zum Übertragen bitte wieder verbinden und speichern.</div>
        <livewire:layout.navigation />
        <div class="ui-app-frame">
            <livewire:layout.sidebar />
            <main id="main-content" tabindex="-1" class="ui-main {{ request()->routeIs('ai-agent') ? 'ui-main-agent' : '' }}">
                @if(isset($header))<div class="ui-page-heading">{{ $header }}</div>@endif
                {{ $slot }}
            </main>
        </div>
        <x-command-palette />
        <x-mobile-quick-action />
        <div id="ui-notifications" class="ui-notifications" role="status" aria-live="polite" aria-atomic="true"></div>
    </body>
</html>
