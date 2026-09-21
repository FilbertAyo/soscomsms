<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'campaign_id',
        'action',
        'request_payload',
        'response_payload',
        'http_status',
        'status',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'http_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['provider', 'campaign']);

        $request = is_array($this->request_payload) ? $this->request_payload : [];
        $response = is_array($this->response_payload) ? $this->response_payload : [];
        $phone = $this->firstPhone($request);
        $senderId = isset($request['senderid']) ? (string) $request['senderid'] : null;
        $countryCode = isset($request['CountryCode']) ? (string) $request['CountryCode'] : null;
        $providerReply = $this->providerReply($response);
        $diagnosis = $this->diagnosis($providerReply);

        $parts = array_filter([
            $this->provider?->name,
            $this->action,
            $this->status,
        ]);
        $message = implode(' — ', $parts);
        if (filled($this->campaign?->name)) {
            $message .= ' ('.$this->campaign->name.')';
        }
        if ($phone !== null) {
            $message .= ' to '.$phone;
        }
        if (filled($this->error)) {
            $message .= ': '.$this->error;
        } elseif ($providerReply !== null && $providerReply !== '') {
            $message .= ': '.$providerReply;
        }

        return [
            'id' => $this->id,
            'source' => 'provider',
            'action' => $this->action,
            'status' => $this->status,
            'httpStatus' => $this->http_status,
            'campaignId' => $this->campaign_id,
            'campaignName' => $this->campaign?->name,
            'providerName' => $this->provider?->name,
            'senderId' => $senderId,
            'phone' => $phone,
            'countryCode' => $countryCode,
            'providerReply' => $providerReply,
            'diagnosis' => $diagnosis,
            'error' => $this->error,
            'message' => $message,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function firstPhone(array $request): ?string
    {
        if (isset($request['mobileno']) && is_string($request['mobileno']) && $request['mobileno'] !== '') {
            return $request['mobileno'];
        }

        $numbers = $request['mobileno'] ?? null;
        if (is_array($numbers) && isset($numbers[0]) && is_string($numbers[0])) {
            return $numbers[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function providerReply(array $response): ?string
    {
        if (isset($response['body']) && is_string($response['body']) && $response['body'] !== '') {
            return mb_substr($response['body'], 0, 400);
        }

        return null;
    }

    private function diagnosis(?string $providerReply): string
    {
        if ($this->status === 'blocked') {
            return $this->error ?: 'Soscom blocked this send before calling Mobishastra. Check production readiness.';
        }

        if ($this->status === 'timeout') {
            return $this->error ?: 'Soscom could not reach Mobishastra. The phone will not get this SMS.';
        }

        $reply = strtolower((string) $providerReply);
        $error = strtolower((string) $this->error);

        if (str_contains($reply, 'invalid password') || str_contains($error, 'invalid password')) {
            return 'Mobishastra rejected the password. The SMS did not leave their network.';
        }

        if (str_contains($reply, 'invalid') && str_contains($reply, 'user')) {
            return 'Mobishastra rejected the username or the server IP is not whitelisted.';
        }

        if (str_contains($reply, 'sender')) {
            return 'Mobishastra rejected the Sender ID. It must be approved on the Soscom Mobishastra account.';
        }

        if (preg_match('/\b000\b/', $reply) === 1 || str_contains($reply, 'send successful')) {
            return 'Mobishastra accepted the SMS. If the phone did not get it, the Sender ID or their Tanzania route failed after accept.';
        }

        if ($this->action === 'send' || $this->action === 'send_bulk') {
            return $this->error
                ?: ($providerReply
                    ? 'Mobishastra replied with the text above. That is why the phone did not get the SMS.'
                    : 'No Mobishastra reply was stored for this send.');
        }

        return $this->error ?: 'Gateway event recorded.';
    }
}
