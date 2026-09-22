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

        $chunkSize = max(1, (int) config('sms.provider.bulk_chunk', 100));
        $budgetSeconds = max(5, (int) config('sms.provider.dispatch_budget_seconds', 45));
        $deadline = microtime(true) + $budgetSeconds;
        $sentAny = false;

        while (microtime(true) < $deadline) {
            $chunkIds = $this->claimNextChunk($campaign, $chunkSize);
            if ($chunkIds === []) {
                break;
            }

            $this->dispatchChunk($campaign, $chunkIds);
            $sentAny = true;
        }

        $campaign = $this->finishDispatch($campaign, notify: false);
        $stillPending = $campaign->recipients()
            ->whereIn('status', [
                CampaignRecipientStatus::Queued,
                CampaignRecipientStatus::Pending,
            ])
            ->exists();

        if (! $stillPending) {
            return $this->finishDispatch($campaign, notify: true);
        }

        if (! $sentAny) {
            // All remaining queued rows are still inside the in-flight grace window.
            return $campaign;
        }

        return $campaign;
    }

    /**
     * Cancel remaining unsent recipients that were already attempted (no provider id).
     * Never resend those — retries can duplicate SMS at the gateway.
     */
    public function cancelStuckSending(): int
    {
        $ids = Campaign::query()
            ->where('status', CampaignStatus::Sending)
            ->whereHas('recipients', function ($query): void {
                $query->whereIn('status', [
                    CampaignRecipientStatus::Queued,
                    CampaignRecipientStatus::Pending,
                ])->whereHas('delivery', function ($delivery): void {
                    $delivery->whereNotNull('attempted_at')
                        ->where(function ($q): void {
                            $q->whereNull('provider_message_id')
                                ->orWhere('provider_message_id', '');
                        });
                });
            })
            ->orderBy('id')
            ->pluck('id');

        $cancelled = 0;
        foreach ($ids as $id) {
            /** @var Campaign|null $campaign */
            $campaign = Campaign::query()->with(['customer', 'recipients.delivery'])->find($id);
            if ($campaign === null) {
                continue;
            }

            $didCancel = $this->cancelAmbiguousUnsent($campaign);
            if ($didCancel) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    /**
     * Continue only never-attempted queued recipients (no delivery row yet).
     */
    public function resumeStuckSending(): int
    {
        $ids = Campaign::query()
            ->where('status', CampaignStatus::Sending)
            ->whereHas('recipients', function ($query): void {
                $query->where('status', CampaignRecipientStatus::Queued)
                    ->whereDoesntHave('delivery');
            })
            ->orderBy('id')
            ->pluck('id');

        $resumed = 0;
        foreach ($ids as $id) {
            /** @var Campaign|null $campaign */
            $campaign = Campaign::query()->with('customer')->find($id);
            if ($campaign === null || $campaign->customer?->isSuspended()) {
                continue;
            }

            $before = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Queued)
                ->whereDoesntHave('delivery')
                ->count();

            if ($before < 1) {
                continue;
            }

            $this->dispatch($campaign);

            $after = CampaignRecipient::query()
                ->where('campaign_id', $id)
                ->where('status', CampaignRecipientStatus::Queued)
                ->whereDoesntHave('delivery')
                ->count();

            if ($after < $before) {
                $resumed++;
            }
        }

        return $resumed;
    }

    /**
     * Mark attempted-but-unconfirmed queued recipients as failed without sending.
     */
    private function cancelAmbiguousUnsent(Campaign $campaign): bool
    {
        $recipients = $campaign->recipients()
            ->with('delivery')
            ->whereIn('status', [
                CampaignRecipientStatus::Queued,
                CampaignRecipientStatus::Pending,
            ])
            ->whereHas('delivery', function ($delivery): void {
                $delivery->whereNotNull('attempted_at')
                    ->where(function ($q): void {
                        $q->whereNull('provider_message_id')
                            ->orWhere('provider_message_id', '');
                    });
            })
            ->orderBy('id')
            ->get();

        if ($recipients->isEmpty()) {
            return false;
        }

        $now = now();

        foreach ($recipients as $recipient) {
            $recipient->status = CampaignRecipientStatus::Failed;
            $recipient->save();

            $delivery = $recipient->delivery;
            if ($delivery !== null) {
                $delivery->status = DeliveryStatus::Failed;
                $delivery->provider_response_code = 'cancelled';
                $delivery->provider_response = 'Cancelled to prevent duplicate SMS';
                $delivery->updated_at = $now;
                $delivery->save();
            }
        }

        ProviderLog::query()->create([
            'provider_id' => Provider::current()->id,
            'campaign_id' => $campaign->id,
            'action' => 'cancel_stuck',
            'status' => 'ok',
            'error' => 'Cancelled attempted unsent recipients to stop duplicate SMS',
        ]);

        $this->finishDispatch($campaign, notify: true);

        return true;
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

            $this->finishDispatch($campaign);

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

        $this->finishDispatch($campaign);
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
            if ($locked->credit_transaction_id !== null && $quantity > 0) {
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
     * Lock at most one chunk of unlocked queued recipients for the next send.
     * Locking per chunk (instead of the whole campaign) keeps later rows retryable
     * if the process dies mid-send.
     *
     * @return list<int>
     */
    private function claimNextChunk(Campaign $campaign, int $limit): array
    {
        $provider = Provider::current();
        $ids = [];
        $now = now();
        $overFetch = max($limit * 3, $limit);

        $candidates = $campaign->recipients()
            ->with('delivery')
            ->where('status', CampaignRecipientStatus::Queued)
            ->orderBy('id')
            ->limit($overFetch)
            ->get();

        $insert = [];
        $updateIds = [];

        foreach ($candidates as $recipient) {
            if (count($ids) >= $limit) {
                break;
            }

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
        if ($customer === null || $campaign->credit_transaction_id === null) {
            return;
        }

        $this->ledger->refundUsage(
            $customer,
            $recipient->segment_count,
            "Provider rejected {$recipient->phone} for campaign {$campaign->name}",
            'REF-CAMP-'.$campaign->id.'-R'.$recipient->id,
        );
    }

    private function finishDispatch(Campaign $campaign, bool $notify = false): Campaign
    {
        $campaign->refreshCounts();
        $this->settleUsage($campaign);

        if ($notify && ! in_array($campaign->status, [
            CampaignStatus::Queued,
            CampaignStatus::Sending,
            CampaignStatus::Scheduled,
            CampaignStatus::Draft,
        ], true)) {
            $this->webhooks->campaignCompleted($campaign);
        }

        return $campaign->fresh(['message']) ?? $campaign;
    }

    private function settleUsage(Campaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->credit_transaction_id !== null) {
            return;
        }

        $shouldSettle = match ($campaign->status) {
            CampaignStatus::Completed,
            CampaignStatus::PartiallyDelivered,
            CampaignStatus::Failed => true,
            CampaignStatus::Queued,
            CampaignStatus::Sending,
            CampaignStatus::Scheduled,
            CampaignStatus::Draft,
            CampaignStatus::Cancelled => false,
        };

        if (! $shouldSettle) {
            return;
        }

        $customer = $campaign->customer ?? $campaign->customer()->first();
        if ($customer === null) {
            return;
        }

        $accepted = (int) $campaign->recipients()
            ->where(function ($query): void {
                $query->whereIn('status', [
                    CampaignRecipientStatus::Sent,
                    CampaignRecipientStatus::Delivered,
                    CampaignRecipientStatus::Unknown,
                ])->orWhereHas('delivery', function ($delivery): void {
                    $delivery->whereNotNull('sent_at');
                });
            })
            ->sum('segment_count');

        if ($accepted < 1) {
            return;
        }

        try {
            $transaction = $this->ledger->debitUsage(
                $customer,
                $accepted,
                "Campaign {$campaign->name}",
                'USE-CAMP-'.$campaign->id,
            );
        } catch (ValidationException) {
            return;
        }

        $campaign->credit_transaction_id = $transaction->id;
        $campaign->save();
    }
}
