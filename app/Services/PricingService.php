<?php

namespace App\Services;

use App\Models\PricingTier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PricingService
{
    /**
     * @return Collection<int, PricingTier>
     */
    public function tiers(): Collection
    {
        $loader = fn (): Collection => PricingTier::query()
            ->orderBy('sort_order')
            ->orderBy('min_quantity')
            ->get();

        if (app()->runningUnitTests()) {
            return $loader();
        }

        /** @var Collection<int, PricingTier> $tiers */
        $tiers = Cache::remember('pricing-tiers', 300, $loader);

        return $tiers;
    }

    public function forget(): void
    {
        Cache::forget('pricing-tiers');
    }

    public function tierFor(int $quantity): PricingTier
    {
        $minimum = (int) config('sms.min_purchase_quantity', 50);

        if ($quantity < $minimum) {
            throw ValidationException::withMessages([
                'quantity' => "Buy at least {$minimum} SMS.",
            ]);
        }

        $tier = PricingTier::query()->matchingVolume($quantity)->first();

        if ($tier === null) {
            $tier = $this->tiers()->first();
        }

        if ($tier === null) {
            throw ValidationException::withMessages([
                'quantity' => 'No price is configured for that volume. Contact Soscom.',
            ]);
        }

        return $tier;
    }

    public function firstRangeUnitPriceTzs(): int
    {
        return (int) ($this->tiers()->first()?->unit_price_tzs ?: 25);
    }

    public function tierForVolume(int $quantity): PricingTier
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter at least 1 SMS.',
            ]);
        }

        $tier = PricingTier::query()->matchingVolume($quantity)->first();

        if ($tier === null) {
            $tier = $this->tiers()->first();
        }

        if ($tier === null) {
            throw ValidationException::withMessages([
                'quantity' => 'No price is configured for that volume. Contact Soscom.',
            ]);
        }

        return $tier;
    }

    /**
     * @return array{quantity: int, unitPriceTzs: int, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    public function quote(int $quantity): array
    {
        return $this->quoteFromTier($quantity, $this->tierFor($quantity));
    }

    /**
     * Price any volume, including grants below the public purchase minimum.
     *
     * @return array{quantity: int, unitPriceTzs: int, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    public function quoteVolume(int $quantity): array
    {
        return $this->quoteFromTier($quantity, $this->tierForVolume($quantity));
    }

    /**
     * @return array{quantity: int, unitPriceTzs: int, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    private function quoteFromTier(int $quantity, PricingTier $tier): array
    {
        $providerCost = (int) config('sms.provider_unit_cost_tzs', 14);
        $total = $quantity * $tier->unit_price_tzs;

        return [
            'quantity' => $quantity,
            'unitPriceTzs' => $tier->unit_price_tzs,
            'totalTzs' => $total,
            'providerUnitCostTzs' => $providerCost,
            'estimatedMarginTzs' => ($tier->unit_price_tzs - $providerCost) * $quantity,
            'tier' => $tier->toApiArray(),
        ];
    }
}
