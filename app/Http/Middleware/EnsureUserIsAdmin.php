<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            return ApiError::json('This area is for Soscom administrators.', 403);
        }

        return $next($request);
    }
}
