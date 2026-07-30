<?php $brandName = \App\Models\AppSetting::name(); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Ersteinrichtung &ndash; {{ $brandName }}</title>

    <link rel="icon" href="{{ \App\Models\AppSetting::logoUrl() }}">
    <link rel="apple-touch-icon" href="{{ \App\Models\AppSetting::logoUrl() }}">

    <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">

    @include('public._card-styles')
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ \App\Models\AppSetting::logoUrl() }}" alt="{{ $brandName }}" width="56" height="60">

        <h1>
            <span>Ersteinrichtung</span>
            {{ $brandName }}
        </h1>

        <p>Es besteht noch kein Zugang. Lege hier den ersten Administrator an.</p>

        @if ($errors->any())
            <div class="errors">
                Bitte prüfe folgende Angaben:
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('setup.store') }}" novalidate>
            @csrf

            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
            </div>

            <div class="field">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>

            <div class="field">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>

            <div class="field">
                <label for="password_confirmation">Passwort bestätigen</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>

            <button type="submit" class="button">
                Konto anlegen
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 4.21a1 1 0 0 1 1.415 0l5.083 5.083a1 1 0 0 1 0 1.414L8.625 15.79a1 1 0 1 1-1.415-1.414L11.586 10 7.21 5.624a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                </svg>
            </button>
        </form>

        <p class="note">Diese Seite verschwindet automatisch, sobald der Benutzer angelegt ist.</p>
    </main>
</body>
</html>
