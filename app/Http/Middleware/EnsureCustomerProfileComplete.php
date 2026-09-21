<?php

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerProfileComplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return $next($request);
        }

        $user->loadMissing('customer');

        if ($user->customer === null || $user->customer->status === CustomerStatus::PendingProfile) {
            return response()->json([
                'message' => 'Complete your business name and Tanzanian phone number before using this feature.',
            ], 403);
        }

        return $next($request);
    }
}
