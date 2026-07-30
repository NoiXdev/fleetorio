<?php $brandName = \App\Models\AppSetting::name(); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $brandName }}</title>

    <link rel="icon" href="{{ \App\Models\AppSetting::logoUrl() }}">
    <link rel="apple-touch-icon" href="{{ \App\Models\AppSetting::logoUrl() }}">

    {{-- Inter liegt lokal in den Filament-Assets. Bewusst keine externe Schriftquelle:
         die Seite soll ohne Verbindung nach draußen laden. Die Hausschrift Klint ist
         kommerziell lizenziert und steht hier nicht zur Verfügung. --}}
    <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">

    @include('public._card-styles')
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ \App\Models\AppSetting::logoUrl() }}" alt="{{ $brandName }}" width="56" height="60">

        <h1>{{ $brandName }}</h1>

        <p>Fahrzeuge, Leasingzeiträume und weitere Infos &ndash; einfach zentral dokumentiert.</p>

        @if($hasUsers)
            <a class="button" href="{{ url('/admin/login') }}">
                Anmelden
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 4.21a1 1 0 0 1 1.415 0l5.083 5.083a1 1 0 0 1 0 1.414L8.625 15.79a1 1 0 1 1-1.415-1.414L11.586 10 7.21 5.624a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                </svg>
            </a>
        @else
            <p>Es besteht noch kein Zugang zu dieser Anwendung.</p>

            <a class="button" href="{{ route('setup.show') }}">
                Einrichtung starten
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 4.21a1 1 0 0 1 1.415 0l5.083 5.083a1 1 0 0 1 0 1.414L8.625 15.79a1 1 0 1 1-1.415-1.414L11.586 10 7.21 5.624a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                </svg>
            </a>
        @endif

        <p class="note">Interne Anwendung</p>
    </main>
</body>
</html>
