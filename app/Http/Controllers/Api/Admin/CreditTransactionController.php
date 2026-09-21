<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CreditLedger;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = CreditTransaction::query()
            ->with(['customer.user', 'reversal'])
            ->when(
                $request->filled('customer_id'),
                fn ($query) => $query->where('customer_id', $request->integer('customer_id'))
            )
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

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

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
