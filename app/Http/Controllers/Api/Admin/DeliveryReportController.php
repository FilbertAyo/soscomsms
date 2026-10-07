<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $reports = Delivery::query()
            ->with(['campaignRecipient.campaign.customer.user', 'campaignRecipient.message'])
            ->when($term !== '', fn (Builder $query) => $this->search($query, $term))
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 10, 200));

        return response()->json([
            'reports' => $reports->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($reports),
        ]);
    }

    /**
     * Matches message ID, phone, Sender ID, campaign, or customer name, business, or email.
     *
     * @param  Builder<Delivery>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';
        $digits = ltrim((string) preg_replace('/\D+/', '', $term), '0');

        $query->where(function (Builder $inner) use ($like, $digits): void {
            $inner->where('provider_message_id', 'like', $like)
                ->orWhereHas('campaignRecipient', function (Builder $recipient) use ($like, $digits): void {
                    $recipient->where(function (Builder $match) use ($like, $digits): void {
                        $match->where('phone', 'like', $like);
                        if (strlen($digits) >= 4) {
                            $match->orWhere('phone', 'like', '%'.$digits.'%');
                        }
                    })->orWhereHas('campaign', function (Builder $campaign) use ($like): void {
                        $campaign->where('sender_name', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhereHas('customer', function (Builder $customer) use ($like): void {
                                $customer->where('business_name', 'like', $like)
                                    ->orWhereHas('user', function (Builder $user) use ($like): void {
                                        $user->where('name', 'like', $like)
                                            ->orWhere('email', 'like', $like);
                                    });
                            });
                    });
                });
        });
    }
}
