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

    protected $signature = 'sms:poll-delivery-reports';

    protected $description = 'Pull Mobishastra DLR status for submitted messages that are not yet delivered or failed.';

    public function handle(SmsGatewayInterface $gateway, DeliveryReportService $reports): int
    {
        if ((string) config('sms.gateway') !== GatewayDriver::Mobishastra->value) {
            $this->info('Skipped. DLR polling runs only when SMS_GATEWAY=mobishastra.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $budget = max((int) config('sms.provider.dlr_budget_seconds', 45), 5);
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
        ]);

        $this->info("Checked {$checked} messages. Updated {$updated}.".($errors > 0 ? " Errors {$errors}." : ''));

        return self::SUCCESS;
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
