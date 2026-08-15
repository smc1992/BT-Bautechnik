<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BT Bautechnik') }} - Anmelden & Cockpit</title>

        <!-- PWA Manifest & Theme -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=4">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}?v=4">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}?v=4">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=4">
        <link rel="manifest" href="{{ asset('manifest.json') }}?v=4">
        <meta name="theme-color" content="#091224">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="BT Bautechnik">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-[#091224] min-h-screen flex flex-col selection:bg-amber-500 selection:text-slate-950">
        
        <div class="min-h-screen flex flex-col lg:flex-row w-full flex-1">
            
            <!-- Left Side: Architectural Brand & Feature Showcase (Visible on Large Screens) -->
            <div class="hidden lg:flex lg:w-1/2 relative bg-slate-950 p-12 xl:p-16 flex-col justify-between overflow-hidden border-r border-slate-800/80">
                <!-- Hairline & Blueprint Grid Overlay -->
                <div class="arch-hairline-overlay"></div>
                <div class="absolute -top-24 -left-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-slate-800/20 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Top Brand Header -->
                <div class="relative z-10 space-y-5">
                    <a href="/" class="inline-flex items-center gap-3 bg-white/95 backdrop-blur-md px-4 py-2 rounded-2xl shadow-xl border border-white/20 hover:opacity-95 transition">
                        <x-brand-logo size="default" />
                    </a>

                    <div class="pt-4">
                        <div class="arch-section-label mb-3">
                            <span>COCKPIT PRO & STEUERZENTRALE</span>
                        </div>
                        <h1 class="text-3xl xl:text-4xl font-black text-white tracking-tight leading-tight">
                            Digitale Bauleitung & <br>
                            <span class="text-amber-500">
                                rechtssicheres Controlling
                            </span>
                        </h1>
                        <p class="text-slate-400 text-xs xl:text-sm mt-3 max-w-lg leading-relaxed font-medium">
                            Entwickelt aus der täglichen Baupraxis der <strong>BT Bautechnik UG</strong> in Bayern. Lückenlose Baustellen-Steuerung, VOB/B Nachträge, digitale Aufmaße und DATEV SKR03/04 in Echtzeit.
                        </p>
                    </div>
                </div>

                <!-- Feature Highlights Cards with Architectural Line Icons -->
                <div class="relative z-10 space-y-3.5 my-6">
                    <!-- Feature 1: VOB Nachträge -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 backdrop-blur-md shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">VOB/B § 2 Nachtragsmanagement</h4>
                            <p class="text-[11px] text-slate-400 font-medium">Rechtssichere Mehrkostenangebote per Knopfdruck als PDF vor Ausführung</p>
                        </div>
                    </div>

                    <!-- Feature 2: Whisper KI Bautagebuch -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 backdrop-blur-md shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">KI-Bautagebuch & Sprachmemo</h4>
                            <p class="text-[11px] text-slate-400 font-medium">30s Audio auf der Baustelle einsprechen – druckreife Berichte mit Wetter & Gewerken</p>
                        </div>
                    </div>

                    <!-- Feature 3: DATEV SKR03/04 -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800 backdrop-blur-md shadow-xs">
                        <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">DATEV SKR03/04 & § 13b UStG</h4>
                            <p class="text-[11px] text-slate-400 font-medium">Automatisierter Buchungsstapel-Export direkt an Ihre Steuerkanzlei</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom Trust & Compliance Footer -->
                <div class="relative z-10 pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-slate-400 font-semibold">Server Online • 256-Bit SSL</span>
                    </div>
                    <span class="text-slate-400 font-semibold">Made in Bayern</span>
                </div>
            </div>

            <!-- Right Side: Login Form Area (Mobile + Desktop) -->
            <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12 bg-[#091224] relative">
                <!-- Background Ambient Glow -->
                <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="w-full max-w-md space-y-6 relative z-10">
                    
                    <!-- Mobile Brand Logo Display -->
                    <div class="lg:hidden flex flex-col items-center text-center space-y-3 mb-6">
                        <a href="/" class="bg-white/95 backdrop-blur-md px-4 py-2 rounded-2xl shadow-xl border border-white/20 inline-block">
                            <x-brand-logo size="default" />
                        </a>
                    </div>

                    <!-- Auth Form Slot Container (Architectural Card) -->
                    <div class="bg-white border border-slate-200/90 rounded-3xl p-7 sm:p-9 shadow-2xl space-y-6">
                        {{ $slot }}
                    </div>

                    <!-- Enterprise Security Notice Footer -->
                    <div class="text-center space-y-1">
                        <p class="text-[11px] text-slate-400 font-medium flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Geschützter interner Mitarbeiter- & Partnerzugang</span>
                        </p>
                        <p class="text-[10px] text-slate-500">
                            BT Bautechnik UG (haftungsbeschränkt) • Brunnenstraße 4, 92334 Berching
                        </p>
                    </div>

                </div>
            </div>

        </div>

    </body>
</html>
