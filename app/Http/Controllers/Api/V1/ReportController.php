<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ResolvesApiCustomer;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ResolvesApiCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->apiCustomer($request);
        $reports = $this->filtered($customer, $request)
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return response()->json([
            'reports' => $reports->getCollection()->map->toPublicArray()->values(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * @return Builder<Delivery>
     */
    private function filtered(Customer $customer, Request $request): Builder
    {
        return Delivery::query()
            ->with(['campaignRecipient.campaign', 'campaignRecipient.message'])
            ->whereHas('campaignRecipient.campaign', function (Builder $query) use ($customer): void {
                $query->where('customer_id', $customer->id);
            })
            ->when($request->filled('campaign_id'), function (Builder $query) use ($request): void {
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($request): void {
                    $inner->where('campaign_id', $request->integer('campaign_id'));
                });
            })
            ->when($request->filled('id'), function (Builder $query) use ($request): void {
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($request): void {
                    $inner->where('id', $request->integer('id'));
                });
            })
            ->when($request->filled('to') || $request->filled('q'), function (Builder $query) use ($request): void {
                $term = $request->filled('to')
                    ? $request->string('to')->toString()
                    : $request->string('q')->toString();
                $digits = preg_replace('/\D+/', '', $term) ?? '';
                $tail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($term, $tail): void {
                    $inner->where(function (Builder $phones) use ($term, $tail): void {
                        $phones->where('phone', 'like', '%'.$term.'%');
                        if ($tail !== '') {
                            $phones->orWhere('phone', 'like', '%'.$tail.'%');
                        }
                    });
                });
            })
            ->latest('id');
    }
}
