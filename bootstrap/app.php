<?php

use App\Console\Commands\CreateAdmin;
use App\Console\Commands\ImportBrandenburgGeoCore;
use App\Console\Commands\ImportContactSuggestions;
use App\Console\Commands\ImportFacilities;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/up', function (): Response {
                $headers = [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                ];

                try {
                    Event::dispatch(new DiagnosingHealth);
                } catch (Throwable $exception) {
                    report($exception);

                    return response('ERROR', 500, $headers);
                }

                return response('OK', 200, $headers);
            });
        },
    )
    ->withCommands([
        CreateAdmin::class,
        ImportContactSuggestions::class,
        ImportBrandenburgGeoCore::class,
        ImportFacilities::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(remove: [
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);
        $middleware->group('admin-session', [
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
