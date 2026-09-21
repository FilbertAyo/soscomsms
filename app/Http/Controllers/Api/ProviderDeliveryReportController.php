<?php

namespace App\Http\Controllers\Api;

use App\Enums\GatewayDriver;
use App\Http\Controllers\Controller;
use App\Services\DeliveryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderDeliveryReportController extends Controller
{
    public function store(Request $request, DeliveryReportService $reports): JsonResponse
    {
        if (! $this->tokenMatches($request)) {
            return response()->json(['message' => 'Unauthorized delivery report.'], 401);
        }

        $messageId = trim((string) (
            $request->input('msg_id')
            ?? $request->input('msgid')
            ?? $request->input('messageid')
            ?? $request->input('provider_message_id')
            ?? ''
        ));
        $status = trim((string) (
            $request->input('status')
            ?? $request->input('stat')
            ?? $request->input('DLR_Status')
            ?? $request->input('dlr_status')
            ?? ''
        ));
        $phone = $request->input('mobileno')
            ?? $request->input('number')
            ?? $request->input('phone');
        $occurredAt = $request->input('DLR_Time')
            ?? $request->input('dlr_time')
            ?? $request->input('time');

        if ($messageId === '' || $status === '') {
            return response()->json([
                'ok' => false,
                'message' => 'msg_id and status are required.',
            ], 422);
        }

        $delivery = $reports->apply(
            $messageId,
            $status,
            is_string($phone) ? $phone : null,
            is_string($occurredAt) ? $occurredAt : null,
        );

        return response()->json([
            'ok' => true,
            'matched' => $delivery !== null,
        ]);
    }

    private function tokenMatches(Request $request): bool
    {
        $expected = (string) config('sms.provider.dlr_token', '');
        if ($expected === '') {
            return (string) config('sms.gateway', GatewayDriver::Fake->value) === GatewayDriver::Fake->value;
        }

        $provided = (string) (
            $request->header('X-Soscom-Provider-Token')
            ?? $request->input('token')
            ?? ''
        );

        return hash_equals($expected, $provided);
    }
}
