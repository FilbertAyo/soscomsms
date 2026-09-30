<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignRecipientStatus;
use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryReportController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $reports = $this->filtered($customer, $request)
            ->paginate(PaginationMeta::perPage($request, 10, 500));

        return response()->json([
            'reports' => $reports->getCollection()->map->toReportArray()->values(),
            'meta' => PaginationMeta::from($reports),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->streamDownload(function () use ($customer, $request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Recipient',
                'Campaign',
                'Sender ID',
                'Message',
                'Status',
                'Sent at',
                'Delivered at',
            ]);

            $exported = 0;
            $this->filtered($customer, $request)
                ->lazyById(200)
                ->each(function (Delivery $row) use ($handle, &$exported): mixed {
                    if ($exported >= 10000) {
                        return false;
                    }

                    $report = $row->toReportArray();
                    fputcsv($handle, [
                        $report['phone'],
                        $report['campaignName'],
                        $report['senderId'],
                        $report['message'],
                        $report['status'] === CampaignRecipientStatus::Unknown->value
                            ? CampaignRecipientStatus::Sent->value
                            : $report['status'],
                        $report['sentAt'],
                        $report['deliveredAt'],
                    ]);
                    $exported++;

                    return null;
                });

            fclose($handle);
        }, 'delivery-reports.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
            ->when($request->filled('sender_id'), function (Builder $query) use ($request): void {
                $query->whereHas('campaignRecipient.campaign', function (Builder $inner) use ($request): void {
                    $inner->where('sender_name', $request->string('sender_id')->toString());
                });
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $status = $request->string('status')->toString();
                $statuses = $status === CampaignRecipientStatus::Sent->value
                    ? [CampaignRecipientStatus::Sent->value, CampaignRecipientStatus::Unknown->value]
                    : [$status];

                $query->whereHas('campaignRecipient', function (Builder $inner) use ($statuses): void {
                    $inner->whereIn('status', $statuses);
                });
            })
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim($request->string('q')->toString());
                $digits = preg_replace('/\D+/', '', $term) ?? '';
                if (strlen($term) < 3 && strlen($digits) < 3) {
                    return;
                }

                $tail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
                $query->whereHas('campaignRecipient', function (Builder $inner) use ($term, $tail, $digits): void {
                    $inner->where(function (Builder $phones) use ($term, $tail, $digits): void {
                        if (strlen($digits) >= 3) {
                            $phones->where('phone', 'like', '%'.$tail);
                        } else {
                            $phones->where('phone', 'like', $term.'%');
                        }
                    });
                });
            })
            ->when($request->filled('date'), function (Builder $query) use ($request): void {
                $query->whereDate('sent_at', $request->date('date'));
            })
            ->latest('id');
    }
}
