<?php

declare(strict_types=1);

use App\Exceptions\FoundryException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn () => null);

        $isDiagramImport = fn ($request): bool => $request->is('api/diagrams/sql/import/*');
        $middleware->trimStrings(except: [$isDiagramImport]);
        $middleware->convertEmptyStringsToNull(except: [$isDiagramImport]);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'track.seen' => TrackLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (FoundryException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $exception->toResponse();
            }

            return null;
        });
    })->create();
