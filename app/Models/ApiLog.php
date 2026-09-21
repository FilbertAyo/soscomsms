<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'api_key_id',
        'customer_id',
        'campaign_id',
        'method',
        'path',
        'status_code',
        'ip',
        'request_id',
        'error',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return array{id: int, customerId: int|null, customerEmail: string|null, keyName: string|null, keyPrefix: string|null, method: string, path: string, statusCode: int, ip: string|null, requestId: string, campaignId: int|null, error: string|null, createdAt: string|null}
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['customer.user', 'apiKey']);

        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'customerEmail' => $this->customer?->user?->email,
            'keyName' => $this->apiKey?->name,
            'keyPrefix' => $this->apiKey?->key_prefix,
            'method' => $this->method,
            'path' => $this->path,
            'statusCode' => $this->status_code,
            'ip' => $this->ip,
            'requestId' => $this->request_id,
            'campaignId' => $this->campaign_id,
            'error' => $this->error,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
