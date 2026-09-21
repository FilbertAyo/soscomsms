<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private PricingService $pricing,
        private CreditLedger $ledger,
    ) {}

    /**
     * @param  array{quantity: int, payer_reference: string}  $payload
     */
    public function request(Customer $customer, array $payload): Payment
    {
        $pending = $customer->payments()->where('status', PaymentStatus::Pending)->count();
        if ($pending >= 3) {
            throw ValidationException::withMessages([
                'quantity' => 'You already have pending payments. Wait for Soscom to confirm one, or cancel with support.',
            ]);
        }

        $quote = $this->pricing->quote($payload['quantity']);
        $reference = trim($payload['payer_reference']);

        if ($reference === '') {
            throw ValidationException::withMessages([
                'payer_reference' => 'Enter the name or mobile number you will pay with.',
            ]);
        }

        $payment = $customer->payments()->create([
            'quantity' => $quote['quantity'],
            'unit_price_tzs' => $quote['unitPriceTzs'],
            'amount_tzs' => $quote['totalTzs'],
            'method' => PaymentMethod::LipaNamba,
            'lipa_namba' => (string) config('sms.billing.lipa_namba', '123456'),
            'payer_reference' => $reference,
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addHours((int) config('sms.billing.payment_expires_hours', 72)),
        ]);

        $customer->notifyOwner(new PaymentRequestedNotification($payment));

        return $payment;
    }

    public function confirm(Payment $payment, User $admin, ?string $note = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending payment can be marked paid.',
            ]);
        }

        $updated = DB::transaction(function () use ($payment, $admin, $note): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only a pending payment can be marked paid.',
                ]);
            }

            $customer = $locked->customer;
            if ($customer === null) {
                throw ValidationException::withMessages([
                    'status' => 'This payment has no customer.',
                ]);
            }

            $transaction = $this->ledger->purchase(
                $customer,
                $locked->quantity,
                $locked->unit_price_tzs,
                'PAY-'.$locked->id,
                "Purchase of {$locked->quantity} SMS via Lipa namba",
            );

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'credit_transaction_id' => $transaction->id,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'admin_note' => $note ?: $locked->admin_note,
            ])->save();

            return $locked->fresh(['customer.user', 'creditTransaction']) ?? $locked;
        });

        $updated->customer?->notifyOwner(new PaymentConfirmedNotification($updated));

        return $updated;
    }

    public function reject(Payment $payment, User $admin, string $reason): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending payment can be rejected.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Give a reason so the customer knows why it was rejected.',
            ]);
        }

        $payment->forceFill([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'confirmed_by' => $admin->id,
        ])->save();

        $updated = $payment->fresh(['customer.user']) ?? $payment;
        $updated->customer?->notifyOwner(new PaymentRejectedNotification($updated));

        return $updated;
    }

    public function expireDue(): int
    {
        return Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => PaymentStatus::Expired->value,
                'rejection_reason' => 'Payment was not confirmed before it expired.',
            ]);
    }
}
