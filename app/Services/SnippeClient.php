<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SnippeClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMobilePayment(array $payload, string $idempotencyKey): array
    {
        return $this->data($this->send('post', '/v1/payments', $payload, $idempotencyKey));
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPayment(string $reference): array
    {
        return $this->data($this->send('get', '/v1/payments/'.rawurlencode($reference)));
    }

    public function signatureIsValid(string $payload, string $timestamp, string $signature): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === '' || $timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $age = time() - (int) $timestamp;
        if ($age > 300 || $age < -300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        if (strlen($signature) !== strlen($expected)) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('Snippe API key is not configured.');
        }

        $pending = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout(20);

        if ($idempotencyKey !== null) {
            $pending = $pending->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ]);
        }

        try {
            $response = $method === 'get'
                ? $pending->get($path)
                : $pending->post($path, $payload ?? []);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The payment network could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function data(array $json): array
    {
        $data = $json['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    private function apiKey(): string
    {
        return trim((string) config('sms.snippe.api_key'));
    }

    private function webhookSecret(): string
    {
        return trim((string) config('sms.snippe.webhook_secret'));
    }

    private function baseUrl(): string
    {
        $base = trim((string) config('sms.snippe.base_url'));

        return $base !== '' ? $base : 'https://api.snippe.sh';
    }
}
