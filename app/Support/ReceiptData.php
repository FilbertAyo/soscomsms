<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Carbon;

class ReceiptData
{
    /**
     * @return array<string, mixed>
     */
    public function forPayment(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Paid) {
            abort(404);
        }

        $payment->loadMissing(['customer.user', 'creditTransaction.reversal']);

        return $this->payload($payment->creditTransaction, $payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function forCreditTransaction(CreditTransaction $transaction): array
    {
        $transaction->loadMissing(['customer.user', 'reversal', 'payment']);

        if (! $transaction->receiptAvailable()) {
            abort(404);
        }

        $payment = $transaction->payment;

        if ($payment !== null) {
            return $this->forPayment($payment);
        }

        return $this->payload($transaction, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?CreditTransaction $transaction, ?Payment $payment): array
    {
        $customer = $payment?->customer ?? $transaction?->customer;
        $issuedAt = $payment?->confirmed_at ?? $payment?->created_at ?? $transaction?->created_at;
        $timezone = (string) config('app.timezone', 'Africa/Dar_es_Salaam');
        $issued = $issuedAt instanceof Carbon ? $issuedAt->copy()->timezone($timezone) : null;
        $quantity = abs((int) ($transaction?->quantity ?? $payment?->quantity ?? 0));
        $unitPrice = (int) ($payment?->unit_price_tzs ?? $transaction?->customer_unit_price ?? 0);
        $listUnit = $payment?->list_unit_price_tzs;
        $amount = (int) ($payment?->amount_tzs ?? $transaction?->amount_tzs ?? 0);
        $reversed = $transaction?->isReversed() ?? false;

        return [
            'receiptNumber' => $payment !== null
                ? sprintf('RCP-%06d', $payment->id)
                : (string) $transaction?->reference,
            'issuedAt' => $issued?->toIso8601String(),
            'issuedAtLabel' => $issued?->format('d M Y, H:i'),
            'status' => $reversed ? 'reversed' : 'paid',
            'issuer' => [
                'company' => 'SOSCOM Technologies Company Limited',
                'product' => 'Soscom SMS',
                'website' => 'sms.soscom.co.tz',
                'email' => 'sms@soscom.co.tz',
            ],
            'billTo' => $this->billTo($customer),
            'line' => [
                'description' => 'SMS credits',
                'quantity' => $quantity,
                'unitPriceTzs' => $unitPrice,
                'listUnitPriceTzs' => $listUnit,
                'specialPrice' => $listUnit !== null && $listUnit !== $unitPrice,
                'amountTzs' => $amount,
            ],
            'paymentMethod' => $payment?->method->value ?? 'admin_grant',
            'paymentMethodLabel' => $this->methodLabel($payment),
            'transactionId' => $this->transactionId($payment),
            'internalReference' => (string) ($transaction?->reference ?? ($payment !== null ? 'PAY-'.$payment->id : '')),
            'payerReference' => $this->payerNumber($payment),
            'balanceAfter' => $transaction?->balance_after,
            'smsAdded' => $quantity,
            'note' => $this->note($payment, $transaction),
        ];
    }

    /**
     * @return array{name: string|null, businessName: string|null, email: string|null, phone: string|null}
     */
    private function billTo(?Customer $customer): array
    {
        $customer?->loadMissing('user');

        return [
            'name' => $customer?->user?->name,
            'businessName' => $customer?->business_name,
            'email' => $customer?->user?->email,
            'phone' => $customer?->phone,
        ];
    }

    private function methodLabel(?Payment $payment): string
    {
        if ($payment === null) {
            return 'Admin grant';
        }

        $provider = trim((string) $payment->provider);

        return match ($payment->method) {
            PaymentMethod::Snippe => $provider !== '' ? 'Mobile money ('.$provider.')' : 'Mobile money',
            PaymentMethod::LipaNamba, PaymentMethod::ManualMobile => 'Mobile money',
            PaymentMethod::Cash => 'Cash',
            PaymentMethod::Bank => 'Bank transfer',
        };
    }

    private function transactionId(?Payment $payment): ?string
    {
        if ($payment === null) {
            return null;
        }

        $value = match ($payment->method) {
            PaymentMethod::Snippe, PaymentMethod::LipaNamba => $payment->snippe_reference ?: $payment->external_reference,
            PaymentMethod::Cash, PaymentMethod::Bank, PaymentMethod::ManualMobile => $payment->external_reference,
        };

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function payerNumber(?Payment $payment): ?string
    {
        if ($payment === null) {
            return null;
        }

        $show = match ($payment->method) {
            PaymentMethod::Snippe, PaymentMethod::LipaNamba => true,
            PaymentMethod::Cash, PaymentMethod::Bank, PaymentMethod::ManualMobile => false,
        };

        if (! $show) {
            return null;
        }

        $value = trim($payment->payer_reference);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function note(?Payment $payment, ?CreditTransaction $transaction): ?string
    {
        $adminNote = trim((string) $payment?->admin_note);

        if ($adminNote !== '') {
            return $adminNote;
        }

        if ($payment !== null) {
            return null;
        }

        $description = trim((string) $transaction?->description);

        return $description === '' ? null : $description;
    }
}
