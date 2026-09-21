<?php

namespace App\Services;

use App\Contracts\SmsGatewayInterface;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MessageEncoding;
use App\Exceptions\ProviderNotReadyException;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Provider;
use App\Models\ProviderLog;
use App\Services\Gateway\GatewaySendResult;
use App\Services\Gateway\OutboundSms;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CampaignDispatcher
{
    public function __construct(
        private SmsGatewayInterface $gateway,
        private CreditLedger $ledger,
        private DeliveryReportService $deliveryReports,
        private WebhookDispatcher $webhooks,
        private ProviderReadinessService $readiness,
    ) {}

    public function queue(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Queued) {
            return;
        }

        DispatchCampaignJob::dispatch($campaign->id)->afterCommit();
    }

    public function dispatch(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Queued, CampaignStatus::Sending], true)) {
            return $campaign;
        }

        $campaign->loadMissing(['message', 'customer']);

        if ($campaign->customer?->isSuspended()) {
            return $campaign;
        }

        try {
            $this->readiness->assertReadyToSend();
        } catch (ProviderNotReadyException $exception) {
            ProviderLog::query()->create([
                'provider_id' => Provider::current()->id,
                'campaign_id' => $campaign->id,
                'action' => 'send_bulk',
                'status' => 'blocked',
                'error' => $exception->getMessage(),
            ]);

            return $campaign;
        }

        $campaign->status = CampaignStatus::Sending;
        $campaign->save();

        $recipientIds = $this->lockRecipientsForSend($campaign);
        if ($recipientIds === []) {
            return $this->refreshCounts($campaign);
        }

        $chunkSize = max(1, (int) config('sms.provider.bulk_chunk', 100));
        foreach (array_chunk($recipientIds, $chunkSize) as $chunkIds) {
            $this->dispatchChunk($campaign, $chunkIds);
        }

        return $this->refreshCounts($campaign);
    }

    /**
     * @param  list<int>  $recipientIds
     */
    public function dispatchChunk(Campaign $campaign, array $recipientIds): void
    {
        if ($recipientIds === []) {
            return;
        }

        $campaign->loadMissing(['message', 'customer']);

        if ($campaign->customer?->isSuspended()) {
            return;
        }

        try {
            $this->readiness->assertReadyToSend();
        } catch (ProviderNotReadyException $exception) {
            ProviderLog::query()->create([
                'provider_id' => Provider::current()->id,
                'campaign_id' => $campaign->id,
                'action' => 'send_bulk',
                'status' => 'blocked',
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $provider = Provider::current();
        $recipients = $campaign->recipients()
            ->with('delivery')
            ->whereIn('id', $recipientIds)
            ->orderBy('id')
            ->get();

        $messages = [];
        foreach ($recipients as $recipient) {
            $messages[] = new OutboundSms(
                recipientId: $recipient->id,
                phone: $recipient->phone,
                sender: $campaign->sender_name,
                body: (string) $campaign->message?->body,
                encoding: $campaign->message?->encoding ?? MessageEncoding::Gsm,
                segmentCount: $recipient->segment_count,
                campaignId: $campaign->id,
            );
        }

        try {
            $results = $this->gateway->sendBulk($messages);
        } catch (Throwable $exception) {
            ProviderLog::query()->create([
                'provider_id' => $provider->id,
                'campaign_id' => $campaign->id,
                'action' => 'send_bulk',
                'status' => 'timeout',
                'error' => $exception->getMessage(),
            ]);

            foreach ($recipients as $recipient) {
                $this->markUnknown($recipient);
            }

            $campaign->refreshCounts();

            return;
        }

        $byRecipient = [];
        foreach ($results as $result) {
            $byRecipient[$result->recipientId] = $result;
        }

        foreach ($recipients as $recipient) {
            $result = $byRecipient[$recipient->id] ?? new GatewaySendResult(
                recipientId: $recipient->id,
                phone: $recipient->phone,
                accepted: false,
                timedOut: true,
                code: 'timeout',
                raw: 'missing-result',
            );
            $this->applyResult($campaign, $recipient, $result);
        }

        $campaign->refreshCounts();
    }

    public function releaseDueScheduled(): int
    {
        $ids = Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $released = 0;
        foreach ($ids as $id) {
            $queued = DB::transaction(function () use ($id): ?Campaign {
                /** @var Campaign|null $campaign */
                $campaign = Campaign::query()->whereKey($id)->lockForUpdate()->first();
                if ($campaign === null || $campaign->status !== CampaignStatus::Scheduled) {
                    return null;
                }

                $campaign->loadMissing('customer');
                if ($campaign->customer?->isSuspended()) {
                    return null;
                }

                $campaign->status = CampaignStatus::Queued;
                $campaign->queued_at = now();
                $campaign->save();
                $campaign->recipients()
                    ->where('status', CampaignRecipientStatus::Pending)
                    ->update(['status' => CampaignRecipientStatus::Queued]);

                return $campaign;
            });

            if ($queued === null) {
                continue;
            }

            $this->dispatch($queued);
            $released++;
        }

        return $released;
    }

    public function cancelScheduled(Customer $customer, Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($customer, $campaign): Campaign {
            /** @var Campaign $locked */
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($locked->customer_id !== $customer->id) {
                abort(404);
            }

            if ($locked->status !== CampaignStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'campaign' => 'Only a scheduled campaign that has not started can be cancelled.',
                ]);
            }

            $locked->status = CampaignStatus::Cancelled;
            $locked->save();

            $quantity = $locked->total_segments;
            if ($quantity > 0) {
                $this->ledger->refundUsage(
                    $customer,
                    $quantity,
                    "Cancelled scheduled campaign {$locked->name}",
                    'REF-CAMP-'.$locked->id.'-CANCEL',
                );
            }

            return $locked->fresh(['message']) ?? $locked;
        });
    }

    /**
     * @return list<int>
     */
    private function lockRecipientsForSend(Campaign $campaign): array
    {
        $provider = Provider::current();
        $ids = [];
        $now = now();

        $campaign->recipients()
            ->with('delivery')
            ->where('status', CampaignRecipientStatus::Queued)
            ->orderBy('id')
            ->chunkById(500, function ($recipients) use ($provider, $now, &$ids): void {
                $insert = [];
                $updateIds = [];

                foreach ($recipients as $recipient) {
                    $delivery = $recipient->delivery;
                    if ($delivery?->isLockedAgainstRetry()) {
                        continue;
                    }

                    if ($delivery !== null) {
                        $updateIds[] = $delivery->id;
                    } else {
                        $insert[] = [
                            'campaign_recipient_id' => $recipient->id,
                            'provider_id' => $provider->id,
                            'status' => DeliveryStatus::Sending->value,
                            'attempted_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $ids[] = $recipient->id;
                }

                if ($insert !== []) {
                    Delivery::query()->insertOrIgnore($insert);
                }

                if ($updateIds !== []) {
                    Delivery::query()
                        ->whereIn('id', $updateIds)
                        ->update([
                            'provider_id' => $provider->id,
                            'status' => DeliveryStatus::Sending->value,
                            'attempted_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            });

        return $ids;
    }

    private function applyResult(Campaign $campaign, CampaignRecipient $recipient, GatewaySendResult $result): void
    {
        $delivery = $recipient->delivery ?? $recipient->delivery()->first();

        if ($result->timedOut) {
            $this->markUnknown($recipient, $result);

            return;
        }

        if ($result->accepted) {
            $recipient->status = CampaignRecipientStatus::Sent;
            $recipient->save();

            if ($delivery !== null) {
                $delivery->status = DeliveryStatus::Submitted;
                $delivery->provider_message_id = $result->providerMessageId;
                $delivery->provider_response_code = $result->code;
                $delivery->provider_response = $result->raw;
                $delivery->sent_at = now();
                $delivery->save();
            }

            if ($result->providerMessageId) {
                $this->deliveryReports->simulateFake($result->providerMessageId, $recipient->phone);
                $recipient->refresh();
            }

            return;
        }

        $recipient->status = CampaignRecipientStatus::Failed;
        $recipient->save();

        if ($delivery !== null) {
            $delivery->status = DeliveryStatus::Failed;
            $delivery->provider_response_code = $result->code;
            $delivery->provider_response = $result->raw;
            $delivery->save();
        }

        $this->refundFailedRecipient($campaign, $recipient);
    }

    private function markUnknown(CampaignRecipient $recipient, ?GatewaySendResult $result = null): void
    {
        $recipient->status = CampaignRecipientStatus::Unknown;
        $recipient->save();

        $delivery = $recipient->delivery ?? $recipient->delivery()->first();
        if ($delivery === null) {
            return;
        }

        $delivery->status = DeliveryStatus::Unknown;
        $delivery->provider_response_code = $result?->code ?? 'timeout';
        $delivery->provider_response = $result?->raw ?? 'timeout';
        $delivery->save();
    }

    private function refundFailedRecipient(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $customer = $campaign->customer;
        if ($customer === null) {
            return;
        }

        $this->ledger->refundUsage(
            $customer,
            $recipient->segment_count,
            "Provider rejected {$recipient->phone} for campaign {$campaign->name}",
            'REF-CAMP-'.$campaign->id.'-R'.$recipient->id,
        );
    }

    private function refreshCounts(Campaign $campaign): Campaign
    {
        $campaign->refreshCounts();
        $this->webhooks->campaignCompleted($campaign);

        return $campaign->fresh(['message']) ?? $campaign;
    }
}
