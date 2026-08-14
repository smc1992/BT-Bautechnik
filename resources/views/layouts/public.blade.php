<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'BT BAUTECHNIK ERP | Die All-in-One Bauträger- & Bauleiter-Software' }}</title>
        <meta name="description" content="Die All-in-One Bau-Software für Bauträger, Bauunternehmen & Generalübernehmer. VOB/B Nachtragsmanagement, VOB/C Aufmaße, KI-Bautagebuch, 360° Kundenmaske & DATEV Export.">

        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

        <!-- Open Graph / Meta -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="BT BAUTECHNIK Cockpit | Bauträger & Bauleiter ERP">
        <meta property="og:description" content="Entwickelt von Bauunternehmern für Bauprofis. VOB/B Nachträge, VOB/C Aufmaße, KI-Sprachmemo Bautagebuch und 360° Kunden-Zentrale.">

        <!-- Fonts (Inter) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 selection:bg-blue-600 selection:text-white min-h-screen">
        <main>
            {{ $slot }}
        </main>
        @livewireScripts
    </body>
</html>
