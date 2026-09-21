<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreditTransaction extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'type',
        'quantity',
        'customer_unit_price',
        'provider_unit_cost',
        'amount_tzs',
        'balance_after',
        'reference',
        'description',
        'related_transaction_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'quantity' => 'integer',
            'customer_unit_price' => 'integer',
            'provider_unit_cost' => 'integer',
            'amount_tzs' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CreditTransaction, $this>
     */
    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class, 'related_transaction_id');
    }

    /**
     * @return HasOne<CreditTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(CreditTransaction::class, 'related_transaction_id')
            ->where('type', CreditTransactionType::Reversal->value);
    }

    public function isReversed(): bool
    {
        if ($this->relationLoaded('reversal')) {
            return $this->reversal !== null;
        }

        return $this->reversal()->exists();
    }

    public function isWelcomeGrant(): bool
    {
        return $this->type === CreditTransactionType::Grant
            && str_starts_with((string) $this->reference, 'WELCOME-');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePurchasesForCustomer(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('type', CreditTransactionType::Purchase->value)
                ->orWhere(function (Builder $welcome): void {
                    $welcome->where('type', CreditTransactionType::Grant->value)
                        ->where('reference', 'like', 'WELCOME-%');
                });
        });
    }

    /**
     * Paid purchases plus priced admin grants. Signup bonuses stay out of revenue.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRevenue(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('type', CreditTransactionType::Purchase->value)
                ->orWhere(function (Builder $grants): void {
                    $grants->where('type', CreditTransactionType::Grant->value)
                        ->where('reference', 'not like', 'WELCOME-%');
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing('customer.user');

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'customerUnitPrice' => $this->customer_unit_price,
            'amountTzs' => $this->amount_tzs,
            'balanceAfter' => $this->balance_after,
            'reference' => $this->reference,
            'description' => $this->description,
            'reversed' => $this->isReversed(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->user?->name,
                'email' => $this->customer->user?->email,
                'businessName' => $this->customer->business_name,
            ],
        ];
    }
}
