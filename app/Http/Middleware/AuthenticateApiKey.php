<?php

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Customer;
use App\Services\ApiKeyService;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private ApiKeyService $keys) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $requestId = ApiError::requestId($request);
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
            $request->attributes->set('api_request_id', $requestId);
        }

        $plaintext = $this->extractToken($request);
        $key = $plaintext === null ? null : $this->keys->findByPlaintext($plaintext);

        if ($key === null || $key->isRevoked()) {
            $response = $this->deny('Missing or invalid API key.', 'invalid_api_key', 401);
            $this->writeLog($request, $key, $response->getStatusCode(), 'Missing or invalid API key.');
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        }

        $customer = $key->customer;
        if ($customer !== null && $customer->isSuspended()) {
            $response = $this->deny(
                'This account is suspended. API sending is disabled.',
                'account_suspended',
                403,
            );
            $this->writeLog($request, $key, $response->getStatusCode(), 'Account is suspended.');
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        }

        if ($customer === null || $customer->status !== CustomerStatus::Active) {
            $response = $this->deny(
                'This account cannot use the API until the business profile is complete and active.',
                'account_inactive',
                403,
            );
            $this->writeLog($request, $key, $response->getStatusCode(), 'Account is not active.');
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_customer', $customer);
        $this->keys->touchLastUsed($key);

        $response = $next($request);

        $error = $response->getStatusCode() >= 400
            ? $this->errorFromResponse($response)
            : null;
        $this->writeLog($request, $key, $response->getStatusCode(), $error);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public static function logUnhandled(Request $request, Response $response): void
    {
        if ($request->attributes->get('api_logged')) {
            return;
        }

        $key = $request->attributes->get('api_key');
        if (! $key instanceof ApiKey) {
            return;
        }

        $instance = app(self::class);
        $error = $response->getStatusCode() >= 400
            ? $instance->errorFromResponse($response)
            : null;
        $instance->writeLog($request, $key, $response->getStatusCode(), $error);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            return $token !== '' ? $token : null;
        }

        $apiKey = $request->header('X-Api-Key');
        if (is_string($apiKey) && trim($apiKey) !== '') {
            return trim($apiKey);
        }

        return null;
    }

    private function deny(string $message, string $code, int $status): Response
    {
        return ApiError::json($message, $status, $code);
    }

    private function writeLog(Request $request, ?ApiKey $key, int $status, ?string $error): void
    {
        if ($request->attributes->get('api_logged')) {
            return;
        }

        $customer = $request->attributes->get('api_customer');
        $campaignId = $request->attributes->get('api_campaign_id');

        ApiLog::query()->create([
            'api_key_id' => $key?->id,
            'customer_id' => $customer instanceof Customer ? $customer->id : $key?->customer_id,
            'campaign_id' => is_int($campaignId) ? $campaignId : null,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status_code' => $status,
            'ip' => $request->ip(),
            'request_id' => (string) ($request->attributes->get('api_request_id') ?: $request->attributes->get('request_id')),
            'error' => $error === null || $error === '' ? null : Str::limit($error, 240),
            'created_at' => now(),
        ]);

        $request->attributes->set('api_logged', true);
    }

    private function errorFromResponse(Response $response): ?string
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        $message = $decoded['message'] ?? null;

        return is_string($message) ? $message : null;
    }
}
