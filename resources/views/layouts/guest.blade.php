<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&family=noto-sans-lao:400,500,600,700&family=playfair-display:600,700&family=noto-serif-lao:600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-forest-900 antialiased">
        <div class="pattern-lao flex min-h-screen flex-col items-center bg-forest-700 pt-10 sm:justify-center sm:pt-0">
            <div>
                <a href="/" wire:navigate>
                    <x-app-logo size="h-16 w-16" :show-name="true" :tagline="true" name-class="font-display text-2xl font-bold" class="flex-col gap-3 text-center text-cream-50" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden bg-cream-50 px-6 py-6 shadow-xl sm:max-w-md sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
