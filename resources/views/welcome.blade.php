@php
    $appName = str(config('app.name'))
        ->lower()
        ->toString();
    $appEnvironment = app()->environment();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $appName }}</title>

    <x-theme />
    <style>
        html {
            font-family: var(--theme-font-sans);
            box-sizing: border-box;

            scroll-behavior: smooth;
            scrollbar-width: thin;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--theme-paper);
            color: var(--theme-ink);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6, p {
            font-weight: normal;
        }

        section {
            margin: 0 !important;
            padding-top: 1em;
            padding-bottom: 1em;
        }

        code {
            color: var(--theme-code);
            background-color: var(--theme-code-background);
            padding: 2px 5px;
            border-radius: 6px;
            font-family: var(--theme-font-mono);
        }

        blockquote {
            padding: 10px 16px;
            font-weight: bold;
        }

        #full-page-hero {
            background-size: cover;
            background: radial-gradient(var(--theme-primary), var(--theme-ink)) no-repeat center;
            color: var(--theme-paper);

            min-height: 100vh;
            margin: 0;

            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        #full-page-hero h1 {
            margin: 0;
            font-size: 6em;
        }

        #app-info {
            display: block;
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1;
            text-align: center;

            background-color: var(--theme-ink);
            color: var(--theme-paper);
        }

        main {
            max-width: 48rem;
            margin: 0 auto;
            padding: 2em 1.5em;
        }
    </style>
</head>
<body>
    <section id="full-page-hero">
        <h1>{{ $appName }}</h1>
    </section>

    <section id="app-info">
        <p>Currently running in the <code>{{ $appEnvironment }}</code> environment.</p>
    </section>

    <main>
        <section>
            <p>Perfect place to put a nice welcome letter for developers who stumble upon your API's root page!</p>
        </section>
    </main>
</body>
</html>
