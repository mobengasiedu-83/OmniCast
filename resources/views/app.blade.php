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
        @if (request()->routeIs('home'))
            @php
                $seoTitle = 'OmniCast | SMS, Voice & Email Broadcasting Platform';
                $seoDescription = 'OmniCast by SysUp360 is a multi-channel communication platform for SMS, voice and email broadcasting, campaign automation, contact management and delivery analytics.';
                $canonicalUrl = rtrim(config('app.canonical_url'), '/').'/';
            @endphp
            <meta head-key="description" name="description" content="{{ $seoDescription }}">
            <link head-key="canonical" rel="canonical" href="{{ $canonicalUrl }}">
            <meta head-key="og:type" property="og:type" content="website">
            <meta head-key="og:title" property="og:title" content="{{ $seoTitle }}">
            <meta head-key="og:description" property="og:description" content="Create and manage SMS, voice and email broadcasting campaigns with OmniCast by SysUp360.">
            <meta head-key="og:url" property="og:url" content="{{ $canonicalUrl }}">
            <x-inertia::head>
                <title>{{ $seoTitle }}</title>
            </x-inertia::head>
            <script type="application/ld+json">
                {!! json_encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'SoftwareApplication',
                    'name' => 'OmniCast',
                    'alternateName' => 'SysUp360 OmniCast',
                    'url' => $canonicalUrl,
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => 'OmniCast is a multi-channel communications platform for SMS, voice and email broadcasting, campaign automation and delivery analytics.',
                    'provider' => [
                        '@type' => 'Organization',
                        'name' => 'SysUp360',
                        'url' => 'https://sysup360.com/',
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
            </script>
        @else
            <link head-key="canonical" rel="canonical" href="{{ request()->url() }}">
            <x-inertia::head>
                <title>{{ config('app.name', 'OmniCast') }}</title>
            </x-inertia::head>
        @endif

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
