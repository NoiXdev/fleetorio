<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>3B Fuhrparkmanagement</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/a3b-logo.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/a3b-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/a3b-logo.png') }}">

    {{-- Inter liegt lokal in den Filament-Assets. Bewusst keine externe Schriftquelle:
         die Seite soll ohne Verbindung nach draußen laden. Die Hausschrift Klint ist
         kommerziell lizenziert und steht hier nicht zur Verfügung. --}}
    <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">

    <style>
        /* Corporate Design Agentur3B: Rot #bb0000 auf Schwarz und Weiß. */
        :root {
            color-scheme: light dark;

            --brand: #bb0000;
            --brand-hover: #950000;
            --ink: #101010;
            --ink-soft: #5a5a5a;
            --surface: #ffffff;
            --canvas: #f2f2f3;
            --line: #e3e3e5;
            --shadow: 0 1px 2px rgb(0 0 0 / 5%), 0 20px 48px -20px rgb(0 0 0 / 18%);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --brand: #bb0000;
                --brand-hover: #d40000;
                --ink: #f4f4f5;
                --ink-soft: #a1a1a6;
                --surface: #161618;
                --canvas: #0b0b0c;
                --line: #2a2a2e;
                --shadow: 0 1px 2px rgb(0 0 0 / 40%), 0 20px 48px -20px rgb(0 0 0 / 70%);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: var(--canvas);
            color: var(--ink);
            font-family: 'Inter Variable', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Der rote Markenbalken oben ist das einzige Zierelement. */
        .card {
            position: relative;
            width: 100%;
            max-width: 26rem;
            padding: clamp(2.25rem, 6vw, 3rem) clamp(1.75rem, 5vw, 2.5rem);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1.25rem;
            box-shadow: var(--shadow);
            text-align: center;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 0.25rem;
            background: var(--brand);
        }

        .logo {
            display: block;
            width: auto;
            height: 3.5rem;
            margin: 0 auto 1.75rem;
        }

        h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.25;
        }

        h1 span {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--brand);
            margin-bottom: 0.5rem;
        }

        p {
            margin: 0.875rem 0 0;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--ink-soft);
        }

        .button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-top: 2rem;
            padding: 0.875rem 1.5rem;
            background: var(--brand);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 0.625rem;
            transition: background-color 120ms ease, transform 120ms ease;
        }

        .button:hover { background: var(--brand-hover); }
        .button:active { transform: translateY(1px); }

        .button:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 3px;
        }

        .button svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .note {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            font-size: 0.8125rem;
            color: var(--ink-soft);
        }

        @media (prefers-reduced-motion: reduce) {
            .button { transition: none; }
        }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('images/a3b-logo.svg') }}" alt="Agentur3B" width="56" height="60">

        <h1>
            <span>Agentur3B</span>
            Fuhrparkmanagement
        </h1>

        <p>Fahrzeuge, Leasingzeiträume und weitere Infos &ndash; einfach zentral dokumentiert.</p>

        <a class="button" href="{{ url('/admin/login') }}">
            Anmelden
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M7.21 4.21a1 1 0 0 1 1.415 0l5.083 5.083a1 1 0 0 1 0 1.414L8.625 15.79a1 1 0 1 1-1.415-1.414L11.586 10 7.21 5.624a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
            </svg>
        </a>

        <p class="note">Interne Anwendung</p>
    </main>
</body>
</html>
