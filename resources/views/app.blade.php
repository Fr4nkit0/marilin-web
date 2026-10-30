<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Marilin') }}</title>

        {{-- Before anything paints: the saved theme, or the system's (same rule as resources/js/lib/theme.ts). --}}
        <script>
            (function () {
                var choice = localStorage.getItem('marilin-theme');
                var dark = choice === 'dark' || (choice !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Outfit:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        {{-- A page lives in its module (ADR 0003). --}}
        @vite(['resources/js/app.tsx', "resources/js/modules/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
