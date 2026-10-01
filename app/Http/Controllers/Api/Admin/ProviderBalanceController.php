<?php

namespace App\Http\Controllers\Api\Admin;

use App\Console\Commands\PollDeliveryReportsCommand;
use App\Contracts\SmsGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Provider;
use App\Services\DeliveryReportService;
use App\Services\Gateway\ProviderDeliveryStatus;
use App\Services\ProviderReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProviderBalanceController extends Controller
{
    public function show(SmsGatewayInterface $gateway, ProviderReadinessService $readiness): JsonResponse
    {
        $provider = Provider::current();
        $production = $readiness->productionProvider();
        $balance = $gateway->checkBalance();
        $customerCredits = (int) Customer::query()->sum('sms_balance');

        return response()->json([
            'driver' => $provider->driver->value,
            'name' => $provider->name,
            'smsBalance' => $balance->smsBalance,
            'customerCredits' => $customerCredits,
            'realSmsBalance' => $balance->smsBalance - $customerCredits,
            'parsed' => $balance->parsed,
            'parseError' => $balance->error,
            'rawResponse' => mb_substr($balance->raw, 0, 500),
            'lowBalanceThreshold' => $provider->low_balance_threshold,
            'lowBalance' => $balance->parsed && $balance->smsBalance < $provider->low_balance_threshold,
            'staticIp' => $production->static_ip,
            'staticIpWhitelistedAt' => $production->static_ip_whitelisted_at?->toIso8601String(),
            'baseUrl' => $production->base_url,
            'profileId' => $production->maskedProfileId(),
            'countryCode' => $production->countryCode(),
            'credentialsConfirmedAt' => $production->credentials_confirmed_at?->toIso8601String(),
            'checkedAt' => $provider->fresh()?->last_balance_at?->toIso8601String(),
            'dlrPoll' => Cache::get(PollDeliveryReportsCommand::HEARTBEAT_KEY),
        ]);
    }

    public function checkDlr(
        Request $request,
        SmsGatewayInterface $gateway,
        DeliveryReportService $reports,
    ): JsonResponse {
        $validated = $request->validate([
            'message_id' => ['required', 'string', 'max:64'],
        ]);

        $messageId = trim($validated['message_id']);
        $report = $gateway->checkDeliveryStatus($messageId);
        $normalized = ProviderDeliveryStatus::normalize($report->status);

        $delivery = Delivery::query()
            ->where('provider_message_id', $messageId)
            ->with('campaignRecipient')
            ->first();
        $applied = $delivery === null
            ? null
            : $reports->apply($messageId, $report->status, $delivery->campaignRecipient?->phone, $report->occurredAt);
        if ($delivery !== null) {
            Delivery::query()->whereKey($delivery->id)->update([
                'dlr_checked_at' => now(),
                'dlr_attempts' => $delivery->dlr_attempts + 1,
            ]);
        }
        $recipientStatus = $delivery?->campaignRecipient?->fresh()?->status;
        $provider = Provider::current();
        $path = (string) config('sms.provider.dlr_path', 'dlrstatus_api.aspx');
        $base = rtrim((string) (config('sms.provider.dlr_base_url') ?: $provider->base_url ?: config('sms.provider.base_url')), '/');

        return response()->json([
            'messageId' => $messageId,
            'rawResponse' => mb_substr($report->raw !== '' ? $report->raw : $report->status, 0, 1000),
            'dlrStatus' => $report->status,
            'dlrTime' => $report->occurredAt,
            'soscomStatus' => $normalized?->value,
            'requestUrl' => $path === '' ? '' : $base.'/'.ltrim($path, '/'),
            'driver' => $provider->driver->value,
            'matchedDelivery' => $delivery !== null,
            'saved' => $applied !== null,
            'reportStatus' => $recipientStatus?->value,
        ]);
    }
}
