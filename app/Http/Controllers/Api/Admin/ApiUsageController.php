<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Customer;
use App\Models\WebhookDelivery;
use App\Support\PaginationMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiUsageController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $term = $this->term($request);

        $logs = ApiLog::query()
            ->with(['customer.user', 'apiKey'])
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($term, $like): void {
                    $inner->where('path', 'like', $like)
                        ->orWhere('method', 'like', $like)
                        ->orWhere('request_id', 'like', $like)
                        ->orWhere('ip', 'like', $like)
                        ->orWhere('error', 'like', $like)
                        ->orWhereHas('apiKey', fn (Builder $key) => $key
                            ->where('name', 'like', $like)
                            ->orWhere('key_prefix', 'like', $like))
                        ->orWhereHas('customer', fn (Builder $customer) => $this->matchCustomer($customer, $like));

                    if (ctype_digit($term)) {
                        $inner->orWhere('status_code', (int) $term)
                            ->orWhere('campaign_id', (int) $term);
                    }
                });
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 20));

        return response()->json([
            'logs' => $logs->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($logs),
        ]);
    }

    public function keys(Request $request): JsonResponse
    {
        $term = $this->term($request);

        $keys = ApiKey::query()
            ->with('customer.user')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('key_prefix', 'like', $like)
                        ->orWhereHas('customer', fn (Builder $customer) => $this->matchCustomer($customer, $like));
                });
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 20));

        return response()->json([
            'keys' => $keys->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($keys),
        ]);
    }

    public function webhookDeliveries(Request $request): JsonResponse
    {
        $term = $this->term($request);

        $deliveries = WebhookDelivery::query()
            ->with(['customer.user', 'webhook'])
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($term, $like): void {
                    $inner->where('event', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhere('error', 'like', $like)
                        ->orWhereHas('webhook', fn (Builder $webhook) => $webhook
                            ->where('name', 'like', $like)
                            ->orWhere('url', 'like', $like))
                        ->orWhereHas('customer', fn (Builder $customer) => $this->matchCustomer($customer, $like));

                    if (ctype_digit($term)) {
                        $inner->orWhere('http_status', (int) $term)
                            ->orWhere('campaign_id', (int) $term);
                    }
                });
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 20));

        return response()->json([
            'webhookDeliveries' => $deliveries->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($deliveries),
        ]);
    }

    private function term(Request $request): string
    {
        return trim($request->string('q')->toString());
    }

    /**
     * @param  Builder<Customer>  $customer
     */
    private function matchCustomer(Builder $customer, string $like): void
    {
        $customer->where('business_name', 'like', $like)
            ->orWhereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like));
    }
}
