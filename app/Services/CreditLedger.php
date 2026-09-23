<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreditLedger
{
    public function grantWelcome(Customer $customer): CreditTransaction
    {
        $quantity = (int) config('sms.welcome_credits', 20);
        $reference = $this->welcomeReference($customer);
        $unitPrice = app(PricingService::class)->firstRangeUnitPriceTzs();

        $existing = CreditTransaction::query()
            ->where('reference', $reference)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->post(
            customer: $customer,
            type: CreditTransactionType::Grant,
            quantity: $quantity,
            description: "Signup bonus — {$quantity} free SMS",
            reference: $reference,
            customerUnitPrice: $unitPrice,
            providerUnitCost: 0,
        );
    }

    public function grant(
        Customer $customer,
        int $quantity,
        ?string $note = null,
        ?int $amountTzs = null,
        ?int $unitPriceTzs = null,
    ): CreditTransaction {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Grant at least 1 SMS.',
            ]);
        }

        if ($unitPriceTzs !== null && $unitPriceTzs < 0) {
            throw ValidationException::withMessages([
                'unit_price_tzs' => 'Unit price cannot be negative.',
            ]);
        }

        if ($amountTzs !== null && $amountTzs < 0) {
            throw ValidationException::withMessages([
                'amount_tzs' => 'Amount cannot be negative.',
            ]);
        }

        $quote = app(PricingService::class)->quoteVolume($quantity);
        $unitPrice = $unitPriceTzs ?? $quote['unitPriceTzs'];
        $amount = $amountTzs ?? ($quantity * $unitPrice);

        if ($unitPriceTzs === null && $amountTzs !== null) {
            $unitPrice = intdiv($amount, $quantity);
        }

        return $this->post(
            customer: $customer,
            type: CreditTransactionType::Grant,
            quantity: $quantity,
            description: $note ?: "Admin grant of {$quantity} SMS",
            customerUnitPrice: $unitPrice,
            providerUnitCost: 0,
            amountTzs: $amount,
        );
    }

    public function debitUsage(
        Customer $customer,
        int $quantity,
        string $description,
        ?string $reference = null,
    ): CreditTransaction {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Usage must deduct at least 1 SMS.',
            ]);
        }

        if ($reference !== null) {
            $existing = CreditTransaction::query()
                ->where('reference', $reference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->post(
            customer: $customer,
            type: CreditTransactionType::Usage,
            quantity: -$quantity,
            description: $description,
            reference: $reference,
            customerUnitPrice: (int) config('sms.send_unit_price_tzs', 30),
            providerUnitCost: (int) config('sms.provider_unit_cost_tzs', 14),
        );
    }

    /**
     * Atomically reserve campaign credits up front (hard debit).
     * Idempotent on USE-CAMP-{id}.
     */
    public function reserveCampaign(Customer $customer, int $campaignId, int $quantity, string $campaignName): CreditTransaction
    {
        return $this->debitUsage(
            $customer,
            $quantity,
            "Reserved for campaign {$campaignName}",
            'USE-CAMP-'.$campaignId,
        );
    }

    public function purchase(
        Customer $customer,
        int $quantity,
        int $unitPrice,
        string $reference,
        string $description,
    ): CreditTransaction {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Purchase at least 1 SMS.',
            ]);
        }

        $existing = CreditTransaction::query()
            ->where('reference', $reference)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->post(
            customer: $customer,
            type: CreditTransactionType::Purchase,
            quantity: $quantity,
            description: $description,
            reference: $reference,
            customerUnitPrice: $unitPrice,
            providerUnitCost: (int) config('sms.provider_unit_cost_tzs', 14),
        );
    }

    public function refundUsage(
        Customer $customer,
        int $quantity,
        string $description,
        ?string $reference = null,
    ): CreditTransaction {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Refund at least 1 SMS.',
            ]);
        }

        if ($reference !== null) {
            $existing = CreditTransaction::query()
                ->where('reference', $reference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->post(
            customer: $customer,
            type: CreditTransactionType::Refund,
            quantity: $quantity,
            description: $description,
            reference: $reference,
            customerUnitPrice: (int) config('sms.send_unit_price_tzs', 30),
            providerUnitCost: (int) config('sms.provider_unit_cost_tzs', 14),
        );
    }

    public function reverse(CreditTransaction $original): CreditTransaction
    {
        if ($original->type === CreditTransactionType::Reversal) {
            throw ValidationException::withMessages([
                'transaction' => 'A reversal cannot be reversed.',
            ]);
        }

        if ($original->isReversed()) {
            throw ValidationException::withMessages([
                'transaction' => 'This transaction has already been reversed.',
            ]);
        }

        return $this->post(
            customer: $original->customer,
            type: CreditTransactionType::Reversal,
            quantity: -$original->quantity,
            description: "Reversal of {$original->reference}",
            reference: 'REV-'.$original->reference,
            customerUnitPrice: $original->customer_unit_price,
            providerUnitCost: $original->provider_unit_cost,
            relatedTransactionId: $original->id,
        );
    }

    public function post(
        Customer $customer,
        CreditTransactionType $type,
        int $quantity,
        string $description,
        ?string $reference = null,
        ?int $customerUnitPrice = null,
        ?int $providerUnitCost = null,
        ?int $relatedTransactionId = null,
        ?int $amountTzs = null,
    ): CreditTransaction {
        if ($quantity === 0) {
            throw new InvalidArgumentException('Ledger quantity cannot be zero.');
        }

        return DB::transaction(function () use (
            $customer,
            $type,
            $quantity,
            $description,
            $reference,
            $customerUnitPrice,
            $providerUnitCost,
            $relatedTransactionId,
            $amountTzs,
        ): CreditTransaction {
            /** @var Customer $locked */
            $locked = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = $locked->sms_balance + $quantity;

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough SMS credits for this transaction.',
                ]);
            }

            $transaction = CreditTransaction::query()->create([
                'customer_id' => $locked->id,
                'type' => $type,
                'quantity' => $quantity,
                'customer_unit_price' => $customerUnitPrice,
                'provider_unit_cost' => $providerUnitCost,
                'amount_tzs' => $amountTzs ?? ($customerUnitPrice === null
                    ? null
                    : abs($quantity) * $customerUnitPrice),
                'balance_after' => $balanceAfter,
                'reference' => $reference ?? $this->newReference($type),
                'description' => $description,
                'related_transaction_id' => $relatedTransactionId,
                'created_at' => now(),
            ]);

            $locked->sms_balance = $balanceAfter;
            $locked->save();

            return $transaction;
        });
    }

    public function welcomeReference(Customer $customer): string
    {
        return 'WELCOME-'.$customer->id;
    }

    private function newReference(CreditTransactionType $type): string
    {
        $prefix = match ($type) {
            CreditTransactionType::Purchase => 'BUY',
            CreditTransactionType::Usage => 'USE',
            CreditTransactionType::Refund => 'RFD',
            CreditTransactionType::Grant => 'GNT',
            CreditTransactionType::Reversal => 'REV',
        };

        return $prefix.'-'.strtoupper((string) Str::ulid());
    }
}
