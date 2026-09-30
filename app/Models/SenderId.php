<?php

namespace App\Models;

use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use Database\Factories\SenderIdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'available_to_all',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SenderIdStatus::class,
            'source' => SenderIdSource::class,
            'available_to_all' => 'boolean',
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
     * @return HasMany<SenderIdGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(SenderIdGrant::class);
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function grantedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'sender_id_grants')->withTimestamps();
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
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
                $inner->where('available_to_all', true)
                    ->orWhere('customer_id', $customer->id)
                    ->orWhereHas('grants', function (Builder $grants) use ($customer): void {
                        $grants->where('customer_id', $customer->id);
                    });
            });
    }

    public function isUsableBy(Customer $customer): bool
    {
        if ($this->status !== SenderIdStatus::Active) {
            return false;
        }

        if ($this->available_to_all || $this->customer_id === $customer->id) {
            return true;
        }

        return $this->grants()->where('customer_id', $customer->id)->exists();
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
     * @return array{id: int, name: string, status: string, source: string, availableToAll: bool, customerId: int|null, campaignCount: int, grants: list<array{id: int, name: string|null, email: string|null, businessName: string|null}>, createdAt: string|null}
     */
    public function toAdminCatalogArray(): array
    {
        $this->loadMissing(['grantedCustomers.user']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'availableToAll' => $this->available_to_all,
            'customerId' => $this->customer_id,
            'campaignCount' => $this->campaigns()->count(),
            'grants' => $this->grantedCustomers
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->user?->name,
                    'email' => $customer->user?->email,
                    'businessName' => $customer->business_name,
                ])
                ->values()
                ->all(),
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
