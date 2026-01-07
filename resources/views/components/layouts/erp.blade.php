<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>IgniaFungi - Panel de Producción</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-900 antialiased"> 
    <div class="flex flex-col h-screen overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <img src="{{ asset('/images/logo_ignia_sin_texto.png') }}" class="h-8" alt="Logo">
                <h1 class="text-2xl font-black text-gray-800 uppercase tracking-tighter">
                Control de Producción <span class="text-gold-ignia">Ignia Fungi</span>
                </h1>
            </div>
             <a href="/admin" class="group flex items-center gap-2 text-gray-500 hover:text-gold-ignia transition-all">
                <svg class="w-6 h-6 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z" />
                </svg>
                <span class="font-bold text-sm uppercase tracking-widest">Panel Admin</span>
            </a>
           <div class="flex items-center gap-4">
                <span class="text-xs font-bold text-gray-400 border border-gray-200 px-3 py-1 rounded-full bg-gray-50">
                    USER: {{ strtoupper(auth()->user()->name) }}
                </span>
            </div>
        </header>

        <main class="flex-1">
            <div class="erp-container">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>