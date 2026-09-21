<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Support\PaginationMeta;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sender_id_id',
        'message_id',
        'credit_transaction_id',
        'name',
        'sender_name',
        'status',
        'recipient_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'total_segments',
        'scheduled_at',
        'queued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'recipient_count' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
            'total_segments' => 'integer',
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
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
     * @return BelongsTo<SenderId, $this>
     */
    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<CreditTransaction, $this>
     */
    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(bool $withRecipients = false, bool $withMessage = true): array
    {
        if ($withMessage) {
            $this->loadMissing('message');
        }

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'senderId' => $this->sender_name,
            'status' => $this->status->value,
            'message' => $withMessage ? $this->message?->body : null,
            'encoding' => $withMessage ? $this->message?->encoding?->value : null,
            'characterCount' => $withMessage ? $this->message?->character_count : null,
            'segmentsPerRecipient' => $withMessage ? $this->message?->segment_count : null,
            'recipientPhone' => $this->recipientPhonePreview(),
            'recipients' => $this->recipient_count,
            'sent' => $this->sent_count,
            'delivered' => $this->delivered_count,
            'failed' => $this->failed_count,
            'totalSms' => $this->total_segments,
            'scheduledAt' => $this->scheduled_at?->toIso8601String(),
            'queuedAt' => $this->queued_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];

        if ($withRecipients) {
            $page = $this->recipients()
                ->with('delivery')
                ->orderBy('id')
                ->paginate(min(max((int) request()->integer('per_page', 50), 1), 200));

            $payload['recipientRows'] = $page->getCollection()->map->toApiArray()->values();
            $payload['recipientMeta'] = PaginationMeta::from($page);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['customer.user']);

        return array_merge($this->toApiArray(), [
            'customerName' => $this->customer?->user?->name,
            'customerEmail' => $this->customer?->user?->email,
            'customerBusiness' => $this->customer?->business_name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(bool $withMessages = false, ?Customer $customer = null): array
    {
        $this->loadMissing('message');

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'sender_id' => $this->sender_name,
            'message' => $this->message?->body,
            'encoding' => $this->message?->encoding?->value,
            'character_count' => $this->message?->character_count,
            'segments' => $this->message?->segment_count,
            'recipients' => $this->recipient_count,
            'sent' => $this->sent_count,
            'delivered' => $this->delivered_count,
            'failed' => $this->failed_count,
            'sms' => $this->total_segments,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($customer !== null) {
            $payload['sms_balance'] = $customer->sms_balance;
        }

        if ($withMessages) {
            $page = $this->recipients()
                ->with('delivery')
                ->orderBy('id')
                ->paginate(min(max((int) request()->integer('per_page', 50), 1), 200));

            $payload['messages'] = $page->getCollection()->map->toPublicArray()->values();
            $payload['messages_meta'] = PaginationMeta::from($page);
        }

        return $payload;
    }

    public function refreshCounts(): self
    {
        $counts = $this->recipients()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $countFor = function (CampaignRecipientStatus ...$statuses) use ($counts): int {
            $total = 0;
            foreach ($statuses as $status) {
                $total += (int) ($counts[$status->value] ?? 0);
            }

            return $total;
        };

        $delivered = $countFor(CampaignRecipientStatus::Delivered);
        $failed = $countFor(CampaignRecipientStatus::Failed, CampaignRecipientStatus::Rejected);
        $accepted = $countFor(CampaignRecipientStatus::Sent, CampaignRecipientStatus::Delivered);
        $pending = $countFor(CampaignRecipientStatus::Queued, CampaignRecipientStatus::Pending);

        $this->sent_count = $accepted;
        $this->delivered_count = $delivered;
        $this->failed_count = $failed;

        if (in_array($this->status, [
            CampaignStatus::Cancelled,
            CampaignStatus::Scheduled,
            CampaignStatus::Draft,
        ], true)) {
            $this->save();

            return $this;
        }

        if ($pending > 0) {
            $this->status = CampaignStatus::Sending;
        } elseif ($accepted > 0 && $failed > 0) {
            $this->status = CampaignStatus::PartiallyDelivered;
        } elseif ($accepted === 0 && $failed > 0) {
            $this->status = CampaignStatus::Failed;
        } else {
            $this->status = CampaignStatus::Completed;
        }

        $this->save();

        return $this;
    }

    public function recipientPhonePreview(): ?string
    {
        $phone = $this->getAttribute('recipient_phone');
        if (is_string($phone) && $phone !== '') {
            return $phone;
        }

        if ($this->relationLoaded('recipients')) {
            return $this->recipients->sortBy('id')->first()?->phone;
        }

        return $this->recipients()->orderBy('id')->value('phone');
    }
}
