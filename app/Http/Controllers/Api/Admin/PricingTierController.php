<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\PricingTier;
use App\Models\User;
use App\Services\AdminMetrics;
use App\Services\AuditLogger;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingTierController extends Controller
{
    public function index(PricingService $pricing, AdminMetrics $metrics): JsonResponse
    {
        $snapshot = $metrics->snapshot();

        return response()->json([
            'tiers' => $pricing->tiers()->map->toApiArray()->values(),
            'providerUnitCostTzs' => (int) config('sms.provider_unit_cost_tzs', 14),
            'purchaseRevenueTzs' => $snapshot['purchaseRevenueTzs'],
            'billedUsageTzs' => $snapshot['billedUsageTzs'],
            'providerCostTzs' => $snapshot['providerCostTzs'],
            'estimatedMarginTzs' => $snapshot['estimatedMarginTzs'],
        ]);
    }

    public function update(
        Request $request,
        PricingTier $pricingTier,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'unit_price_tzs' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $from = $pricingTier->unit_price_tzs;
        $pricingTier->update([
            'unit_price_tzs' => $validated['unit_price_tzs'],
        ]);
        app(PricingService::class)->forget();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::PricingUpdated,
            subject: $pricingTier,
            payload: [
                'label' => $pricingTier->label(),
                'from' => $from,
                'to' => (int) $validated['unit_price_tzs'],
            ],
        );

        return response()->json([
            'tier' => $pricingTier->fresh()?->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
