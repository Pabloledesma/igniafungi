<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Ignia Fungi' }}</title>
        @vite('resources/css/app.css')    
        @vite('resources/js/app.js')  
        @livewireStyles 
        <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
        <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://preline.co/assets/css/main.css?v=3.0.1">
        @stack('scripts')
    </head>
    <body class="bg-neutral-900">
        @livewire('partials.navbar')
        <main id="app">
            {{ $slot }}
        </main>

        @livewire('partials.footer')
        @livewireScripts
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </body>
</html>
