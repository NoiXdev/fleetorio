<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hinter einem Reverse-Proxy (Traefik, nginx, Load Balancer) kommt die
        // Anfrage bei Laravel als http an, auch wenn der Nutzer https benutzt.
        // Ohne Vertrauen in die X-Forwarded-Header erzeugt Laravel dann
        // http-Links, und Filaments Livewire-Anfragen laufen ins Leere.
        //
        // Bewusst über die Umgebung gesteuert und standardmäßig AUS: Wer die
        // Header ungeprüft vertraut, während der Container direkt aus dem
        // Internet erreichbar ist, lässt sich die Client-IP und das Protokoll
        // fälschen. Nur setzen, wenn tatsächlich ein Proxy davor steht.
        //
        //   TRUSTED_PROXIES=*              alle (nur wenn der Container nicht
        //                                  direkt erreichbar ist)
        //   TRUSTED_PROXIES=10.0.0.1,...   besser: die konkreten Adressen
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
