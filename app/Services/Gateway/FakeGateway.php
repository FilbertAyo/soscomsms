<?php

namespace App\Services\Gateway;

use App\Contracts\SmsGatewayInterface;
use App\Enums\MessageEncoding;
use App\Models\Provider;
use App\Models\ProviderLog;
use Throwable;

class FakeGateway implements SmsGatewayInterface
{
    public function send(OutboundSms $message): GatewaySendResult
    {
        return $this->sendBulk([$message])[0];
    }

    /**
     * @param  list<OutboundSms>  $messages
     * @return list<GatewaySendResult>
     */
    public function sendBulk(array $messages): array
    {
        $provider = Provider::current();
        $results = [];
        $acceptedSegments = 0;

        foreach ($messages as $message) {
            $failPhones = array_map('strval', config('sms.fake.fail_phones', []));
            $timeoutPhones = array_map('strval', config('sms.fake.timeout_phones', []));

            if (in_array($message->phone, $timeoutPhones, true)) {
                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    timedOut: true,
                    code: 'timeout',
                    raw: 'fake-timeout',
                );

                continue;
            }

            if (in_array($message->phone, $failPhones, true)) {
                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    code: '001',
                    raw: 'Invalid Receiver',
                );

                continue;
            }

            $results[] = new GatewaySendResult(
                recipientId: $message->recipientId,
                phone: $message->phone,
                accepted: true,
                providerMessageId: 'FAKE-R'.$message->recipientId,
                code: '000',
                raw: 'Send Successful',
            );
            $acceptedSegments += $message->segmentCount;
        }

        if ($acceptedSegments > 0) {
            $balance = (int) ($provider->fake_balance ?? config('sms.fake.balance', 100000));
            $provider->fake_balance = max(0, $balance - $acceptedSegments);
            $provider->last_balance = $provider->fake_balance;
            $provider->last_balance_at = now();
            $provider->save();
        }

        $this->log($provider, $messages, $results);

        return $results;
    }

    public function checkBalance(): ProviderBalance
    {
        $provider = Provider::current();
        $balance = (int) ($provider->fake_balance ?? config('sms.fake.balance', 100000));
        $provider->last_balance = $balance;
        $provider->last_balance_at = now();
        $provider->save();

        return new ProviderBalance($balance, 'fake');
    }

    public function checkDeliveryStatus(string $providerMessageId): DeliveryReport
    {
        return new DeliveryReport($providerMessageId, 'delivered', 'fake-delivered');
    }

    /**
     * @param  list<OutboundSms>  $messages
     * @param  list<GatewaySendResult>  $results
     */
    private function log(Provider $provider, array $messages, array $results): void
    {
        try {
            ProviderLog::query()->create([
                'provider_id' => $provider->id,
                'action' => 'send_bulk',
                'request_payload' => [
                    'count' => count($messages),
                    'encoding' => $messages[0]->encoding->value ?? MessageEncoding::Gsm->value,
                ],
                'response_payload' => [
                    'accepted' => count(array_filter($results, fn (GatewaySendResult $result): bool => $result->accepted)),
                    'timed_out' => count(array_filter($results, fn (GatewaySendResult $result): bool => $result->timedOut)),
                ],
                'status' => 'ok',
            ]);
        } catch (Throwable) {
            // Logging must never block sending.
        }
    }
}
