<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\CreditTransactionType;
use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;

class AdminMetrics
{
    /**
     * @return array{
     *     customers: int,
     *     smsTraffic: int,
     *     smsThisMonth: int,
     *     failedDeliveries: int,
     *     purchaseRevenueTzs: int,
     *     billedUsageTzs: int,
     *     providerCostTzs: int,
     *     estimatedMarginTzs: int
     * }
     */
    public function snapshot(): array
    {
        if (app()->runningUnitTests()) {
            return $this->compute();
        }

        return Cache::remember('admin:metrics', 45, fn (): array => $this->compute());
    }

    public function forget(): void
    {
        Cache::forget('admin:metrics');
    }

    /**
     * @return array{
     *     customers: int,
     *     smsTraffic: int,
     *     smsThisMonth: int,
     *     failedDeliveries: int,
     *     purchaseRevenueTzs: int,
     *     billedUsageTzs: int,
     *     providerCostTzs: int,
     *     estimatedMarginTzs: int
     * }
     */
    private function compute(): array
    {
        $trafficQuery = Campaign::query()->whereNotIn('status', [
            CampaignStatus::Draft->value,
            CampaignStatus::Cancelled->value,
        ]);

        $smsTraffic = (int) (clone $trafficQuery)->sum('total_segments');
        $smsThisMonth = (int) (clone $trafficQuery)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_segments');
        $failedDeliveries = (int) Campaign::query()->sum('failed_count');
        $purchaseRevenueTzs = (int) CreditTransaction::query()
            ->revenue()
            ->sum('amount_tzs');
        $billedUsageTzs = (int) CreditTransaction::query()
            ->where('type', CreditTransactionType::Usage)
            ->sum('amount_tzs');
        $providerCostTzs = (int) CreditTransaction::query()
            ->where('type', CreditTransactionType::Usage)
            ->selectRaw('COALESCE(SUM(ABS(quantity) * COALESCE(provider_unit_cost, 0)), 0) as cost')
            ->value('cost');

        return [
            'customers' => Customer::query()->count(),
            'smsTraffic' => $smsTraffic,
            'smsThisMonth' => $smsThisMonth,
            'failedDeliveries' => $failedDeliveries,
            'purchaseRevenueTzs' => $purchaseRevenueTzs,
            'billedUsageTzs' => $billedUsageTzs,
            'providerCostTzs' => $providerCostTzs,
            'estimatedMarginTzs' => $billedUsageTzs - $providerCostTzs,
        ];
    }
}
