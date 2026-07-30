{{--
    Gemeinsames CSS fuer die datenfreien, oeffentlichen Karten-Seiten (die
    Startseite und die Ersteinrichtung unter /setup). Eingebettet und ohne
    externe Abhaengigkeiten, damit beide Seiten auch ohne Verbindung nach
    draussen vollstaendig laden. Kein Framework - nur die eine Style-Regel,
    die sonst zweimal wortgleich stuende.
--}}
<style>
    /* Corporate Design Agentur3B: Rot #bb0000 auf Schwarz und Weiss. */
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
        border: none;
        cursor: pointer;
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

    /* Formularfelder der Ersteinrichtung - werden nur auf /setup benoetigt,
       stehen hier trotzdem mit, damit alle Kartenstile an einer Stelle
       bleiben statt auf zwei Dateien verteilt zu sein. */
    .field {
        margin-top: 1.25rem;
        text-align: left;
    }

    .field label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--ink);
    }

    .field input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        font-size: 1rem;
        font-family: inherit;
        color: var(--ink);
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 0.5rem;
    }

    .field input:focus-visible {
        outline: 2px solid var(--brand);
        outline-offset: 1px;
    }

    .field .error {
        margin: 0.375rem 0 0;
        font-size: 0.8125rem;
        color: var(--brand);
    }

    .errors {
        margin: 1.25rem 0 0;
        padding: 0.875rem 1rem;
        text-align: left;
        background: color-mix(in srgb, var(--brand) 8%, var(--surface));
        border: 1px solid var(--brand);
        border-radius: 0.625rem;
        font-size: 0.875rem;
        color: var(--ink);
    }

    .errors ul {
        margin: 0.375rem 0 0;
        padding-left: 1.125rem;
    }

    @media (prefers-reduced-motion: reduce) {
        .button { transition: none; }
    }
</style>
