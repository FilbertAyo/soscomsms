<?php

namespace App\Services;

use App\Contracts\SmsGatewayInterface;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GatewayDriver;
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
use App\Services\Gateway\ProviderDeliveryStatus;
use App\Support\AdminAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            return $campaign;
        }

        return $campaign;
    }

    /**
     * Resolve attempted recipients with no provider message id without retrying.
     * Credits stay charged (Mobishastra is source of truth); admins are alerted only.
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

        $resolved = 0;
        foreach ($ids as $id) {
            /** @var Campaign|null $campaign */
            $campaign = Campaign::query()->with(['customer', 'recipients.delivery'])->find($id);
            if ($campaign === null) {
                continue;
            }

            $didResolve = $this->resolveAmbiguousUnsent($campaign);
            if ($didResolve) {
                $resolved++;
            }
        }

        return $resolved;
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
     * Hold attempted-but-unconfirmed recipients as unknown: never resend, never
     * refund or debit again. Mobishastra remains the billing/delivery truth;
     * only admins are alerted so they can follow up with the customer if needed.
     */
    private function resolveAmbiguousUnsent(Campaign $campaign): bool
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

        $phones = [];

        foreach ($recipients as $recipient) {
            // Same treatment as a gateway timeout: keep the charge, do not retry.
            $this->markUnknown($recipient, new GatewaySendResult(
                recipientId: $recipient->id,
                phone: $recipient->phone,
                accepted: false,
                timedOut: true,
                code: 'ambiguous',
                raw: 'Ambiguous send held; Mobishastra is source of truth',
            ));
            $phones[] = $recipient->phone;
        }

        ProviderLog::query()->create([
            'provider_id' => Provider::current()->id,
            'campaign_id' => $campaign->id,
            'action' => 'ambiguous_held',
            'status' => 'ok',
            'error' => 'Ambiguous attempted recipients held without refund or retry',
        ]);

        $campaign->loadMissing('customer.user');
        $owner = $campaign->customer?->business_name
            ?: ($campaign->customer?->user?->name ?? 'a customer');
        $phoneList = implode(', ', $phones);

        AdminAlerts::technical(
            'ambiguous-send:'.$campaign->id,
            'Ambiguous SMS send — no credit change',
            'Campaign #'.$campaign->id.' ('.$campaign->name.') for '.$owner
                .' had ambiguous send(s) with no provider message id: '.$phoneList
                .'. Credits were left as charged (Mobishastra is source of truth).'
                .' Confirm delivery in Mobishastra if needed; the customer was not notified or refunded.',
        );

        $this->finishDispatch($campaign, notify: false);

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
        $campaign->refresh();

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

        $funded = [];
        foreach ($recipients as $recipient) {
            if (! $campaign->tryConsumeSegments($recipient->segment_count)) {
                $this->markCreditExhausted($campaign, $recipient);

                continue;
            }

            $funded[] = $recipient;
        }

        if ($funded === []) {
            $this->finishDispatch($campaign);

            return;
        }

        $messages = [];
        foreach ($funded as $recipient) {
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

            foreach ($funded as $recipient) {
                $this->markUnknown($recipient);
            }

            $this->finishDispatch($campaign);

            return;
        }

        $byRecipient = [];
        foreach ($results as $result) {
            $byRecipient[$result->recipientId] = $result;
        }

        foreach ($funded as $recipient) {
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

        $this->confirmSubmittedDeliveries($funded);
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

            $unused = $locked->remainingReservedSegments();
            if ($locked->credit_transaction_id !== null && $unused > 0) {
                $this->ledger->refundUsage(
                    $customer,
                    $unused,
                    "Cancelled scheduled campaign {$locked->name}",
                    'REF-CAMP-'.$locked->id.'-CANCEL',
                );
                $locked->consumed_segments = $locked->reserved_segments;
                $locked->save();
            }

            $locked->recipients()
                ->whereIn('status', [
                    CampaignRecipientStatus::Pending,
                    CampaignRecipientStatus::Queued,
                ])
                ->update(['status' => CampaignRecipientStatus::Failed]);

            return $locked->fresh(['message']) ?? $locked;
        });
    }

    /**
     * Atomically claim at most one chunk of never-attempted queued recipients.
     *
     * @return list<int>
     */
    private function claimNextChunk(Campaign $campaign, int $limit): array
    {
        return DB::transaction(function () use ($campaign, $limit): array {
            $provider = Provider::current();
            $ids = [];
            $now = now();
            $overFetch = max($limit * 3, $limit);

            $candidates = $campaign->recipients()
                ->where('status', CampaignRecipientStatus::Queued)
                ->whereDoesntHave('delivery')
                ->orderBy('id')
                ->limit($overFetch)
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $recipient) {
                if (count($ids) >= $limit) {
                    break;
                }

                $affected = Delivery::query()->insertOrIgnore([[
                    'campaign_recipient_id' => $recipient->id,
                    'provider_id' => $provider->id,
                    'status' => DeliveryStatus::Sending->value,
                    'attempted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]]);

                if ($affected < 1) {
                    continue;
                }

                $ids[] = $recipient->id;
            }

            return $ids;
        });
    }

    private function applyResult(Campaign $campaign, CampaignRecipient $recipient, GatewaySendResult $result): void
    {
        $delivery = $recipient->delivery ?? $recipient->delivery()->first();

        if ($result->timedOut) {
            // Keep consumed credits — the provider may have accepted the SMS.
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

    /**
     * Ask Mobishastra for DLR immediately after a small instant send.
     * Bulk campaigns are left to the minute poller so one request cannot
     * wait on a status call per recipient.
     *
     * @param  list<CampaignRecipient>  $recipients
     */
    private function confirmSubmittedDeliveries(array $recipients): void
    {
        if ((string) config('sms.gateway') !== GatewayDriver::Mobishastra->value) {
            return;
        }

        if (count($recipients) > 10) {
            return;
        }

        foreach ($recipients as $recipient) {
            $recipient->refresh();
            $delivery = $recipient->delivery()->first();
            $messageId = $delivery?->provider_message_id;

            if ($delivery === null || ! filled($messageId)) {
                continue;
            }

            if (ProviderDeliveryStatus::isSyntheticProviderId((string) $messageId)) {
                continue;
            }

            if (ProviderDeliveryStatus::isTerminal($recipient->status)) {
                continue;
            }

            try {
                $report = $this->gateway->checkDeliveryStatus((string) $messageId);
                $this->deliveryReports->apply(
                    (string) $messageId,
                    $report->status,
                    $recipient->phone,
                    $report->occurredAt,
                );
            } catch (Throwable $exception) {
                Log::warning('Immediate DLR check failed.', [
                    'message_id' => $messageId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function markCreditExhausted(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $recipient->status = CampaignRecipientStatus::Failed;
        $recipient->save();

        $delivery = $recipient->delivery ?? $recipient->delivery()->first();
        if ($delivery === null) {
            $delivery = Delivery::query()->create([
                'campaign_recipient_id' => $recipient->id,
                'provider_id' => Provider::current()->id,
                'status' => DeliveryStatus::Failed,
                'provider_response_code' => 'no_credits',
                'provider_response' => 'Campaign reservation exhausted before provider submit',
                'attempted_at' => now(),
            ]);
        } else {
            $delivery->status = DeliveryStatus::Failed;
            $delivery->provider_response_code = 'no_credits';
            $delivery->provider_response = 'Campaign reservation exhausted before provider submit';
            $delivery->save();
        }

        ProviderLog::query()->create([
            'provider_id' => Provider::current()->id,
            'campaign_id' => $campaign->id,
            'action' => 'send_bulk',
            'status' => 'blocked',
            'error' => "Skipped {$recipient->phone}: campaign reservation exhausted",
        ]);
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

    private function refundFailedRecipient(
        Campaign $campaign,
        CampaignRecipient $recipient,
        string $reason = 'Provider rejected',
    ): void {
        $customer = $campaign->customer ?? $campaign->customer()->first();
        if ($customer === null || $campaign->credit_transaction_id === null) {
            return;
        }

        if ($recipient->segment_count < 1) {
            return;
        }

        $this->ledger->refundUsage(
            $customer,
            $recipient->segment_count,
            "{$reason} {$recipient->phone} for campaign {$campaign->name}",
            'REF-CAMP-'.$campaign->id.'-R'.$recipient->id,
        );
    }

    private function finishDispatch(Campaign $campaign, bool $notify = false): Campaign
    {
        $campaign->refreshCounts();
        $this->releaseUnusedReservation($campaign);

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

    /**
     * Refund never-attempted reserved segments once the campaign is terminal.
     */
    private function releaseUnusedReservation(Campaign $campaign): void
    {
        $campaign->refresh();

        $shouldRelease = match ($campaign->status) {
            CampaignStatus::Completed,
            CampaignStatus::PartiallyDelivered,
            CampaignStatus::Failed,
            CampaignStatus::Cancelled => true,
            CampaignStatus::Queued,
            CampaignStatus::Sending,
            CampaignStatus::Scheduled,
            CampaignStatus::Draft => false,
        };

        if (! $shouldRelease) {
            return;
        }

        $unused = $campaign->remainingReservedSegments();
        if ($unused < 1 || $campaign->credit_transaction_id === null) {
            return;
        }

        $customer = $campaign->customer ?? $campaign->customer()->first();
        if ($customer === null) {
            return;
        }

        try {
            $this->ledger->refundUsage(
                $customer,
                $unused,
                "Unused reservation for campaign {$campaign->name}",
                'REF-CAMP-'.$campaign->id.'-UNUSED',
            );
        } catch (ValidationException) {
            return;
        }

        $campaign->consumed_segments = $campaign->reserved_segments;
        $campaign->save();
    }
}
