<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRequestedNotification;
use App\Support\TanzaniaPhone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentService
{
    public function __construct(
        private PricingService $pricing,
        private CreditLedger $ledger,
        private SnippeClient $snippe,
    ) {}

    /**
     * @param  array{quantity: int, payer_reference: string}  $payload
     */
    public function request(Customer $customer, array $payload): Payment
    {
        if (! $this->snippe->hasApiKey()) {
            throw ValidationException::withMessages([
                'quantity' => 'Mobile money payments are not set up yet.',
            ]);
        }

        $phone = $this->payingPhone($payload['payer_reference']);
        $quote = $this->pricing->quote($payload['quantity'], $customer);

        if ($quote['totalTzs'] < 500) {
            throw ValidationException::withMessages([
                'quantity' => 'The smallest mobile money payment is TZS 500.',
            ]);
        }

        $customer->loadMissing('user');
        [$firstName, $lastName, $email] = $this->customerIdentity($customer);

        $payment = DB::transaction(function () use ($customer, $quote, $phone): Payment {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();

            $pending = $customer->payments()
                ->where('method', PaymentMethod::Snippe)
                ->where('status', PaymentStatus::Pending)
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'quantity' => 'You already have a payment waiting on your phone. Approve it or wait for it to expire.',
                ]);
            }

            return $customer->payments()->create([
                'quantity' => $quote['quantity'],
                'unit_price_tzs' => $quote['unitPriceTzs'],
                'list_unit_price_tzs' => $quote['listUnitPriceTzs'],
                'amount_tzs' => $quote['totalTzs'],
                'method' => PaymentMethod::Snippe,
                'lipa_namba' => null,
                'payer_reference' => $phone,
                'status' => PaymentStatus::Pending,
                'expires_at' => now()->addHours(4),
            ]);
        });

        $idempotencyKey = 'p'.$payment->id;
        $snippePhone = ltrim($phone, '+');

        try {
            $data = $this->snippe->createMobilePayment([
                'payment_type' => 'mobile',
                'details' => [
                    'amount' => $payment->amount_tzs,
                    'currency' => 'TZS',
                ],
                'phone_number' => $snippePhone,
                'customer' => [
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email,
                ],
                'webhook_url' => url('/api/webhooks/snippe'),
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                ],
            ], $idempotencyKey);
        } catch (RequestException $exception) {
            $this->failStart($payment, $this->startFailureMessage($exception));

            throw ValidationException::withMessages([
                'payer_reference' => $this->startFailureMessage($exception),
            ]);
        } catch (Throwable $exception) {
            $this->failStart($payment, 'The payment could not be started. Try again.');
            Log::warning('Snippe payment create failed.', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payer_reference' => 'The payment could not be started. Try again.',
            ]);
        }

        $reference = $data['reference'] ?? null;
        if (! is_string($reference) || $reference === '') {
            $this->failStart($payment, 'The payment could not be started. Try again.');

            throw ValidationException::withMessages([
                'payer_reference' => 'The payment could not be started. Try again.',
            ]);
        }

        $expiresAt = $payment->expires_at;
        if (is_string($data['expires_at'] ?? null)) {
            try {
                $expiresAt = Carbon::parse($data['expires_at']);
            } catch (Throwable) {
                $expiresAt = now()->addHours(4);
            }
        }

        $payment->forceFill([
            'snippe_reference' => $reference,
            'external_reference' => $this->stringValue($data['external_reference'] ?? null),
            'provider' => $this->providerFrom($data),
            'expires_at' => $expiresAt,
        ])->save();

        $customer->notifyOwner(new PaymentRequestedNotification($payment));

        return $payment->fresh() ?? $payment;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function applyWebhook(array $event): void
    {
        $data = $event['data'] ?? null;
        if (! is_array($data)) {
            return;
        }

        $payment = $this->paymentForWebhook($data);
        if ($payment === null) {
            Log::warning('Snippe webhook did not match a payment.', [
                'event_id' => $this->stringValue($event['id'] ?? null),
                'reference' => $this->stringValue($data['reference'] ?? null),
            ]);

            return;
        }

        $credited = $this->applyProviderData(
            $payment,
            $data,
            $this->stringValue($event['id'] ?? null),
            $this->stringValue($event['type'] ?? null),
        );

        if ($credited) {
            $credited->customer?->notifyOwner(new PaymentConfirmedNotification($credited));
        }
    }

    public function refresh(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending || ! filled($payment->snippe_reference)) {
            return $payment;
        }

        try {
            $data = $this->snippe->fetchPayment((string) $payment->snippe_reference);
        } catch (Throwable $exception) {
            Log::warning('Snippe payment status check failed.', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);

            return $payment;
        }

        $credited = $this->applyProviderData($payment, $data, null, null);
        if ($credited) {
            $credited->customer?->notifyOwner(new PaymentConfirmedNotification($credited));

            return $credited;
        }

        return $payment->fresh() ?? $payment;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function expireDue(): Collection
    {
        $due = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            return $due;
        }

        Payment::query()
            ->whereKey($due->modelKeys())
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => PaymentStatus::Expired->value,
                'rejection_reason' => 'The phone prompt expired before it was approved.',
            ]);

        return Payment::query()
            ->whereKey($due->modelKeys())
            ->where('status', PaymentStatus::Expired)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paymentForWebhook(array $data): ?Payment
    {
        $reference = $this->stringValue($data['reference'] ?? null);
        if ($reference !== null) {
            $match = Payment::query()->where('snippe_reference', $reference)->first();
            if ($match !== null) {
                return $match;
            }
        }

        $metadata = $data['metadata'] ?? null;
        $paymentId = is_array($metadata) ? $metadata['payment_id'] ?? null : null;
        if (! is_numeric($paymentId)) {
            return null;
        }

        $candidate = Payment::query()->find((int) $paymentId);
        if ($candidate === null || $candidate->method !== PaymentMethod::Snippe) {
            return null;
        }

        if ($candidate->snippe_reference !== null && $reference !== null && $candidate->snippe_reference !== $reference) {
            return null;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyProviderData(Payment $payment, array $data, ?string $eventId, ?string $eventType): ?Payment
    {
        $outcome = $this->outcome($eventType, $data);

        /** @var Payment|null $credited */
        $credited = DB::transaction(function () use ($payment, $data, $eventId, $outcome): ?Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid || $locked->credit_transaction_id !== null) {
                return null;
            }

            if ($eventId !== null && $locked->webhook_event_id === $eventId) {
                return null;
            }

            if (
                $eventId !== null
                && Payment::query()->where('webhook_event_id', $eventId)->whereKeyNot($locked->id)->exists()
            ) {
                return null;
            }

            $reference = $this->stringValue($data['reference'] ?? null);
            if ($reference !== null && ($locked->snippe_reference === null || $locked->snippe_reference === $reference)) {
                $locked->snippe_reference = $reference;
            }

            $locked->external_reference = $this->stringValue($data['external_reference'] ?? null) ?? $locked->external_reference;
            $locked->provider = $this->providerFrom($data) ?? $locked->provider;

            if ($outcome === 'completed') {
                $money = $this->money($data['amount'] ?? null);
                if ($money === null || $money['currency'] !== 'TZS' || $money['value'] !== $locked->amount_tzs) {
                    if ($locked->status === PaymentStatus::Pending) {
                        $locked->forceFill([
                            'status' => PaymentStatus::Failed,
                            'rejection_reason' => 'The amount received did not match this purchase.',
                            'rejected_at' => now(),
                            'webhook_event_id' => $eventId ?? $locked->webhook_event_id,
                        ])->save();
                    }

                    Log::warning('Snippe payment amount did not match.', [
                        'payment_id' => $locked->id,
                        'expected' => $locked->amount_tzs,
                    ]);

                    return null;
                }

                $customer = $locked->customer;
                if ($customer === null) {
                    return null;
                }

                $settlement = is_array($data['settlement'] ?? null) ? $data['settlement'] : [];
                $description = "Purchase of {$locked->quantity} SMS via mobile money";
                if (
                    $locked->list_unit_price_tzs !== null
                    && $locked->list_unit_price_tzs !== $locked->unit_price_tzs
                ) {
                    $description .= " at TZS {$locked->unit_price_tzs} (normal TZS {$locked->list_unit_price_tzs})";
                }

                $transaction = $this->ledger->purchase(
                    $customer,
                    $locked->quantity,
                    $locked->unit_price_tzs,
                    'PAY-'.$locked->id,
                    $description,
                );

                $locked->forceFill([
                    'status' => PaymentStatus::Paid,
                    'credit_transaction_id' => $transaction->id,
                    'confirmed_at' => now(),
                    'fee_tzs' => $this->nestedMoney($settlement['fees'] ?? null),
                    'net_tzs' => $this->nestedMoney($settlement['net'] ?? null),
                    'webhook_event_id' => $eventId ?? $locked->webhook_event_id,
                    'rejection_reason' => null,
                ])->save();

                return $locked->fresh(['customer.user']) ?? $locked;
            }

            if ($locked->status !== PaymentStatus::Pending || $outcome === null) {
                $locked->save();

                return null;
            }

            if ($outcome === 'expired') {
                $locked->forceFill([
                    'status' => PaymentStatus::Expired,
                    'rejection_reason' => 'The phone prompt expired before it was approved.',
                    'webhook_event_id' => $eventId ?? $locked->webhook_event_id,
                ])->save();

                return null;
            }

            $reason = $this->stringValue($data['failure_reason'] ?? null)
                ?? ($outcome === 'voided' ? 'The payment was cancelled.' : 'The payment was not approved.');

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'rejection_reason' => mb_substr($reason, 0, 255),
                'rejected_at' => now(),
                'webhook_event_id' => $eventId ?? $locked->webhook_event_id,
            ])->save();

            return null;
        });

        return $credited;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function outcome(?string $eventType, array $data): ?string
    {
        $fromEvent = match ($eventType) {
            'payment.completed' => 'completed',
            'payment.failed' => 'failed',
            'payment.voided' => 'voided',
            'payment.expired' => 'expired',
            default => null,
        };

        if ($fromEvent !== null) {
            return $fromEvent;
        }

        return match ($this->stringValue($data['status'] ?? null)) {
            'completed' => 'completed',
            'failed' => 'failed',
            'voided' => 'voided',
            'expired' => 'expired',
            default => null,
        };
    }

    private function failStart(Payment $payment, string $reason): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'rejection_reason' => mb_substr($reason, 0, 255),
            'rejected_at' => now(),
        ])->save();
    }

    private function startFailureMessage(RequestException $exception): string
    {
        $status = $exception->response->status();

        return match (true) {
            $status === 401 || $status === 403 => 'Mobile money payments are not set up yet.',
            $status === 429 => 'Too many payment attempts. Wait a minute and try again.',
            $status === 400 => 'Check the mobile number and try again.',
            default => 'The payment could not be started. Try again.',
        };
    }

    private function payingPhone(string $raw): string
    {
        $phone = TanzaniaPhone::normalize(trim($raw));
        if (! TanzaniaPhone::isValid($phone)) {
            throw ValidationException::withMessages([
                'payer_reference' => 'Enter a valid Tanzanian mobile number.',
            ]);
        }

        return $phone;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function customerIdentity(Customer $customer): array
    {
        $email = trim((string) ($customer->user?->email ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'payer_reference' => 'Your account needs an email before you can pay.',
            ]);
        }

        $parts = preg_split('/\s+/', trim((string) ($customer->user?->name ?? ''))) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
        $first = mb_substr($parts[0] ?? 'Customer', 0, 80);
        $last = mb_substr(count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer', 0, 80);

        return [$first, $last, $email];
    }

    /**
     * @return array{value: int, currency: string}|null
     */
    private function money(mixed $amount): ?array
    {
        if (! is_array($amount)) {
            return null;
        }

        $value = $amount['value'] ?? null;
        $currency = $amount['currency'] ?? null;
        if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || ! is_string($currency)) {
            return null;
        }

        return [
            'value' => (int) $value,
            'currency' => strtoupper($currency),
        ];
    }

    private function nestedMoney(mixed $amount): ?int
    {
        return $this->money($amount)['value'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function providerFrom(array $data): ?string
    {
        $channel = $data['channel'] ?? null;
        $provider = is_array($channel) ? ($channel['provider'] ?? null) : null;

        return $this->stringValue($provider);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
