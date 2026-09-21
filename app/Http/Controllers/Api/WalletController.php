<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\User;
use App\Services\PricingService;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, PricingService $pricing): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            return response()->json([
                'message' => 'Admin accounts do not have an SMS wallet.',
            ], 404);
        }

        $tiers = $pricing->tiers()->map->toApiArray()->values();
        $packages = collect(config('sms.packages', []))
            ->map(function (int $quantity) use ($pricing): array {
                $quote = $pricing->quote($quantity);

                return [
                    'quantity' => $quantity,
                    'unitPriceTzs' => $quote['unitPriceTzs'],
                    'totalTzs' => $quote['totalTzs'],
                    'popular' => $quantity === 10000,
                ];
            })
            ->values();

        $giftUnitPrice = $pricing->firstRangeUnitPriceTzs();
        $recent = $this->purchaseTransactions($customer)
            ->with('reversal')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($transaction) => $this->purchaseToApiArray($transaction, $giftUnitPrice))
            ->values();

        return response()->json([
            'smsBalance' => $customer->sms_balance,
            'welcomeCredits' => (int) config('sms.welcome_credits', 20),
            'lipaNamba' => (string) config('sms.billing.lipa_namba', '123456'),
            'lipaNambaName' => (string) config(
                'sms.billing.lipa_namba_name',
                'SOSCOM TECHNOLOGIES COMPANY LIMITED',
            ),
            'minPurchaseQuantity' => (int) config('sms.min_purchase_quantity', 50),
            'tiers' => $tiers,
            'packages' => $packages,
            'recentTransactions' => $recent,
        ]);
    }

    public function transactions(Request $request, PricingService $pricing): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            return response()->json([
                'message' => 'Admin accounts do not have an SMS wallet.',
            ], 404);
        }

        $giftUnitPrice = $pricing->firstRangeUnitPriceTzs();
        $page = $this->purchaseTransactions($customer)
            ->with('reversal')
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'smsBalance' => $customer->sms_balance,
            'transactions' => $page->getCollection()
                ->map(fn ($transaction) => $this->purchaseToApiArray($transaction, $giftUnitPrice))
                ->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }

    /**
     * @return HasMany<CreditTransaction, Customer>
     */
    private function purchaseTransactions(Customer $customer): HasMany
    {
        return $customer->creditTransactions()->purchasesForCustomer();
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseToApiArray(CreditTransaction $transaction, int $giftUnitPrice): array
    {
        $row = $transaction->toApiArray();

        if ($transaction->isWelcomeGrant() && (int) $transaction->amount_tzs === 0) {
            $row['amountTzs'] = abs((int) $transaction->quantity) * $giftUnitPrice;
        }

        return $row;
    }
}
