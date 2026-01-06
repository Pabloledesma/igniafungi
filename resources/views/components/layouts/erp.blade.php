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
<body class="bg-slate-900 antialiased"> <div class="min-h-screen flex flex-col">
        <header class="bg-slate-800 border-b border-slate-700 p-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <img src="{{ asset('/images/logo_ignia_sin_texto.png') }}" class="h-8" alt="Logo">
                <h1 class="text-white font-bold tracking-wider text-sm uppercase">Control de Cultivo</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-slate-400 text-xs">Usuario: {{ auth()->user()->name }}</span>
                <a href="/" class="text-slate-300 hover:text-white text-xs underline">Volver a la Tienda</a>
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