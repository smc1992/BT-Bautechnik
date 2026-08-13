<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BT Bautechnik') }} - Anmelden & Cockpit</title>

        <!-- PWA Manifest & Theme -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#1e40af">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-slate-950 min-h-screen flex flex-col selection:bg-blue-600 selection:text-white">
        
        <div class="min-h-screen flex flex-col lg:flex-row w-full flex-1">
            
            <!-- Left Side: Enterprise Brand & Feature Showcase (Visible on Large Screens) -->
            <div class="hidden lg:flex lg:w-1/2 relative bg-slate-950 p-12 xl:p-16 flex-col justify-between overflow-hidden border-r border-slate-800">
                <!-- Ambient Background Glows -->
                <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-25 pointer-events-none"></div>

                <!-- Top Brand Header -->
                <div class="relative z-10 space-y-4">
                    <div class="inline-flex items-center gap-3">
                        <div class="bg-white px-3.5 py-1.5 rounded-2xl shadow-md border border-white/20 flex items-center justify-center">
                            <x-application-logo class="h-8 w-auto object-contain" />
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-500/40 backdrop-blur-md">
                            Cockpit PRO v2.4
                        </span>
                    </div>

                    <div class="pt-6">
                        <h1 class="text-3xl xl:text-4xl font-black text-white tracking-tight leading-tight">
                            Digitale Bauleitung & <br>
                            <span class="bg-gradient-to-r from-blue-400 via-indigo-300 to-cyan-400 bg-clip-text text-transparent">
                                Echtzeit-Controlling
                            </span>
                        </h1>
                        <p class="text-slate-400 text-sm xl:text-base mt-3 max-w-lg leading-relaxed font-medium">
                            Die zentrale Plattform für Bauzeitenplanung, KI-gestützte Bautagebücher, Mängel-Management und VOB/B-konforme Finanzabwicklung.
                        </p>
                    </div>
                </div>

                <!-- Feature Highlights Cards -->
                <div class="relative z-10 space-y-3.5 my-8">
                    <!-- Feature 1 -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-xl bg-slate-900/80 border border-slate-800/90 backdrop-blur-md shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-blue-600/20 border border-blue-500/30 text-blue-400 flex items-center justify-center text-lg shrink-0">
                            🏗️
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Präzise Baustellen-Kostenkontrolle</h4>
                            <p class="text-[11px] text-slate-400">Soll/Ist-Vergleiche, Materialbudgets und Subunternehmer-Abrechnung</p>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-xl bg-slate-900/80 border border-slate-800/90 backdrop-blur-md shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-cyan-600/20 border border-cyan-500/30 text-cyan-400 flex items-center justify-center text-lg shrink-0">
                            🤖
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Autonomer KI-Bauleiter & Sprachaufmaß</h4>
                            <p class="text-[11px] text-slate-400">OpenAI Whisper Sprach-Bautagebuch & Vision Baustellen-Fotoanalyse</p>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="flex items-center gap-3.5 p-3.5 rounded-xl bg-slate-900/80 border border-slate-800/90 backdrop-blur-md shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-lg shrink-0">
                            📄
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white">Rechtssichere E-Rechnung & ZUGFeRD</h4>
                            <p class="text-[11px] text-slate-400">Automatische XRechnung XML-Generierung und Sammel-Export</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom Trust & Compliance Footer -->
                <div class="relative z-10 pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-slate-400">Server Online • 256-Bit SSL</span>
                    </div>
                    <span>Made in Germany 🇩🇪</span>
                </div>
            </div>

            <!-- Right Side: Login Form Area (Mobile + Desktop) -->
            <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12 bg-slate-900 relative">
                <!-- Mobile Background Ambient Glow -->
                <div class="lg:hidden absolute -top-16 left-1/2 -translate-x-1/2 w-72 h-72 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>

                <div class="w-full max-w-md space-y-6 relative z-10">
                    
                    <!-- Mobile Brand Logo Display -->
                    <div class="lg:hidden flex flex-col items-center text-center space-y-3 mb-6">
                        <div class="bg-white px-4 py-2 rounded-2xl shadow-md border border-white/20">
                            <x-application-logo class="h-10 w-auto object-contain" />
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-white">BT Bautechnik UG</h2>
                            <p class="text-xs text-slate-400 font-medium">Cockpit & Bauführung</p>
                        </div>
                    </div>

                    <!-- Auth Form Slot Container -->
                    <div class="bg-white border border-slate-200/90 rounded-3xl p-7 sm:p-9 shadow-2xl space-y-6">
                        {{ $slot }}
                    </div>

                    <!-- Enterprise Security Notice Footer -->
                    <div class="text-center space-y-1">
                        <p class="text-[11px] text-slate-500 font-medium flex items-center justify-center gap-1.5">
                            <span>🔒</span>
                            <span>Geschützter interner Mitarbeiter- & Partnerzugang</span>
                        </p>
                        <p class="text-[10px] text-slate-600">
                            BT Bautechnik UG (haftungsbeschränkt) • Alle Rechte vorbehalten
                        </p>
                    </div>

                </div>
            </div>

        </div>

    </body>
</html>
