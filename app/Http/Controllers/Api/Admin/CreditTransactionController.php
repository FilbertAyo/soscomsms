<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CreditLedger;
use App\Support\PaginationMeta;
use App\Support\ReceiptData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = CreditTransaction::query()
            ->with(['customer.user', 'reversal', 'payment'])
            ->when(
                $request->filled('customer_id'),
                fn ($query) => $query->where('customer_id', $request->integer('customer_id'))
            )
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 20));

        return response()->json([
            'transactions' => $page->getCollection()->map->toApiArray()->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }

    public function reverse(
        Request $request,
        CreditTransaction $creditTransaction,
        CreditLedger $ledger,
        AuditLogger $audit,
    ): JsonResponse {
        $reversal = $ledger->reverse($creditTransaction);

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::CreditsReversed,
            subject: $reversal,
            customer: $creditTransaction->customer,
            payload: [
                'quantity' => abs($reversal->quantity),
                'original_id' => $creditTransaction->id,
            ],
        );

        return response()->json([
            'transaction' => $reversal->fresh(['customer.user', 'reversal'])?->toApiArray(),
            'original' => $creditTransaction->fresh('reversal')?->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function receipt(CreditTransaction $creditTransaction, ReceiptData $receipts): JsonResponse
    {
        return response()->json([
            'receipt' => $receipts->forCreditTransaction($creditTransaction),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
