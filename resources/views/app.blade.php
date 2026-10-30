<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="theme-color" content="#F2F2F7" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
        <meta name="color-scheme" content="light dark">
        <script src="/theme.js"></script>
        <meta name="description" content="A little space for two hearts.">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="LoveyNest">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <title inertia>{{ config('app.name') }}</title>
        <meta name="usandus-build" content="{{ \App\Support\BuildId::current() }}">
        @vite('resources/js/app.js')
        <x-inertia::head />
    </head>
    <body class="bg-cream text-plum antialiased">
        <x-inertia::app />
    </body>
</html>
