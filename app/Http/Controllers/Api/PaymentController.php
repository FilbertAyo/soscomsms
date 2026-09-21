<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
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
            'lipaNamba' => (string) config('sms.billing.lipa_namba', '123456'),
            'lipaNambaName' => (string) config(
                'sms.billing.lipa_namba_name',
                'SOSCOM TECHNOLOGIES COMPANY LIMITED',
            ),
        ]);
    }

    public function store(Request $request, PaymentService $payments): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'payer_reference' => ['required', 'string', 'min:3', 'max:80'],
        ]);

        $payment = $payments->request($customer, $validated);

        return response()->json([
            'payment' => $payment->toApiArray(),
            'smsBalance' => $customer->fresh()?->sms_balance,
            'csrf_token' => csrf_token(),
        ], 201);
    }
}
