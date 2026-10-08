<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\SnippeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnippeWebhookController extends Controller
{
    public function store(Request $request, SnippeClient $snippe, PaymentService $payments): JsonResponse
    {
        $payload = $request->getContent();
        $valid = $snippe->signatureIsValid(
            $payload,
            (string) $request->header('X-Webhook-Timestamp', ''),
            (string) $request->header('X-Webhook-Signature', ''),
        );

        if (! $valid) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $payments->applyWebhook($event);

        return response()->json(['ok' => true]);
    }
}
