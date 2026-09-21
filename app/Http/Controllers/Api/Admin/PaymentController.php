<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PaymentService;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = Payment::query()
            ->with(['customer.user'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
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

    public function confirm(
        Request $request,
        Payment $payment,
        PaymentService $payments,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $payments->confirm($payment, $this->admin($request), $validated['note'] ?? null);

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::PaymentConfirmed,
            subject: $updated,
            customer: $updated->customer,
            payload: [
                'quantity' => $updated->quantity,
                'amount_tzs' => $updated->amount_tzs,
                'reference' => 'PAY-'.$updated->id,
            ],
        );

        return response()->json([
            'payment' => $updated->toApiArray(),
            'smsBalance' => $updated->customer?->sms_balance,
            'csrf_token' => csrf_token(),
        ]);
    }

    public function reject(
        Request $request,
        Payment $payment,
        PaymentService $payments,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $updated = $payments->reject($payment, $this->admin($request), $validated['rejection_reason']);

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::PaymentRejected,
            subject: $updated,
            customer: $updated->customer,
            payload: [
                'reason' => $validated['rejection_reason'],
            ],
        );

        return response()->json([
            'payment' => $updated->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
