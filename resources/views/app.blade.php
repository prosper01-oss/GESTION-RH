<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <script>
            // Applique le thème avant l'affichage pour éviter un flash clair.
            (function () {
                var choice = 'system';
                try { choice = localStorage.getItem('omit-theme') || 'system'; } catch (e) {}
                var dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/main.jsx'])
    </head>
    <body class="bg-ivory text-slate-900 antialiased">
        <div id="app"></div>
    </body>
</html>
