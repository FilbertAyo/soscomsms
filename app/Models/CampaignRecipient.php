<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use Database\Factories\CampaignRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignRecipient extends Model
{
    /** @use HasFactory<CampaignRecipientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'message_id',
        'contact_id',
        'phone',
        'status',
        'segment_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'segment_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasOne<Delivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * @return array{id: int, phone: string, status: string, contactId: int|null, segmentCount: int, providerMessageId: string|null, deliveredAt: string|null}
     */
    public function toApiArray(): array
    {
        $this->loadMissing('delivery');

        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'contactId' => $this->contact_id,
            'segmentCount' => $this->segment_count,
            'providerMessageId' => $this->delivery?->provider_message_id,
            'deliveredAt' => $this->delivery?->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, campaign_id: int, to: string, status: string, segments: int, provider_message_id: string|null, delivered_at: string|null}
     */
    public function toPublicArray(): array
    {
        $this->loadMissing('delivery');

        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'to' => $this->phone,
            'status' => $this->status->value,
            'segments' => $this->segment_count,
            'provider_message_id' => $this->delivery?->provider_message_id,
            'delivered_at' => $this->delivery?->delivered_at?->toIso8601String(),
        ];
    }
}
