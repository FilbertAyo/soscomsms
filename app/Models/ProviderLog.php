<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'campaign_id',
        'action',
        'request_payload',
        'response_payload',
        'http_status',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'http_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['provider', 'campaign']);

        $parts = array_filter([
            $this->provider?->name,
            $this->action,
            $this->status,
        ]);
        $message = implode(' — ', $parts);
        if (filled($this->campaign?->name)) {
            $message .= ' ('.$this->campaign->name.')';
        }
        if (filled($this->error)) {
            $message .= ': '.$this->error;
        }

        return [
            'id' => $this->id,
            'source' => 'provider',
            'action' => $this->action,
            'status' => $this->status,
            'httpStatus' => $this->http_status,
            'campaignName' => $this->campaign?->name,
            'providerName' => $this->provider?->name,
            'error' => $this->error,
            'message' => $message,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
