<?php

namespace App\Services;

use App\Models\ApiIdempotencyKey;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyService
{
    public function replayOrClaim(Customer $customer, Request $request): JsonResponse|ApiIdempotencyKey|null
    {
        $key = $this->key($request);
        if ($key === null) {
            return null;
        }

        $hash = $this->hash($request);

        try {
            return ApiIdempotencyKey::query()->create([
                'customer_id' => $customer->id,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'status_code' => null,
                'response_body' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = ApiIdempotencyKey::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing === null) {
                abort(409, 'Idempotency-Key conflict.');
            }

            if ($existing->request_hash !== $hash) {
                abort(409, 'Idempotency-Key was already used with a different request.');
            }

            if ($existing->status_code === null) {
                abort(409, 'A request with this Idempotency-Key is already in progress.');
            }

            return response()->json($existing->response_body, $existing->status_code);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function complete(ApiIdempotencyKey $record, int $status, array $body, ?int $campaignId = null): void
    {
        $record->forceFill([
            'status_code' => $status,
            'response_body' => $body,
            'campaign_id' => $campaignId,
        ])->save();
    }

    public function release(ApiIdempotencyKey $record): void
    {
        if ($record->status_code !== null) {
            return;
        }

        $record->delete();
    }

    private function key(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key)) {
            return null;
        }

        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if (strlen($key) > 255) {
            abort(400, 'Idempotency-Key is too long.');
        }

        return $key;
    }

    private function hash(Request $request): string
    {
        return hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
    }
}
