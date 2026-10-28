<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CustomerPrice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'pricing_tier_id',
        'unit_price_tzs',
        'starts_at',
        'expires_at',
        'disabled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price_tzs' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
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
     * @return BelongsTo<PricingTier, $this>
     */
    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeApplicable(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull('disabled_at')
            ->where(function (Builder $inner) use ($at): void {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $inner) use ($at): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            });
    }

    public function isApplicable(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->disabled_at !== null) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->gt($at)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lte($at)) {
            return false;
        }

        return true;
    }
}
