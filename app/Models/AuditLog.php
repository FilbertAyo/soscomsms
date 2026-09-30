<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'customer_id',
        'action',
        'subject_type',
        'subject_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['actor', 'customer.user']);

        return [
            'id' => $this->id,
            'source' => 'audit',
            'action' => $this->action->value,
            'level' => $this->level(),
            'message' => $this->message(),
            'actorName' => $this->actor?->name,
            'actorEmail' => $this->actor?->email,
            'customerEmail' => $this->customer?->user?->email,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function level(): string
    {
        return match ($this->action) {
            AuditAction::CustomerSuspended,
            AuditAction::PaymentRejected,
            AuditAction::SenderIdRejected => 'warning',
            AuditAction::CreditsReversed => 'warning',
            AuditAction::CreditsGranted,
            AuditAction::CustomerUnsuspended,
            AuditAction::PaymentConfirmed,
            AuditAction::SenderIdReviewed,
            AuditAction::SenderIdSubmitted,
            AuditAction::SenderIdApproved,
            AuditAction::SenderIdCreated,
            AuditAction::SenderIdUpdated,
            AuditAction::SenderIdGranted,
            AuditAction::SenderIdRevoked,
            AuditAction::PricingUpdated,
            AuditAction::ProviderSettingsUpdated,
            AuditAction::ProviderWhitelistConfirmed,
            AuditAction::ProviderAccountConfirmed => 'info',
        };
    }

    private function message(): string
    {
        $who = $this->actor?->name ?? 'Admin';
        $customer = $this->customer?->user?->email
            ?? $this->customer?->business_name
            ?? 'a customer';
        $payload = $this->payload ?? [];
        $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : null;
        $name = isset($payload['name']) ? (string) $payload['name'] : null;
        $label = isset($payload['label']) ? (string) $payload['label'] : null;
        $from = isset($payload['from']) ? (int) $payload['from'] : null;
        $to = isset($payload['to']) ? (int) $payload['to'] : null;
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;
        $amount = isset($payload['amount_tzs']) ? (int) $payload['amount_tzs'] : null;

        return match ($this->action) {
            AuditAction::CreditsGranted => $quantity === null
                ? "{$who} granted SMS credits to {$customer}."
                : ($amount === null
                    ? "{$who} granted {$quantity} SMS to {$customer}."
                    : "{$who} granted {$quantity} SMS to {$customer} for TZS {$amount}."),
            AuditAction::CreditsReversed => $quantity === null
                ? "{$who} reversed a credit transaction for {$customer}."
                : "{$who} reversed {$quantity} SMS for {$customer}.",
            AuditAction::CustomerSuspended => "{$who} suspended {$customer}.",
            AuditAction::CustomerUnsuspended => "{$who} unsuspended {$customer}.",
            AuditAction::PaymentConfirmed => $amount === null
                ? "{$who} confirmed a payment for {$customer}."
                : "{$who} confirmed a TZS {$amount} payment for {$customer}.",
            AuditAction::PaymentRejected => $reason === null || $reason === ''
                ? "{$who} rejected a payment for {$customer}."
                : "{$who} rejected a payment for {$customer}: {$reason}.",
            AuditAction::SenderIdReviewed => $name === null || $name === ''
                ? "{$who} marked a Sender ID request under review."
                : "{$who} marked Sender ID {$name} under review.",
            AuditAction::SenderIdSubmitted => $name === null || $name === ''
                ? "{$who} submitted a Sender ID to the provider."
                : "{$who} submitted Sender ID {$name} to the provider.",
            AuditAction::SenderIdApproved => $name === null || $name === ''
                ? "{$who} approved a Sender ID for {$customer}."
                : "{$who} approved Sender ID {$name} for {$customer}.",
            AuditAction::SenderIdRejected => $name === null || $name === ''
                ? "{$who} rejected a Sender ID request."
                : "{$who} rejected Sender ID {$name}".($reason ? ": {$reason}" : '.'),
            AuditAction::SenderIdCreated => $name === null || $name === ''
                ? "{$who} added a Sender ID."
                : "{$who} added Sender ID {$name}.",
            AuditAction::SenderIdUpdated => $name === null || $name === ''
                ? "{$who} updated a Sender ID."
                : "{$who} updated Sender ID {$name}.",
            AuditAction::SenderIdGranted => $name === null || $name === ''
                ? "{$who} granted a Sender ID to {$customer}."
                : "{$who} granted Sender ID {$name} to {$customer}.",
            AuditAction::SenderIdRevoked => $name === null || $name === ''
                ? "{$who} removed a Sender ID from {$customer}."
                : "{$who} removed Sender ID {$name} from {$customer}.",
            AuditAction::PricingUpdated => $label === null || $from === null || $to === null
                ? "{$who} updated a pricing tier."
                : "{$who} updated {$label} from TZS {$from} to TZS {$to} per SMS.",
            AuditAction::ProviderSettingsUpdated => "{$who} updated production provider settings.",
            AuditAction::ProviderWhitelistConfirmed => "{$who} confirmed Mobishastra has whitelisted the SOSCOM static IP.",
            AuditAction::ProviderAccountConfirmed => "{$who} confirmed the Tanzania Mobishastra account details.",
        };
    }
}
