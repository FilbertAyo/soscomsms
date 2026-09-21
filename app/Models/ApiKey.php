<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * @return HasMany<ApiLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function masked(): string
    {
        return $this->key_prefix.'••••••••';
    }

    public function status(): string
    {
        return $this->isRevoked() ? 'revoked' : 'active';
    }

    /**
     * @return array{id: int, name: string, prefix: string, masked: string, status: string, lastUsedAt: string|null, createdAt: string|null, revokedAt: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->key_prefix,
            'masked' => $this->masked(),
            'status' => $this->status(),
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'revokedAt' => $this->revoked_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, prefix: string, masked: string, status: string, customerId: int, customerEmail: string|null, lastUsedAt: string|null, createdAt: string|null, revokedAt: string|null}
     */
    public function toAdminArray(): array
    {
        $this->loadMissing('customer.user');

        return [
            ...$this->toApiArray(),
            'customerId' => $this->customer_id,
            'customerEmail' => $this->customer?->user?->email,
        ];
    }
}
