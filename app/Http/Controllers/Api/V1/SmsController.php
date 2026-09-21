<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ResolvesApiCustomer;
use App\Http\Controllers\Controller;
use App\Models\ApiIdempotencyKey;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\CampaignService;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmsController extends Controller
{
    use ResolvesApiCustomer;

    public function send(Request $request, CampaignService $campaigns, IdempotencyService $idempotency): JsonResponse
    {
        return $this->dispatch($request, $campaigns, $idempotency, bulk: false);
    }

    public function bulk(Request $request, CampaignService $campaigns, IdempotencyService $idempotency): JsonResponse
    {
        return $this->dispatch($request, $campaigns, $idempotency, bulk: true);
    }

    public function show(Request $request, int $sms): JsonResponse
    {
        $customer = $this->apiCustomer($request);
        $campaign = Campaign::query()
            ->where('customer_id', $customer->id)
            ->whereKey($sms)
            ->firstOrFail();

        return response()->json($campaign->toPublicArray(true, $customer));
    }

    public function messages(Request $request, int $sms): JsonResponse
    {
        $customer = $this->apiCustomer($request);
        $campaign = Campaign::query()
            ->where('customer_id', $customer->id)
            ->whereKey($sms)
            ->firstOrFail();

        return response()->json($campaign->toPublicArray(true, $customer));
    }

    public function message(Request $request, int $message): JsonResponse
    {
        $customer = $this->apiCustomer($request);
        $recipient = CampaignRecipient::query()
            ->with('delivery')
            ->whereKey($message)
            ->whereHas('campaign', function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id);
            })
            ->firstOrFail();

        return response()->json($recipient->toPublicArray());
    }

    private function dispatch(
        Request $request,
        CampaignService $campaigns,
        IdempotencyService $idempotency,
        bool $bulk,
    ): JsonResponse {
        $customer = $this->apiCustomer($request);
        $to = $request->input('to');

        if ($bulk && is_string($to)) {
            throw ValidationException::withMessages([
                'to' => 'Provide an array of Tanzanian mobile numbers.',
            ]);
        }

        if (is_string($to)) {
            $request->merge(['to' => [$to]]);
        }

        $validated = $request->validate([
            'sender_id' => ['required', 'string', 'max:13'],
            'to' => ['required', 'array', 'min:1', 'max:'.(int) config('sms.max_recipients_per_campaign', 5000)],
            'to.*' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $claim = $idempotency->replayOrClaim($customer, $request);
        if ($claim instanceof JsonResponse) {
            return $claim;
        }

        try {
            $campaign = $campaigns->create($customer, [
                'source' => 'numbers',
                'numbers' => $validated['to'],
                'message' => $validated['message'],
                'sender_id' => $validated['sender_id'],
                'name' => $validated['name'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            $this->releaseClaim($idempotency, $claim);
            throw $this->publicApiException($exception);
        } catch (Throwable $exception) {
            $this->releaseClaim($idempotency, $claim);
            throw $exception;
        }

        $request->attributes->set('api_campaign_id', $campaign->id);
        $fresh = $customer->fresh() ?? $customer;
        $body = $campaign->toPublicArray(true, $fresh);

        if ($claim instanceof ApiIdempotencyKey) {
            $idempotency->complete($claim, 201, $body, $campaign->id);
        }

        return response()->json($body, 201);
    }

    private function releaseClaim(IdempotencyService $idempotency, JsonResponse|ApiIdempotencyKey|null $claim): void
    {
        if ($claim instanceof ApiIdempotencyKey) {
            $idempotency->release($claim);
        }
    }

    private function publicApiException(ValidationException $exception): ValidationException
    {
        $errors = $exception->errors();

        if (isset($errors['sender_id'])) {
            return ValidationException::withMessages([
                'sender_id' => 'This Sender ID is not available on this account.',
            ])->status(403);
        }

        if (isset($errors['quantity'])) {
            return ValidationException::withMessages([
                'quantity' => 'Not enough SMS credits.',
            ])->status(402);
        }

        return $exception;
    }
}
