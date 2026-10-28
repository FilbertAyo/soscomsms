<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPrice;
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
     * Public tiers with this customer's applicable unit price, when one is active.
     *
     * @return Collection<int, array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, listUnitPriceTzs: int, specialPrice: bool, label: string}>
     */
    public function customerTiers(?Customer $customer): Collection
    {
        $prices = $this->activePrices($customer);

        return $this->tiers()->map(function (PricingTier $tier) use ($prices): array {
            $row = $tier->toApiArray();
            $list = $tier->unit_price_tzs;
            $override = $prices->get($tier->id);
            $unit = $override instanceof CustomerPrice ? $override->unit_price_tzs : $list;
            $row['unitPriceTzs'] = $unit;
            $row['listUnitPriceTzs'] = $list;
            $row['specialPrice'] = $unit !== $list;

            return $row;
        })->values();
    }

    /**
     * @return array{quantity: int, unitPriceTzs: int, listUnitPriceTzs: int, specialPrice: bool, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    public function quote(int $quantity, ?Customer $customer = null): array
    {
        return $this->quoteFromTier($quantity, $this->tierFor($quantity), $customer);
    }

    /**
     * Price any volume, including grants below the public purchase minimum.
     *
     * @return array{quantity: int, unitPriceTzs: int, listUnitPriceTzs: int, specialPrice: bool, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    public function quoteVolume(int $quantity): array
    {
        return $this->quoteFromTier($quantity, $this->tierForVolume($quantity));
    }

    /**
     * @return Collection<int, CustomerPrice>
     */
    private function activePrices(?Customer $customer): Collection
    {
        if ($customer === null) {
            return collect();
        }

        return CustomerPrice::query()
            ->where('customer_id', $customer->id)
            ->applicable()
            ->get()
            ->keyBy('pricing_tier_id');
    }

    private function unitPriceFor(PricingTier $tier, ?Customer $customer): int
    {
        if ($customer !== null) {
            $override = CustomerPrice::query()
                ->where('customer_id', $customer->id)
                ->where('pricing_tier_id', $tier->id)
                ->applicable()
                ->value('unit_price_tzs');

            if ($override !== null) {
                return (int) $override;
            }
        }

        return $tier->unit_price_tzs;
    }

    /**
     * @return array{quantity: int, unitPriceTzs: int, listUnitPriceTzs: int, specialPrice: bool, totalTzs: int, providerUnitCostTzs: int, estimatedMarginTzs: int, tier: array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}}
     */
    private function quoteFromTier(int $quantity, PricingTier $tier, ?Customer $customer = null): array
    {
        $providerCost = (int) config('sms.provider_unit_cost_tzs', 14);
        $listUnit = $tier->unit_price_tzs;
        $unit = $this->unitPriceFor($tier, $customer);
        $total = $quantity * $unit;

        return [
            'quantity' => $quantity,
            'unitPriceTzs' => $unit,
            'listUnitPriceTzs' => $listUnit,
            'specialPrice' => $unit !== $listUnit,
            'totalTzs' => $total,
            'providerUnitCostTzs' => $providerCost,
            'estimatedMarginTzs' => ($unit - $providerCost) * $quantity,
            'tier' => $tier->toApiArray(),
        ];
    }
}
