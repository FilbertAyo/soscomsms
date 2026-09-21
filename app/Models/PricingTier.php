<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricingTier extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'min_quantity',
        'max_quantity',
        'unit_price_tzs',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'unit_price_tzs' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMatchingVolume(Builder $query, int $quantity): Builder
    {
        return $query
            ->where('min_quantity', '<=', $quantity)
            ->where(function (Builder $inner) use ($quantity): void {
                $inner->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderByDesc('min_quantity');
    }

    public function label(): string
    {
        $min = number_format($this->min_quantity);

        if ($this->max_quantity === null) {
            return "{$min}+ SMS";
        }

        return "{$min}–".number_format($this->max_quantity).' SMS';
    }

    /**
     * @return array{id: int, minQuantity: int, maxQuantity: int|null, unitPriceTzs: int, label: string}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'minQuantity' => $this->min_quantity,
            'maxQuantity' => $this->max_quantity,
            'unitPriceTzs' => $this->unit_price_tzs,
            'label' => $this->label(),
        ];
    }
}
