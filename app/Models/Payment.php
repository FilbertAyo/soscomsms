<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'quantity',
        'unit_price_tzs',
        'amount_tzs',
        'method',
        'lipa_namba',
        'payer_reference',
        'status',
        'credit_transaction_id',
        'confirmed_by',
        'confirmed_at',
        'rejected_at',
        'expires_at',
        'rejection_reason',
        'admin_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_tzs' => 'integer',
            'amount_tzs' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<CreditTransaction, $this>
     */
    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing(['customer.user', 'confirmedBy']);

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unitPriceTzs' => $this->unit_price_tzs,
            'amountTzs' => $this->amount_tzs,
            'method' => $this->method->value,
            'lipaNamba' => $this->lipa_namba,
            'payerReference' => $this->payer_reference,
            'status' => $this->status->value,
            'creditTransactionId' => $this->credit_transaction_id,
            'rejectionReason' => $this->rejection_reason,
            'adminNote' => $this->admin_note,
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'confirmedAt' => $this->confirmed_at?->toIso8601String(),
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->user?->name,
                'email' => $this->customer->user?->email,
                'businessName' => $this->customer->business_name,
            ],
        ];
    }
}
