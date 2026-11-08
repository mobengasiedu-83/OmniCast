<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) data-theme="{{ $theme ?? 'ember' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background colour based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.985 0.004 265);
            }

            html.dark {
                background-color: oklch(0.18 0.02 274);
            }
        </style>

        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
        <link rel="icon" href="{{ asset('favicon.png') }}?v=2" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>

        {{-- Vendor tracking tags pasted under Admin -> Integrations. --}}
        @foreach ($integrationHeadScripts ?? [] as $integrationHeadScript)
            {!! $integrationHeadScript !!}
        @endforeach
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- Body tags (for example the Google Tag Manager noscript frame). --}}
        @foreach ($integrationBodyScripts ?? [] as $integrationBodyScript)
            {!! $integrationBodyScript !!}
        @endforeach
    </body>
</html>
