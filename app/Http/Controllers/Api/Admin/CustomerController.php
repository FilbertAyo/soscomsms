<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Enums\CampaignStatus;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\AdminMetrics;
use App\Services\AuditLogger;
use App\Services\CreditLedger;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();
        $term = trim($request->string('q')->toString());

        $customers = Customer::query()
            ->with('user')
            ->withSum(
                [
                    'campaigns as sent_this_month' => function (Builder $query) use ($startOfMonth): void {
                        $query->whereNotIn('status', [
                            CampaignStatus::Draft->value,
                            CampaignStatus::Cancelled->value,
                        ])->where('created_at', '>=', $startOfMonth);
                    },
                ],
                'total_segments',
            )
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('business_name', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhereHas('user', function (Builder $users) use ($term): void {
                            $users->where('name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%');
                        });
                });
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'customers' => $customers->getCollection()
                ->map(fn (Customer $customer): array => $customer->toAdminArray(
                    sentThisMonth: (int) ($customer->sent_this_month ?? 0),
                ))
                ->values(),
            'meta' => PaginationMeta::from($customers),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();
        $sentThisMonth = (int) $customer->campaigns()
            ->whereNotIn('status', [
                CampaignStatus::Draft->value,
                CampaignStatus::Cancelled->value,
            ])
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total_segments');

        return response()->json([
            'customer' => $customer->toAdminArray(
                sentThisMonth: $sentThisMonth,
                campaignCount: $customer->campaigns()->count(),
            ),
        ]);
    }

    public function grantCredits(
        Request $request,
        Customer $customer,
        CreditLedger $ledger,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
            'amount_tzs' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'unit_price_tzs' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $transaction = $ledger->grant(
            $customer,
            (int) $validated['quantity'],
            $validated['note'] ?? null,
            isset($validated['amount_tzs']) ? (int) $validated['amount_tzs'] : null,
            isset($validated['unit_price_tzs']) ? (int) $validated['unit_price_tzs'] : null,
        );

        app(AdminMetrics::class)->forget();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::CreditsGranted,
            subject: $transaction,
            customer: $customer,
            payload: [
                'quantity' => (int) $validated['quantity'],
                'amount_tzs' => $transaction->amount_tzs,
                'unit_price_tzs' => $transaction->customer_unit_price,
                'note' => $validated['note'] ?? null,
            ],
        );

        return response()->json([
            'transaction' => $transaction->fresh('reversal')?->toApiArray(),
            'smsBalance' => $customer->fresh()?->sms_balance,
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function suspend(Request $request, Customer $customer, AuditLogger $audit): JsonResponse
    {
        if ($customer->status === CustomerStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => 'This customer is already suspended.',
            ]);
        }

        $previous = $customer->status;
        $customer->forceFill(['status' => CustomerStatus::Suspended])->save();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::CustomerSuspended,
            subject: $customer,
            customer: $customer,
            payload: ['previous_status' => $previous->value],
        );

        return response()->json([
            'customer' => $customer->fresh('user')?->toAdminArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function unsuspend(Request $request, Customer $customer, AuditLogger $audit): JsonResponse
    {
        if ($customer->status !== CustomerStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => 'This customer is not suspended.',
            ]);
        }

        $complete = filled($customer->business_name) && filled($customer->phone);
        $customer->forceFill([
            'status' => $complete ? CustomerStatus::Active : CustomerStatus::PendingProfile,
        ])->save();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::CustomerUnsuspended,
            subject: $customer,
            customer: $customer,
            payload: ['status' => $customer->status->value],
        );

        return response()->json([
            'customer' => $customer->fresh('user')?->toAdminArray(),
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
