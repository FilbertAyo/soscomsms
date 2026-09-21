<?php

namespace App\Console\Commands;

use App\Contracts\SmsGatewayInterface;
use App\Enums\DeliveryStatus;
use App\Enums\GatewayDriver;
use App\Models\Delivery;
use App\Services\DeliveryReportService;
use App\Services\Gateway\ProviderDeliveryStatus;
use Illuminate\Console\Command;

class PollDeliveryReportsCommand extends Command
{
    protected $signature = 'sms:poll-delivery-reports';

    protected $description = 'Pull Mobishastra DLR status for submitted messages that are not yet delivered or failed.';

    public function handle(SmsGatewayInterface $gateway, DeliveryReportService $reports): int
    {
        if ((string) config('sms.gateway') !== GatewayDriver::Mobishastra->value) {
            $this->info('Skipped. DLR polling runs only when SMS_GATEWAY=mobishastra.');

            return self::SUCCESS;
        }

        $deliveries = Delivery::query()
            ->whereNotNull('provider_message_id')
            ->where('provider_message_id', '!=', '')
            ->whereIn('status', [
                DeliveryStatus::Pending,
                DeliveryStatus::Sending,
                DeliveryStatus::Submitted,
                DeliveryStatus::Unknown,
            ])
            ->with('campaignRecipient')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->filter(function (Delivery $delivery): bool {
                $status = $delivery->campaignRecipient?->status;

                return $status === null || ! ProviderDeliveryStatus::isTerminal($status);
            });

        $updated = 0;
        foreach ($deliveries as $delivery) {
            $messageId = (string) $delivery->provider_message_id;
            $report = $gateway->checkDeliveryStatus($messageId);
            $applied = $reports->apply(
                $report->providerMessageId !== '' ? $report->providerMessageId : $messageId,
                $report->status,
                $delivery->campaignRecipient?->phone,
                $report->occurredAt,
            );

            $normalized = ProviderDeliveryStatus::normalize($report->status);
            if ($applied !== null && $normalized !== null && ProviderDeliveryStatus::isTerminal($normalized)) {
                $updated++;
            }
        }

        $this->info('Checked '.$deliveries->count().' messages. Updated '.$updated.'.');

        return self::SUCCESS;
    }
}
