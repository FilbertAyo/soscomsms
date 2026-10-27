<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->incomingId($request);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('api_request_id', $requestId);
        $request->headers->set('X-Request-Id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);

        return ApiError::enrich($response, $request);
    }

    private function incomingId(Request $request): string
    {
        $header = $request->header('X-Request-Id');
        if (is_string($header) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $header) === 1) {
            return $header;
        }

        return (string) Str::uuid();
    }
}
