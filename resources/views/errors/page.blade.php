<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · Soscom SMS</title>
        <link rel="icon" type="image/png" href="/images/soscom-sms-icon.png">
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f6f7f4;
                color: #1c1c1c;
                font-family: Manrope, Inter, ui-sans-serif, system-ui, sans-serif;
            }
            main {
                width: min(28rem, calc(100% - 2rem));
                text-align: center;
            }
            img {
                width: 40px;
                height: 40px;
                object-fit: contain;
            }
            h1 {
                margin: 1rem 0 0.5rem;
                font-size: 1.25rem;
                font-weight: 650;
            }
            p {
                margin: 0;
                color: #5c6158;
                line-height: 1.5;
            }
            a {
                display: inline-block;
                margin-top: 1.5rem;
                color: #1c1c1c;
            }
        </style>
    </head>
    <body>
        <main>
            <img src="/images/soscom-sms-icon.png" alt="Soscom SMS">
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <a href="{{ url('/dashboard') }}">Back to dashboard</a>
        </main>
    </body>
</html>
