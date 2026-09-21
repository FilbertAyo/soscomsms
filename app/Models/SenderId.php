<?php

namespace App\Models;

use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use Database\Factories\SenderIdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenderId extends Model
{
    /** @use HasFactory<SenderIdFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sender_id_request_id',
        'name',
        'status',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SenderIdStatus::class,
            'source' => SenderIdSource::class,
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
     * @return BelongsTo<SenderIdRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(SenderIdRequest::class, 'sender_id_request_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUsableBy(Builder $query, Customer $customer): Builder
    {
        return $query
            ->where('status', SenderIdStatus::Active)
            ->where(function (Builder $inner) use ($customer): void {
                $inner->whereNull('customer_id')
                    ->orWhere('customer_id', $customer->id);
            });
    }

    public function isUsableBy(Customer $customer): bool
    {
        if ($this->status !== SenderIdStatus::Active) {
            return false;
        }

        return $this->customer_id === null || $this->customer_id === $customer->id;
    }

    /**
     * @return array{id: int, name: string, status: string, source: string, customerId: int|null, purpose: string|null, organization: string|null, createdAt: string|null}
     */
    public function toApiArray(): array
    {
        $this->loadMissing('request');

        $generic = $this->source === SenderIdSource::Generic;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'customerId' => $this->customer_id,
            'purpose' => $generic ? 'Shared SOSCOM Sender ID' : $this->request?->purpose,
            'organization' => $generic ? 'SOSCOM' : $this->request?->organization,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, status: string, source: string}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'source' => $this->source->value,
        ];
    }
}
