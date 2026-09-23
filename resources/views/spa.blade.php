<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="/images/soscom-sms-icon.png">
        <link rel="apple-touch-icon" href="/images/soscom-sms-icon.png">

        <title>Soscom SMS</title>
        <meta name="title" content="Soscom SMS">
        {{-- Restore the API sentence when resources/js/lib/features.ts developerApiVisible is true. --}}
        <meta name="description" content="A simple and reliable platform for sending bulk SMS.">
        <meta name="theme-color" content="#379019">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Manrope:wght@200..800&display=swap" rel="stylesheet">

        <script>
            (function () {
                try {
                    var match = document.cookie.match(/(?:^|; )vite-ui-theme=([^;]*)/);
                    var theme = match ? decodeURIComponent(match[1]) : 'light';
                    var resolved = theme === 'system'
                        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                        : theme;
                    document.documentElement.classList.remove('light', 'dark');
                    document.documentElement.classList.add(resolved === 'dark' ? 'dark' : 'light');
                    var meta = document.querySelector('meta[name="theme-color"]');
                    if (meta) {
                        meta.setAttribute('content', resolved === 'dark' ? '#379019' : '#379019');
                    }
                } catch (e) {
                    document.documentElement.classList.add('light');
                }
            })();
        </script>

        @vite(['resources/js/main.tsx'])
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
