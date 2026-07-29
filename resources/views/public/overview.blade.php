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
            --shadow: 0 1px 2px rgb(0 0 0 / 5%), 0 14px 34px -14px rgb(0 0 0 / 14%);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                /* Markenrot bleibt auch im Dunkelmodus #bb0000 (Vorgabe des
                   Corporate Designs). Es erscheint nur an großflächigen
                   Elementen - Zahl in 3rem aufwärts und Schaltfläche mit
                   weißer Schrift -, wo der Kontrast dafür ausreicht. */
                --brand: #bb0000;
                --brand-hover: #d40000;
                --ink: #f4f4f5;
                --ink-soft: #a1a1a6;
                --surface: #161618;
                --canvas: #0b0b0c;
                --line: #2a2a2e;
                --shadow: 0 1px 2px rgb(0 0 0 / 40%), 0 14px 34px -14px rgb(0 0 0 / 60%);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--canvas);
            color: var(--ink);
            font-family: 'Inter Variable', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            font-feature-settings: 'cv02', 'cv03', 'cv04';
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            width: 100%;
            max-width: 58rem;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Kopfzeile mit Wortmarke */
        .masthead {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mark {
            display: block;
            width: auto;
            height: 2.75rem;
        }

        .wordmark {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -0.015em;
        }

        .wordmark span {
            color: var(--ink-soft);
            font-weight: 500;
        }

        main { flex: 1; }

        /* Titelbereich */
        .hero { margin: clamp(2.75rem, 9vw, 5rem) 0 clamp(2rem, 5vw, 2.75rem); }

        .hero h1 {
            margin: 0;
            font-size: clamp(2.25rem, 6vw, 3.75rem);
            font-weight: 700;
            letter-spacing: -0.035em;
            line-height: 1.05;
        }

        .hero h1::after {
            content: '';
            display: block;
            width: 3.5rem;
            height: 0.25rem;
            margin-top: 1.5rem;
            background: var(--brand);
            border-radius: 999px;
        }

        .hero p {
            max-width: 34rem;
            margin: 1.5rem 0 0;
            font-size: clamp(1rem, 2.2vw, 1.125rem);
            line-height: 1.65;
            color: var(--ink-soft);
        }

        /* Kennzahlen */
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            padding: clamp(1.5rem, 4vw, 2.25rem);
        }

        .metric-label {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .metric-value {
            margin: 0.5rem 0 0;
            font-size: clamp(3rem, 11vw, 4.5rem);
            font-weight: 700;
            letter-spacing: -0.04em;
            line-height: 1;
            color: var(--brand);
            font-variant-numeric: tabular-nums;
        }

        .divider {
            height: 1px;
            margin: 1.75rem 0;
            background: var(--line);
            border: 0;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0.875rem 0 0;
            padding: 0;
            list-style: none;
        }

        .chips li {
            padding: 0.4375rem 0.875rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--canvas);
            font-size: 0.9375rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .empty {
            margin: 0.875rem 0 0;
            color: var(--ink-soft);
            font-size: 0.9375rem;
        }

        /* Anmeldung */
        .cta { margin-top: 2.5rem; }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8125rem 1.625rem;
            background: var(--brand);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 0.5rem;
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

        .cta-note {
            margin: 0.875rem 0 0;
            font-size: 0.875rem;
            color: var(--ink-soft);
        }

        footer {
            border-top: 1px solid var(--line);
            font-size: 0.8125rem;
            color: var(--ink-soft);
        }

        footer .shell { padding-block: 1.5rem; }

        @media (prefers-reduced-motion: reduce) {
            .button { transition: none; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="masthead">
            <img class="mark" src="{{ asset('images/a3b-logo.svg') }}" alt="Agentur3B" width="56" height="60">
            <span class="wordmark">3B <span>Fuhrparkmanagement</span></span>
        </header>

        <main>
            <div class="hero">
                <h1>Der Fuhrpark<br>auf einen Blick.</h1>
                <p>
                    Fahrzeuge, Zuordnungen, Leasingzeiträume und Kosten &ndash; zentral
                    dokumentiert statt über Tabellen verteilt.
                </p>
            </div>

            <section class="panel" aria-labelledby="metric-heading">
                <p class="metric-label" id="metric-heading">Aktive Fahrzeuge</p>
                <p class="metric-value">{{ $activeVehicleCount }}</p>

                <hr class="divider">

                <p class="metric-label">Aufteilung nach Antriebsart</p>

                @if ($fuelTypeCounts->isEmpty())
                    <p class="empty">Noch keine Fahrzeuge erfasst</p>
                @else
                    <ul class="chips">
                        @foreach ($fuelTypeCounts as $fuelType => $total)
                            <li>{{ $total }} {{ \App\Enums\FuelType::from($fuelType)->getLabel() }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <div class="cta">
                <a class="button" href="{{ url('/admin/login') }}">
                    Anmelden
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 4.21a1 1 0 0 1 1.415 0l5.083 5.083a1 1 0 0 1 0 1.414L8.625 15.79a1 1 0 1 1-1.415-1.414L11.586 10 7.21 5.624a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </a>
                <p class="cta-note">Zugang nur für Mitarbeitende der Agentur3B.</p>
            </div>
        </main>
    </div>

    <footer>
        <div class="shell">Interne Anwendung der Agentur3B</div>
    </footer>
</body>
</html>
