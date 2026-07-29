<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fuhrparkmanagement – Übersicht</title>
    <style>
        :root {
            color-scheme: light dark;
            --color-bg: #f4f6f8;
            --color-surface: #ffffff;
            --color-text: #1f2933;
            --color-muted: #52606d;
            --color-primary: #2563eb;
            --color-primary-contrast: #ffffff;
            --color-border: #e2e8f0;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --color-bg: #0f172a;
                --color-surface: #1e293b;
                --color-text: #e2e8f0;
                --color-muted: #94a3b8;
                --color-primary: #3b82f6;
                --color-primary-contrast: #0f172a;
                --color-border: #334155;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--color-bg);
            color: var(--color-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
        }

        main {
            width: 100%;
            max-width: 40rem;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.75rem;
            font-weight: 700;
        }

        p.lead {
            margin: 0 0 2rem;
            color: var(--color-muted);
            font-size: 1.05rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
            gap: 1rem;
            margin-bottom: 2.25rem;
        }

        .stat {
            border: 1px solid var(--color-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .stat-label {
            margin: 0 0 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--color-muted);
        }

        .stat-value {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .stat-value.text {
            font-size: 1.1rem;
            font-weight: 500;
        }

        .cta {
            display: inline-block;
            background: var(--color-primary);
            color: var(--color-primary-contrast);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            padding: 0.85rem 1.75rem;
            border-radius: 0.6rem;
        }

        .cta:hover,
        .cta:focus-visible {
            filter: brightness(1.08);
        }

        @media (max-width: 30rem) {
            main {
                padding: 2rem 1.25rem;
            }

            .cta {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1>Fuhrparkmanagement</h1>
        <p class="lead">Interne Übersicht über den Firmenfuhrpark: Fahrzeuge, Leasingzeiträume und Kosten.</p>

        <div class="stats">
            <div class="stat">
                <p class="stat-label">Aktive Fahrzeuge</p>
                <p class="stat-value">{{ $activeVehicleCount }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Aufteilung nach Antriebsart</p>
                <p class="stat-value text">
                    {{ $fuelTypeBreakdown !== '' ? $fuelTypeBreakdown : 'Noch keine Fahrzeuge erfasst' }}
                </p>
            </div>
        </div>

        <a class="cta" href="/admin/login">Anmelden</a>
    </main>
</body>
</html>
