<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>document.documentElement.classList.add('js');</script>

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
            /* Smooth, restrained reveal and micro-interaction system */
            .reveal-on-scroll {
                --reveal-x: 0px;
                --reveal-y: 24px;
                --reveal-scale: 1;
                --reveal-delay: 0ms;
            }
            .js .reveal-on-scroll {
                opacity: 0;
                transform: translate3d(var(--reveal-x), var(--reveal-y), 0) scale(var(--reveal-scale));
                transition:
                    opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1) var(--reveal-delay),
                    transform 0.7s cubic-bezier(0.16, 1, 0.3, 1) var(--reveal-delay);
                will-change: opacity, transform;
            }
            .js .reveal-on-scroll.is-visible {
                opacity: 1 !important;
                transform: translate3d(0, 0, 0) scale(1) !important;
            }
            .reveal-from-left { --reveal-x: -24px; --reveal-y: 0px; }
            .reveal-from-right { --reveal-x: 24px; --reveal-y: 0px; }
            .reveal-scale { --reveal-y: 14px; --reveal-scale: 0.975; }
            .reveal-delay-100 { --reveal-delay: 100ms; }
            .reveal-delay-200 { --reveal-delay: 200ms; }
            .reveal-delay-300 { --reveal-delay: 300ms; }
            .reveal-delay-400 { --reveal-delay: 400ms; }

            .micro-action .micro-arrow {
                display: inline-block;
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .micro-action:hover .micro-arrow {
                transform: translateX(4px);
            }

            .micro-trust .micro-icon {
                transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease;
            }
            .micro-trust:hover .micro-icon {
                transform: translateY(-2px) rotate(-2deg);
                border-color: rgba(217, 119, 6, 0.45);
            }

            @keyframes micro-float {
                0%, 100% { transform: translate3d(0, 0, 0); }
                50% { transform: translate3d(0, -5px, 0); }
            }
            @keyframes micro-float-reverse {
                0%, 100% { transform: translate3d(0, 0, 0); }
                50% { transform: translate3d(0, 4px, 0); }
            }
            @keyframes micro-status-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
                50% { box-shadow: 0 0 0 5px rgba(245, 158, 11, 0.14); }
            }
            .micro-float { animation: micro-float 6s ease-in-out infinite; }
            .micro-float-reverse { animation: micro-float-reverse 7s ease-in-out infinite; }
            .micro-status-dot { animation: micro-status-pulse 2.8s ease-in-out infinite; }

            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto !important; }
                .js .reveal-on-scroll,
                .js .reveal-on-scroll.is-visible {
                    opacity: 1 !important;
                    transform: none !important;
                    transition: none !important;
                }
                .micro-float,
                .micro-float-reverse,
                .micro-status-dot {
                    animation: none !important;
                }
                .micro-action .micro-arrow,
                .micro-trust .micro-icon {
                    transition: none !important;
                }
            }

            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 selection:bg-blue-600 selection:text-white min-h-screen">
        <main>
            {{ $slot }}
        </main>

        <!-- IntersectionObserver for fluid on-scroll animations with robust Livewire fallback -->
        <script>
            (function() {
                function initScrollReveal() {
                    document.querySelectorAll('[data-reveal]').forEach((element) => {
                        element.classList.add('reveal-on-scroll');

                        const variant = element.dataset.reveal;
                        if (variant === 'left') element.classList.add('reveal-from-left');
                        if (variant === 'right') element.classList.add('reveal-from-right');
                        if (variant === 'scale') element.classList.add('reveal-scale');
                    });

                    document.querySelectorAll('[data-reveal-group]').forEach((group) => {
                        const items = group.querySelectorAll(':scope > *');
                        items.forEach((item, index) => {
                            item.classList.add('reveal-on-scroll');
                            item.style.setProperty('--reveal-delay', `${Math.min(index, 5) * 80}ms`);

                            if (group.dataset.revealGroup === 'trust') {
                                item.classList.add('micro-trust');
                                item.firstElementChild?.classList.add('micro-icon');
                            }
                        });
                    });

                    const elements = document.querySelectorAll('.reveal-on-scroll');
                    if (!elements.length) return;

                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                        elements.forEach(el => el.classList.add('is-visible'));
                        return;
                    }

                    const observer = new IntersectionObserver((entries, obs) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                obs.unobserve(entry.target);
                            }
                        });
                    }, {
                        root: null,
                        rootMargin: '0px 0px -8% 0px',
                        threshold: 0.08
                    });

                    elements.forEach(el => {
                        const rect = el.getBoundingClientRect();
                        if (rect.top < window.innerHeight) {
                            el.classList.add('is-visible');
                        } else if (!el.classList.contains('is-visible')) {
                            observer.observe(el);
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initScrollReveal);
                } else {
                    initScrollReveal();
                }

                document.addEventListener('livewire:navigated', initScrollReveal);
                document.addEventListener('livewire:initialized', () => {
                    if (window.Livewire) {
                        Livewire.hook('morph.updated', () => {
                            initScrollReveal();
                        });
                    }
                });
            })();
        </script>

        @livewireScripts
    </body>
</html>
