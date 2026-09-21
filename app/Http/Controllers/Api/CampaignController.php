<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\SegmentationService;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    use ResolvesCustomer;

    public function preview(Request $request, CampaignService $campaigns): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $this->validatedPreview($request);

        return response()->json($campaigns->preview($customer, $validated));
    }

    public function store(Request $request, CampaignService $campaigns): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $this->validatedPreview($request, requireSender: true);

        $campaign = $campaigns->create($customer, $validated);

        return response()->json([
            'campaign' => $campaign->toApiArray(false),
            'smsBalance' => $customer->fresh()?->sms_balance,
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function stats(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $campaigns = $customer->campaigns();

        return response()->json([
            'totalSms' => (int) (clone $campaigns)->sum('total_segments'),
            'delivered' => (int) (clone $campaigns)->sum('delivered_count'),
            'failed' => (int) (clone $campaigns)->sum('failed_count'),
            'campaignCount' => (clone $campaigns)->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $page = $customer->campaigns()
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'campaigns' => $page->getCollection()
                ->map(fn (Campaign $campaign): array => $campaign->toApiArray(false, false))
                ->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($campaign->customer_id === $customer->id, 404);

        return response()->json([
            'campaign' => $campaign->toApiArray(true),
        ]);
    }

    public function cancel(Request $request, Campaign $campaign, CampaignDispatcher $dispatcher): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($campaign->customer_id === $customer->id, 404);

        $cancelled = $dispatcher->cancelScheduled($customer, $campaign);

        return response()->json([
            'campaign' => $cancelled->toApiArray(true),
            'smsBalance' => $customer->fresh()?->sms_balance,
            'csrf_token' => csrf_token(),
        ]);
    }

    public function segment(Request $request, SegmentationService $segmentation): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($segmentation->analyze($validated['message']));
    }

    /**
     * @return array{source: string, numbers?: list<string>, contact_ids?: list<int>, group_id?: int|null, message: string, sender_id?: string, name?: string|null, scheduled_at?: string|null}
     */
    private function validatedPreview(Request $request, bool $requireSender = false): array
    {
        $rules = [
            'source' => ['required', Rule::in(['numbers', 'contacts', 'group', 'upload'])],
            'message' => ['required', 'string', 'max:2000'],
            'numbers' => ['nullable', 'array', 'max:5000'],
            'numbers.*' => ['string', 'max:32'],
            'contact_ids' => ['nullable', 'array', 'max:5000'],
            'contact_ids.*' => ['integer'],
            'group_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:80'],
            'scheduled_at' => ['nullable', 'date'],
            'sender_id' => [$requireSender ? 'required' : 'nullable', 'string', 'max:13'],
        ];

        return $request->validate($rules);
    }
}
