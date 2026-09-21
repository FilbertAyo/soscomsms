<?php

namespace App\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhookJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebhookDispatcher
{
    /**
     * @param  list<string>  $events
     * @return array{webhook: Webhook, secret: string}
     */
    public function create(Customer $customer, string $name, string $url, array $events): array
    {
        $max = (int) config('sms.webhooks.max_per_customer', 5);
        if ($customer->webhooks()->count() >= $max) {
            throw ValidationException::withMessages([
                'url' => "You can have at most {$max} webhooks.",
            ]);
        }

        $url = $this->validatedUrl($url);
        $events = $this->validatedEvents($events);
        $secret = $this->newSecret();

        $webhook = $customer->webhooks()->create([
            'name' => $name,
            'url' => $url,
            'secret' => $secret,
            'events' => $events,
            'enabled' => true,
        ]);

        return ['webhook' => $webhook, 'secret' => $secret];
    }

    /**
     * @param  array{name?: string, url?: string, events?: list<string>, enabled?: bool}  $payload
     */
    public function update(Webhook $webhook, array $payload): Webhook
    {
        if (isset($payload['url'])) {
            $payload['url'] = $this->validatedUrl($payload['url']);
        }

        if (isset($payload['events'])) {
            $payload['events'] = $this->validatedEvents($payload['events']);
        }

        $webhook->fill($payload);
        $webhook->save();

        return $webhook->fresh() ?? $webhook;
    }

    /**
     * @return array{webhook: Webhook, secret: string}
     */
    public function rotateSecret(Webhook $webhook): array
    {
        $secret = $this->newSecret();
        $webhook->forceFill(['secret' => $secret])->save();

        return ['webhook' => $webhook->fresh() ?? $webhook, 'secret' => $secret];
    }

    public function delete(Webhook $webhook): void
    {
        $webhook->delete();
    }

    public function deliveryUpdated(CampaignRecipient $recipient): void
    {
        $campaign = $recipient->campaign;
        if ($campaign === null) {
            return;
        }

        $this->queue(
            $campaign->customer,
            WebhookEvent::DeliveryUpdated,
            'delivery:'.$recipient->id.':'.$recipient->status->value,
            [
                'id' => $recipient->id,
                'campaign_id' => $campaign->id,
                'to' => $recipient->phone,
                'status' => $recipient->status->value,
                'sender_id' => $campaign->sender_name,
                'delivered_at' => $recipient->delivery?->delivered_at?->toIso8601String(),
            ],
            $campaign->id,
            $recipient->id,
        );
    }

    public function campaignCompleted(Campaign $campaign): void
    {
        if (! in_array($campaign->status->value, [
            'completed',
            'partially_delivered',
            'failed',
        ], true)) {
            return;
        }

        $this->queue(
            $campaign->customer,
            WebhookEvent::CampaignCompleted,
            'campaign:'.$campaign->id.':'.$campaign->status->value,
            [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'sender_id' => $campaign->sender_name,
                'recipients' => $campaign->recipient_count,
                'delivered' => $campaign->delivered_count,
                'failed' => $campaign->failed_count,
            ],
            $campaign->id,
            null,
        );
    }

    public function retryDue(): int
    {
        $ids = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverWebhookJob::dispatch((int) $id);
        }

        return $ids->count();
    }

    private function queue(
        ?Customer $customer,
        WebhookEvent $event,
        string $eventKey,
        array $data,
        ?int $campaignId,
        ?int $recipientId,
    ): void {
        if ($customer === null) {
            return;
        }

        $webhooks = $customer->webhooks()
            ->where('enabled', true)
            ->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->listensFor($event));

        foreach ($webhooks as $webhook) {
            $existing = WebhookDelivery::query()
                ->where('webhook_id', $webhook->id)
                ->where('event_key', $eventKey)
                ->first();

            if ($existing !== null) {
                continue;
            }

            $payload = [
                'id' => 'evt_'.Str::lower((string) Str::ulid()),
                'type' => $event->value,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ];

            $delivery = WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id,
                'customer_id' => $customer->id,
                'campaign_id' => $campaignId,
                'campaign_recipient_id' => $recipientId,
                'event' => $event,
                'event_key' => $eventKey,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempt' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id)->afterCommit();
        }
    }

    public function validatedUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        $local = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

        if ($scheme === 'https' || ($scheme === 'http' && $local)) {
            return $url;
        }

        throw ValidationException::withMessages([
            'url' => 'Use an HTTPS URL. http://127.0.0.1 is allowed for local testing.',
        ]);
    }

    /**
     * @param  list<string>  $events
     * @return list<string>
     */
    public function validatedEvents(array $events): array
    {
        $allowed = array_map(fn (WebhookEvent $event): string => $event->value, WebhookEvent::all());
        $clean = array_values(array_unique(array_intersect($events, $allowed)));

        if ($clean === []) {
            throw ValidationException::withMessages([
                'events' => 'Choose at least one event: delivery.updated or campaign.completed.',
            ]);
        }

        return $clean;
    }

    private function newSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }
}
