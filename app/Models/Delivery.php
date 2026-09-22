<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_recipient_id',
        'provider_id',
        'provider_message_id',
        'status',
        'provider_response_code',
        'provider_response',
        'attempted_at',
        'sent_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CampaignRecipient, $this>
     */
    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function wasSubmitted(): bool
    {
        return filled($this->provider_message_id);
    }

    /**
     * Never resend a row that was already handed to the provider.
     * Ambiguous attempts (no provider message id) stay locked and must be
     * cancelled instead of retried — retries caused duplicate SMS.
     */
    public function isLockedAgainstRetry(): bool
    {
        return $this->wasSubmitted()
            || ($this->attempted_at !== null && $this->provider_message_id === null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toReportArray(): array
    {
        $this->loadMissing(['campaignRecipient.campaign', 'campaignRecipient.message']);
        $recipient = $this->campaignRecipient;
        $campaign = $recipient?->campaign;
        $message = $recipient?->message ?? $campaign?->message;

        return [
            'id' => $this->id,
            'phone' => $recipient?->phone,
            'campaignId' => $campaign?->id,
            'campaignName' => $campaign?->name,
            'senderId' => $campaign?->sender_name,
            'message' => $message?->body,
            'status' => $recipient?->status->value,
            'providerMessageId' => $this->provider_message_id,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['campaignRecipient.campaign.customer.user']);
        $customer = $this->campaignRecipient?->campaign?->customer;

        return array_merge($this->toReportArray(), [
            'customerName' => $customer?->user?->name,
            'customerEmail' => $customer?->user?->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $this->loadMissing(['campaignRecipient.campaign', 'campaignRecipient.message']);
        $recipient = $this->campaignRecipient;
        $campaign = $recipient?->campaign;
        $message = $recipient?->message ?? $campaign?->message;

        return [
            'id' => $recipient?->id,
            'to' => $recipient?->phone,
            'campaign_id' => $campaign?->id,
            'campaign_name' => $campaign?->name,
            'sender_id' => $campaign?->sender_name,
            'message' => $message?->body,
            'status' => $recipient?->status->value,
            'provider_message_id' => $this->provider_message_id,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
