<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

final class ApiError
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    public const UNAUTHORIZED = 'UNAUTHORIZED';

    public const FORBIDDEN = 'FORBIDDEN';

    public const NOT_FOUND = 'NOT_FOUND';

    public const CONFLICT = 'CONFLICT';

    public const RATE_LIMITED = 'RATE_LIMITED';

    public const CSRF_MISMATCH = 'CSRF_MISMATCH';

    public const SERVER_ERROR = 'SERVER_ERROR';

    public const SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    public const UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    public static function requestId(?Request $request = null): string
    {
        $request ??= request();
        $id = $request->attributes->get('request_id') ?? $request->attributes->get('api_request_id');

        return is_string($id) && $id !== '' ? $id : '';
    }

    public static function codeForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => self::UNAUTHORIZED,
            $status === 403 => self::FORBIDDEN,
            $status === 404 => self::NOT_FOUND,
            $status === 409 => self::CONFLICT,
            $status === 419 => self::CSRF_MISMATCH,
            $status === 422 => self::VALIDATION_ERROR,
            $status === 429 => self::RATE_LIMITED,
            $status === 503 => self::SERVICE_UNAVAILABLE,
            $status >= 500 => self::SERVER_ERROR,
            default => self::UNKNOWN_ERROR,
        };
    }

    public static function codeFor(Throwable $e, int $status): string
    {
        if ($e instanceof ValidationException) {
            return self::VALIDATION_ERROR;
        }

        if ($e instanceof AuthenticationException) {
            return self::UNAUTHORIZED;
        }

        if ($e instanceof AuthorizationException) {
            return self::FORBIDDEN;
        }

        if ($e instanceof TokenMismatchException) {
            return self::CSRF_MISMATCH;
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return self::RATE_LIMITED;
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::codeForStatus($e->getStatusCode());
        }

        return self::codeForStatus($status);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function payload(string $message, int $status, ?string $code = null, array $extra = []): array
    {
        return array_filter([
            'message' => $message,
            'code' => $code ?? self::codeForStatus($status),
            'request_id' => self::requestId(),
            ...$extra,
        ], fn (mixed $value): bool => $value !== '' && $value !== null);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function json(string $message, int $status, ?string $code = null, array $extra = []): JsonResponse
    {
        $response = response()->json(self::payload($message, $status, $code, $extra), $status);
        $requestId = self::requestId();
        if ($requestId !== '') {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }

    public static function enrich(Response $response, Request $request, ?Throwable $e = null): Response
    {
        $requestId = self::requestId($request);
        if ($requestId !== '') {
            $response->headers->set('X-Request-Id', $requestId);
        }

        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        $data = self::jsonBody($response);
        if ($data === null) {
            return $response;
        }

        if (! isset($data['code']) || ! is_string($data['code']) || $data['code'] === '') {
            $data['code'] = $e instanceof Throwable
                ? self::codeFor($e, $status)
                : self::codeForStatus($status);
        }

        if ($requestId !== '') {
            $data['request_id'] = $requestId;
        }

        if ($status >= 500 && $status !== 503) {
            $data['message'] = 'Something went wrong. Please try again.';
        }

        if ($status === 503 && (! isset($data['message']) || ! is_string($data['message']) || $data['message'] === '')) {
            $data['message'] = 'The service is temporarily unavailable. Please try again.';
        }

        return self::replaceJson($response, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function jsonBody(Response $response): ?array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return is_array($data) && ! array_is_list($data) ? $data : null;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'json')) {
            return null;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function replaceJson(Response $response, array $data): Response
    {
        if ($response instanceof JsonResponse) {
            $response->setData($data);

            return $response;
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $response->setContent($encoded);
        }

        return $response;
    }
}
