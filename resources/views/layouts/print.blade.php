<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        <style>
            @media print {
                .print-hidden {
                    display: none !important;
                }

                body {
                    background: #fff !important;
                    color: #000 !important;
                }

                .print-document {
                    box-shadow: none !important;
                    border: none !important;
                }
            }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-100 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        {{ $slot }}
    </body>
</html>
