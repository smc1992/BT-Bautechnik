<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'BT Bautechnik | Die Bauleiter- & Bauträger-Software aus der Baupraxis' }}</title>
        <meta name="description" content="Entwickelt von der BT Bautechnik UG. Bautagebuch per KI-Sprachmemo, VOB/B § 2 Nachtragsmanagement, Digitales Aufmaß DIN 18299 & DATEV SKR03/04.">

        <!-- Browser Favicon & PWA Icons for iOS / Android -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=4">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}?v=4">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}?v=4">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=4">
        <link rel="manifest" href="{{ asset('manifest.json') }}?v=4">
        <meta name="theme-color" content="#1d4ed8">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="BT Bautechnik">

        <!-- Open Graph / WhatsApp / Facebook / LinkedIn -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="BT Bautechnik UG">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="BT Bautechnik – Die Bauleiter- & Bauträger-Software aus der Baupraxis">
        <meta property="og:description" content="Entwickelt von der BT Bautechnik UG. VOB/B § 2 Nachtragsmanagement, Digitales Aufmaß DIN 18299, KI-Bautagebuch und DATEV-Schnittstelle.">
        <meta property="og:image" content="{{ asset('og-image.jpg') }}?v=4">
        <meta property="og:image:secure_url" content="{{ asset('og-image.jpg') }}?v=4">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="BT Bautechnik Software Cockpit">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="BT Bautechnik – Bauleiter- & Bauträger-Software">
        <meta name="twitter:description" content="Aus der Baupraxis für Bauträger & Bauleiter: Bautagebuch, VOB/B Nachträge, Digitales Aufmaß & DATEV.">
        <meta name="twitter:image" content="{{ asset('og-image.jpg') }}?v=4">

        <!-- Fonts (Inter) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <style>
            /* Smooth Scroll Reveal Animation Classes */
            .reveal-on-scroll {
                opacity: 0;
                transform: translateY(24px);
                transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
                will-change: opacity, transform;
            }
            .reveal-on-scroll.is-visible {
                opacity: 1;
                transform: translateY(0);
            }
            .reveal-delay-100 { transition-delay: 0.1s; }
            .reveal-delay-200 { transition-delay: 0.2s; }
            .reveal-delay-300 { transition-delay: 0.3s; }
            .reveal-delay-400 { transition-delay: 0.4s; }
        </style>
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 selection:bg-blue-600 selection:text-white min-h-screen">
        <main>
            {{ $slot }}
        </main>

        <!-- IntersectionObserver for fluid on-scroll animations -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const observerOptions = {
                    root: null,
                    rootMargin: '0px 0px -40px 0px',
                    threshold: 0.08
                };

                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            obs.unobserve(entry.target);
                        }
                    });
                }, observerOptions);

                document.querySelectorAll('.reveal-on-scroll').forEach(el => observer.observe(el));

                // Re-bind when Livewire updates DOM
                document.addEventListener('livewire:navigated', () => {
                    document.querySelectorAll('.reveal-on-scroll:not(.is-visible)').forEach(el => observer.observe(el));
                });
            });
        </script>

        @livewireScripts
    </body>
</html>
