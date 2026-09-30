<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;

class PricingTierController extends Controller
{
    public function index(PricingService $pricing): JsonResponse
    {
        return response()->json([
            'tiers' => $pricing->tiers()->map->toApiArray()->values(),
            'packages' => config('sms.packages'),
            'minPurchaseQuantity' => (int) config('sms.min_purchase_quantity', 100),
            'welcomeCredits' => (int) config('sms.welcome_credits', 20),
        ]);
    }
}
