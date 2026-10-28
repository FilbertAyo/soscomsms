<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'phone',
        'job_title',
        'status',
        'sms_balance',
        'profile_reminders_sent',
        'profile_reminded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'sms_balance' => 'integer',
            'profile_reminders_sent' => 'integer',
            'profile_reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * @return HasMany<ContactGroup, $this>
     */
    public function contactGroups(): HasMany
    {
        return $this->hasMany(ContactGroup::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<SenderIdRequest, $this>
     */
    public function senderIdRequests(): HasMany
    {
        return $this->hasMany(SenderIdRequest::class);
    }

    /**
     * @return HasMany<SenderId, $this>
     */
    public function senderIds(): HasMany
    {
        return $this->hasMany(SenderId::class);
    }

    /**
     * @return BelongsToMany<SenderId, $this>
     */
    public function grantedSenderIds(): BelongsToMany
    {
        return $this->belongsToMany(SenderId::class, 'sender_id_grants')->withTimestamps();
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<MessageTemplate, $this>
     */
    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    /**
     * @return HasMany<Webhook, $this>
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<CustomerPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(CustomerPrice::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<ApiLog, $this>
     */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::Suspended;
    }

    public function canSend(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    public function assertCanSend(): void
    {
        if ($this->isSuspended()) {
            abort(403, 'This account is suspended. Sending is disabled.');
        }

        if (! $this->canSend()) {
            abort(403, 'Complete your business profile before sending SMS.');
        }
    }

    public function notifyOwner(Notification $notification): void
    {
        $this->loadMissing('user');
        $this->user?->notify($notification);
    }

    public function refreshStatusFromProfile(): void
    {
        $complete = filled($this->business_name) && filled($this->phone);

        $this->status = match ($this->status) {
            CustomerStatus::Suspended => CustomerStatus::Suspended,
            CustomerStatus::PendingProfile, CustomerStatus::Active => $complete
                ? CustomerStatus::Active
                : CustomerStatus::PendingProfile,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(?int $sentThisMonth = null, ?int $campaignCount = null): array
    {
        $this->loadMissing('user');

        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'businessName' => $this->business_name,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'smsBalance' => $this->sms_balance,
            'sentThisMonth' => $sentThisMonth ?? (int) ($this->sent_this_month ?? 0),
            'campaignCount' => $campaignCount,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, businessName: string|null, phone: string|null, jobTitle: string|null, status: string, smsBalance: int}
     */
    public function toAuthArray(): array
    {
        return [
            'id' => $this->id,
            'businessName' => $this->business_name,
            'phone' => $this->phone,
            'jobTitle' => $this->job_title,
            'status' => $this->status->value,
            'smsBalance' => $this->sms_balance,
        ];
    }
}
