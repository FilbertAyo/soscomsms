<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureCustomerProfileComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ShareCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'api/webhooks/snippe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (
                $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
            ) {
                return null;
            }

            $json = $request->is('api/*') || $request->expectsJson();
            $status = $e instanceof ModelNotFoundException
                ? 404
                : ($e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);

            if ($status < 500 && $status !== 404) {
                if ($json) {
                    return null;
                }

                $view = view()->exists('errors.'.$status) ? 'errors.'.$status : 'errors.500';

                return response()->view($view, [], $status);
            }

            if ($json) {
                return response()->json([
                    'message' => $status === 404
                        ? "We couldn't find that."
                        : 'Something went wrong. Please try again.',
                ], $status === 404 ? 404 : 500);
            }

            return response()->view(
                $status === 404 ? 'errors.404' : 'errors.500',
                [],
                $status === 404 ? 404 : 500,
            );
        });
    })->create();
