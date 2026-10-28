<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function store(Request $request, PricingService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'quote' => $pricing->quote((int) $validated['quantity'], $user->customer),
        ]);
    }
}
