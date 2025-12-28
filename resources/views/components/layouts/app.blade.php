<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Super Tienda' }}</title>
        @vite('resources/css/app.css')    
        @vite('resources/js/app.js')  
        @livewireStyles 
        <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
    </head>
    <body class="bg-slate-200 dark:bg-slate-700">
        @livewire('partials.navbar')
        <main id="app">
            {{ $slot }}
        </main>

        @livewire('partials.footer')
        @livewireScripts
    </body>
</html>
