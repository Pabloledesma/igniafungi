<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Ignia Fungi | Orellanas y Hongos Gourmet en Bogotá')</title>
    <meta name="description"
        content="@yield('meta_description', 'Cultivo de hongos gourmet. Frescura y salud desde nuestra granja urbana.')">

    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'Ignia Fungi')">
    <meta property="og:description" content="@yield('meta_description', 'Descubre el poder del reino Fungi.')">
    <meta property="og:image" content="@yield('meta_image', asset('images/og-default.jpg'))">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="@yield('title', 'Ignia Fungi')">
    <meta property="twitter:description" content="@yield('meta_description', 'Hongos gourmet frescos.')">
    <meta property="twitter:image" content="@yield('meta_image', asset('images/og-default.jpg'))">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
    <script src="https://checkout.bold.co/library/boldPaymentButton.js"></script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17876018512"></script>
    <script>window.dataLayer = window.dataLayer || []; function gtag() { dataLayer.push(arguments); } gtag('js', new Date()); gtag('config', 'AW-17876018512');</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://preline.co/assets/css/main.css?v=3.0.1">
    @stack('scripts')
    @stack('schema')
</head>

<body>
    @if(!isset($hideNavbar) || !$hideNavbar)
        @livewire('partials.navbar')
    @endif
    <main id="app">
        {{ $slot }}
    </main>

    @livewire('partials.footer')
    <livewire:ai-chat />
    @livewireScripts
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('livewire:navigated', () => {
            if (window.HSStaticMethods) {
                window.HSStaticMethods.autoInit();
            }
        });
    </script>
</body>

</html>