<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\PricingTier;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPriceController extends Controller
{
    public function index(Customer $customer, PricingService $pricing): JsonResponse
    {
        $prices = $customer->prices()
            ->whereNull('disabled_at')
            ->get()
            ->keyBy('pricing_tier_id');

        $packages = $pricing->tiers()->map(function (PricingTier $tier) use ($prices): array {
            /** @var CustomerPrice|null $price */
            $price = $prices->get($tier->id);

            return $this->packageArray($tier, $price);
        })->values();

        return response()->json([
            'packages' => $packages,
        ]);
    }

    public function store(Request $request, Customer $customer, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'pricing_tier_id' => ['required', 'integer', 'exists:pricing_tiers,id'],
            'unit_price_tzs' => ['required', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $tier = PricingTier::query()->findOrFail((int) $validated['pricing_tier_id']);
        $expiresAt = $this->expiresAt($validated['expires_at'] ?? null);
        $unitPrice = (int) $validated['unit_price_tzs'];

        $price = DB::transaction(function () use ($customer, $tier, $unitPrice, $expiresAt, $audit, $request): CustomerPrice {
            /** @var CustomerPrice|null $existing */
            $existing = CustomerPrice::query()
                ->where('customer_id', $customer->id)
                ->where('pricing_tier_id', $tier->id)
                ->lockForUpdate()
                ->first();

            $from = $existing !== null && $existing->disabled_at === null
                ? $existing->unit_price_tzs
                : null;

            $price = $existing ?? new CustomerPrice([
                'customer_id' => $customer->id,
                'pricing_tier_id' => $tier->id,
            ]);

            $price->fill([
                'unit_price_tzs' => $unitPrice,
                'expires_at' => $expiresAt,
                'disabled_at' => null,
            ])->save();

            $audit->record(
                actor: $this->admin($request),
                action: AuditAction::CustomerPriceSaved,
                subject: $price,
                customer: $customer,
                payload: [
                    'label' => $tier->label(),
                    'list_unit_price_tzs' => $tier->unit_price_tzs,
                    'from' => $from,
                    'to' => $unitPrice,
                    'expires_at' => $expiresAt?->toIso8601String(),
                ],
            );

            return $price;
        });

        return response()->json([
            'package' => $this->packageArray($tier, $price->fresh() ?? $price),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function destroy(
        Request $request,
        Customer $customer,
        CustomerPrice $customerPrice,
        AuditLogger $audit,
    ): JsonResponse {
        abort_unless($customerPrice->customer_id === $customer->id, 404);

        if ($customerPrice->disabled_at !== null) {
            throw ValidationException::withMessages([
                'price' => 'This special price is already removed.',
            ]);
        }

        $customerPrice->loadMissing('pricingTier');
        $tier = $customerPrice->pricingTier;
        $from = $customerPrice->unit_price_tzs;

        $customerPrice->forceFill([
            'disabled_at' => now(),
        ])->save();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::CustomerPriceRemoved,
            subject: $customerPrice,
            customer: $customer,
            payload: [
                'label' => $tier?->label(),
                'list_unit_price_tzs' => $tier?->unit_price_tzs,
                'from' => $from,
                'to' => null,
            ],
        );

        return response()->json([
            'removed' => true,
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function packageArray(PricingTier $tier, ?CustomerPrice $price): array
    {
        return [
            'tierId' => $tier->id,
            'label' => $tier->label(),
            'minQuantity' => $tier->min_quantity,
            'maxQuantity' => $tier->max_quantity,
            'listUnitPriceTzs' => $tier->unit_price_tzs,
            'price' => $price === null ? null : [
                'id' => $price->id,
                'tierId' => $price->pricing_tier_id,
                'unitPriceTzs' => $price->unit_price_tzs,
                'expiresAt' => $price->expires_at?->toIso8601String(),
                'active' => $price->isApplicable(),
                'updatedAt' => $price->updated_at?->toIso8601String(),
            ],
        ];
    }

    private function expiresAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->endOfDay();
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
