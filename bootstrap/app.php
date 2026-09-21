<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureCustomerProfileComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ShareCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trusted === '*') {
            $middleware->trustProxies(at: '*');
        } elseif ($trusted !== '') {
            $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', $trusted)))));
        }
        $middleware->redirectGuestsTo('/sign-in');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->web(append: [
            ShareCsrfToken::class,
        ]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'profile.complete' => EnsureCustomerProfileComplete::class,
            'api.key' => AuthenticateApiKey::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/provider/delivery-reports',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
