<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-y-scroll" style="scrollbar-gutter: stable;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BT Bautechnik') }} - Cockpit & Controlling</title>

        <!-- PWA Manifest & Theme -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#2563eb">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="BT Bautechnik">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Custom Stylesheets -->
        <link rel="stylesheet" href="{{ asset('css/invoice-style.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="{ isOffline: !navigator.onLine }" 
          x-init="
              window.addEventListener('offline', () => isOffline = true);
              window.addEventListener('online', () => isOffline = false);
              if ('serviceWorker' in navigator) {
                  navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW Reg Error', err));
              }
          " 
          class="font-sans antialiased bg-slate-50 text-slate-900 selection:bg-blue-600 selection:text-white flex flex-col min-h-screen">

        <!-- Offline Status Banner -->
        <div x-show="isOffline" x-cloak class="bg-amber-500 text-slate-900 px-4 py-2 text-center text-xs font-black shadow-md flex items-center justify-center gap-2 relative z-50 sticky top-0">
            <span class="w-2.5 h-2.5 rounded-full bg-slate-900 animate-ping"></span>
            <span>📡 OFFLINE-MODUS AKTIV: Bautagebuch-Einträge & Notizen werden lokal gespeichert und bei Netzempfang automatisch synchronisiert!</span>
        </div>

        <div class="min-h-screen bg-slate-50 flex flex-col">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white border-b border-slate-200/80 shadow-xs">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Main Content Area with HubSpot-Style Sidebar -->
            <div class="flex-1 w-full flex items-start">
                <livewire:layout.sidebar />

                <main class="flex-1 min-w-0 {{ request()->routeIs('ai-agent') ? 'p-0 h-[calc(100vh-4rem)] overflow-hidden flex flex-col' : 'p-4 sm:p-6 lg:p-8 w-full overflow-x-hidden' }}">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <!-- Global Command Palette -->
        <x-command-palette />

        <!-- Mobile Bottom Navigation & Quick Action Center -->
        <x-mobile-quick-action />
    </body>
</html>
