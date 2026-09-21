<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_id',
        'customer_id',
        'campaign_id',
        'campaign_recipient_id',
        'event',
        'event_key',
        'payload',
        'status',
        'attempt',
        'http_status',
        'error',
        'next_retry_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'status' => WebhookDeliveryStatus::class,
            'payload' => 'array',
            'attempt' => 'integer',
            'http_status' => 'integer',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
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
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing('webhook');

        return [
            'id' => $this->id,
            'webhookId' => $this->webhook_id,
            'webhookName' => $this->webhook?->name,
            'event' => $this->event->value,
            'status' => $this->status->value,
            'attempt' => $this->attempt,
            'httpStatus' => $this->http_status,
            'error' => $this->error,
            'campaignId' => $this->campaign_id,
            'nextRetryAt' => $this->next_retry_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['customer.user', 'webhook']);

        return [
            ...$this->toApiArray(),
            'customerId' => $this->customer_id,
            'customerEmail' => $this->customer?->user?->email,
            'url' => $this->webhook?->url,
        ];
    }
}
