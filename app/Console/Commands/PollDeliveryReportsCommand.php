<?php

namespace App\Console\Commands;

use App\Contracts\SmsGatewayInterface;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\DeliveryStatus;
use App\Enums\GatewayDriver;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\DeliveryReportService;
use App\Services\Gateway\ProviderDeliveryStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollDeliveryReportsCommand extends Command
{
    public const HEARTBEAT_KEY = 'sms:dlr-poll:last-run';

    public const STARTED_KEY = 'sms:dlr-poll:last-start';

    public const LOCK_KEY = 'sms:dlr-poll:lock';

    protected $signature = 'sms:poll-delivery-reports
        {--budget= : Stop starting new checks after this many seconds}
        {--force : Release a lock left behind by a killed run}';

    protected $description = 'Pull Mobishastra DLR status for submitted messages that are not yet delivered or failed.';

    public function handle(SmsGatewayInterface $gateway, DeliveryReportService $reports): int
    {
        if ((string) config('sms.gateway') !== GatewayDriver::Mobishastra->value) {
            $this->info('Skipped. DLR polling runs only when SMS_GATEWAY=mobishastra.');

            return self::SUCCESS;
        }

        $budget = max((int) ($this->option('budget') ?: config('sms.provider.dlr_budget_seconds', 40)), 5);

        // Short-lived lock instead of the scheduler's 24-hour mutex: shared hosting
        // kills long cron processes, and a killed run must not block polling for a day.
        $lock = Cache::lock(self::LOCK_KEY, $budget + (int) config('sms.provider.dlr_timeout', 8) + 30);
        if ($this->option('force')) {
            $lock->forceRelease();
        }
        if (! $lock->get()) {
            $this->info('Skipped. Another DLR poll is still running.');

            return self::SUCCESS;
        }

        try {
            $this->poll($gateway, $reports, $budget);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function poll(SmsGatewayInterface $gateway, DeliveryReportService $reports, int $budget): void
    {
        Cache::forever(self::STARTED_KEY, now()->toIso8601String());

        $startedAt = microtime(true);
        $deliveries = $this->dueDeliveries();

        $checked = 0;
        $updated = 0;
        $errors = 0;
        foreach ($deliveries as $delivery) {
            if (microtime(true) - $startedAt >= $budget) {
                break;
            }

            $checked++;
            $messageId = (string) $delivery->provider_message_id;

            try {
                $report = $gateway->checkDeliveryStatus($messageId);
                $applied = $reports->apply(
                    $messageId,
                    $report->status,
                    $delivery->campaignRecipient?->phone,
                    $report->occurredAt,
                );

                $normalized = ProviderDeliveryStatus::normalize($report->status);
                if ($applied !== null && $normalized !== null && ProviderDeliveryStatus::isTerminal($normalized)) {
                    $updated++;
                }
            } catch (Throwable $exception) {
                $errors++;
                Log::warning('DLR poll failed for one message', [
                    'delivery_id' => $delivery->id,
                    'provider_message_id' => $messageId,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                Delivery::query()->whereKey($delivery->id)->update([
                    'dlr_checked_at' => now(),
                    'dlr_attempts' => $delivery->dlr_attempts + 1,
                ]);
            }
        }

        Campaign::query()
            ->where(function ($query): void {
                $query->where('status', CampaignStatus::Sending)
                    ->orWhereHas('recipients', function ($recipients): void {
                        $recipients->where('status', CampaignRecipientStatus::Unknown);
                    });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->each(fn (Campaign $campaign) => $campaign->refreshCounts());

        Cache::forever(self::HEARTBEAT_KEY, [
            'at' => now()->toIso8601String(),
            'checked' => $checked,
            'updated' => $updated,
            'errors' => $errors,
            'waiting' => $this->waitingQuery()->count(),
            'seconds' => (int) round(microtime(true) - $startedAt),
        ]);

        $this->info("Checked {$checked} messages. Updated {$updated}.".($errors > 0 ? " Errors {$errors}." : ''));
    }

    /**
     * Least-recently-checked first, so every waiting message gets a turn
     * and old messages that never resolve cannot block new ones.
     *
     * @return \Illuminate\Support\Collection<int, Delivery>
     */
    private function dueDeliveries()
    {
        $recheckBefore = now()->subSeconds(max((int) config('sms.provider.dlr_recheck_seconds', 60), 0));

        return $this->waitingQuery()
            ->where(function (Builder $query) use ($recheckBefore): void {
                $query->whereNull('dlr_checked_at')
                    ->orWhere('dlr_checked_at', '<=', $recheckBefore);
            })
            ->with('campaignRecipient')
            ->orderByRaw('dlr_checked_at is null desc')
            ->orderBy('dlr_checked_at')
            ->orderByDesc('id')
            ->limit(max((int) config('sms.provider.dlr_batch', 200), 1))
            ->get();
    }

    /**
     * @return Builder<Delivery>
     */
    private function waitingQuery(): Builder
    {
        $cutoff = now()->subHours(max((int) config('sms.provider.dlr_max_age_hours', 72), 1));

        return Delivery::query()
            ->whereNotNull('provider_message_id')
            ->where('provider_message_id', '!=', '')
            ->where('provider_message_id', 'not like', 'MSH-R%')
            ->whereIn('status', [
                DeliveryStatus::Pending,
                DeliveryStatus::Sending,
                DeliveryStatus::Submitted,
                DeliveryStatus::Unknown,
            ])
            ->whereHas('campaignRecipient', function (Builder $query): void {
                $query->whereNotIn('status', [
                    CampaignRecipientStatus::Delivered->value,
                    CampaignRecipientStatus::Failed->value,
                    CampaignRecipientStatus::Rejected->value,
                ]);
            })
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('sent_at', '>=', $cutoff)
                    ->orWhere(function (Builder $unsent) use ($cutoff): void {
                        $unsent->whereNull('sent_at')->where('created_at', '>=', $cutoff);
                    });
            });
    }
}
