<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(public int $webhookDeliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->find($this->webhookDeliveryId);
        if ($delivery === null || $delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $webhook = $delivery->webhook;
        if ($webhook === null || ! $webhook->enabled) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'Webhook is missing or disabled.',
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret);

        $delivery->attempt++;
        $maxAttempts = (int) config('sms.webhooks.max_attempts', 5);

        try {
            $response = Http::timeout((int) config('sms.webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Soscom-SMS-Webhook/1.0',
                    'X-Soscom-Event' => $delivery->event->value,
                    'X-Soscom-Delivery-Id' => (string) $delivery->id,
                    'X-Soscom-Signature' => 't='.$timestamp.',v1='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $delivery->http_status = $response->status();

            if ($response->successful()) {
                $delivery->status = WebhookDeliveryStatus::Delivered;
                $delivery->error = null;
                $delivery->next_retry_at = null;
                $delivery->delivered_at = now();
                $delivery->save();

                return;
            }

            $delivery->error = Str::limit('HTTP '.$response->status(), 240);
        } catch (ConnectionException $exception) {
            $delivery->http_status = null;
            $delivery->error = Str::limit('Connection failed: '.$exception->getMessage(), 240);
        } catch (Throwable $exception) {
            $delivery->http_status = null;
            $delivery->error = Str::limit($exception->getMessage(), 240);
        }

        if ($delivery->attempt >= $maxAttempts) {
            $delivery->status = WebhookDeliveryStatus::Failed;
            $delivery->next_retry_at = null;
        } else {
            $delivery->status = WebhookDeliveryStatus::Pending;
            $backoff = config('sms.webhooks.backoff', [60, 300, 900, 3600]);
            $wait = (int) ($backoff[$delivery->attempt - 1] ?? 3600);
            $delivery->next_retry_at = now()->addSeconds($wait);
        }

        $delivery->save();
    }
}
