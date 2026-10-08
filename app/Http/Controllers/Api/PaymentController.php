<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $page = $customer->payments()
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'payments' => $page->getCollection()->map->toApiArray()->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }

    public function show(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($payment->customer_id === $customer->id, 404);

        $payment = $payments->refresh($payment);

        return response()->json([
            'payment' => $payment->toApiArray(),
            'smsBalance' => $customer->fresh()?->sms_balance,
        ]);
    }

    public function store(Request $request, PaymentService $payments): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'payer_reference' => ['required', 'string', 'min:9', 'max:20'],
        ]);

        $payment = $payments->request($customer, $validated);

        return response()->json([
            'payment' => $payment->toApiArray(),
            'smsBalance' => $customer->fresh()?->sms_balance,
            'csrf_token' => csrf_token(),
        ], 201);
    }
}
