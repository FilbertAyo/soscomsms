<?php

namespace App\Services\Gateway;

use App\Contracts\SmsGatewayInterface;
use App\Enums\MessageEncoding;
use App\Models\Provider;
use App\Models\ProviderLog;
use App\Services\ProviderReadinessService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class MobishastraGateway implements SmsGatewayInterface
{
    public function send(OutboundSms $message): GatewaySendResult
    {
        return $this->sendBulk([$message])[0];
    }

    /**
     * @param  list<OutboundSms>  $messages
     * @return list<GatewaySendResult>
     */
    public function sendBulk(array $messages): array
    {
        app(ProviderReadinessService::class)->assertReadyToSend();

        $provider = Provider::current();
        $payload = [];

        foreach ($messages as $message) {
            $row = [
                'user' => $provider->profile_id,
                'pwd' => $provider->password,
                'number' => ProviderPhone::digits($message->phone),
                'msg' => $this->encodedBody($message),
                'sender' => $message->sender,
                'language' => $message->encoding === MessageEncoding::Unicode ? 'Unicode' : 'English',
            ];
            $payload[] = $row;
        }

        try {
            $response = Http::timeout((int) config('sms.provider.timeout', 20))
                ->connectTimeout(5)
                ->accept('*/*')
                ->asJson()
                ->post($this->url($provider, (string) config('sms.provider.json_path', 'sendsms_api_json.aspx')), $payload);
        } catch (ConnectionException $exception) {
            $this->log(
                $provider,
                'send_bulk',
                $this->bulkLogRequest($messages),
                null,
                null,
                'timeout',
                $exception->getMessage(),
                $messages[0]->campaignId ?? null,
            );

            return array_map(
                fn (OutboundSms $message): GatewaySendResult => new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    timedOut: true,
                    code: 'timeout',
                    raw: $exception->getMessage(),
                ),
                $messages,
            );
        }

        $decoded = $response->json();
        $this->log(
            $provider,
            'send_bulk',
            $this->bulkLogRequest($messages),
            $this->bulkLogResponse($response->status(), $decoded, $response->body()),
            $response->status(),
            $response->successful() ? 'ok' : 'error',
            campaignId: $messages[0]->campaignId ?? null,
        );

        return $this->mapJsonResults($messages, $response, $decoded);
    }

    public function checkBalance(): ProviderBalance
    {
        $provider = Provider::current();
        $query = [
            'user' => $provider->profile_id,
            'pwd' => $provider->password,
        ];

        $response = $this->http()
            ->get($this->url($provider, (string) config('sms.provider.balance_path', 'balance.aspx')), $query);

        $raw = trim($response->body());
        $balance = MobishastraBalanceParser::parse($raw);
        $this->log(
            $provider,
            'balance',
            $this->redact($query),
            ['body' => $raw, 'sms_balance' => $balance],
            $response->status(),
            $balance !== null ? 'ok' : 'error',
            $balance === null ? 'Unrecognised balance response' : null,
        );

        if ($balance === null) {
            return new ProviderBalance(
                smsBalance: (int) ($provider->last_balance ?? 0),
                raw: $raw,
                parsed: false,
                error: 'Mobishastra did not return a numeric SMS balance.',
            );
        }

        $provider->last_balance = $balance;
        $provider->last_balance_at = now();
        $provider->save();

        return new ProviderBalance($balance, $raw);
    }

    public function checkDeliveryStatus(string $providerMessageId): DeliveryReport
    {
        $path = (string) config('sms.provider.dlr_path', 'dlrstatus_api.aspx');
        if ($path === '') {
            // Empty status is unmapped — do not invent provider "Unknown".
            return new DeliveryReport($providerMessageId, '', '');
        }

        $provider = Provider::current();
        $query = [
            'user' => $provider->profile_id,
            'pwd' => $provider->password,
            'messageid' => $providerMessageId,
        ];

        try {
            $response = $this->http()
                ->timeout((int) config('sms.provider.dlr_timeout', 8))
                ->get($this->dlrUrl($provider, $path), $query);
        } catch (ConnectionException $exception) {
            $this->log($provider, 'delivery_status', $this->redact($query), null, null, 'timeout', $exception->getMessage());

            return new DeliveryReport($providerMessageId, '', $exception->getMessage());
        }

        $raw = trim($response->body());
        $this->log($provider, 'delivery_status', $this->redact($query), ['body' => $raw], $response->status(), $response->successful() ? 'ok' : 'error');

        return $this->mapDlrBody($providerMessageId, $raw);
    }

    private function encodedBody(OutboundSms $message): string
    {
        if ($message->encoding === MessageEncoding::Unicode) {
            return UnicodeHex::encode($message->body);
        }

        return $message->body;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('sms.provider.timeout', 20))
            ->connectTimeout(5)
            ->accept('*/*')
            ->withOptions(['allow_redirects' => false]);
    }

    private function url(Provider $provider, string $path): string
    {
        $base = rtrim((string) ($provider->base_url ?: config('sms.provider.base_url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function dlrUrl(Provider $provider, string $path): string
    {
        $base = rtrim((string) (config('sms.provider.dlr_base_url') ?: $provider->base_url ?: config('sms.provider.base_url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function mapDlrBody(string $providerMessageId, string $raw): DeliveryReport
    {
        if ($raw === '') {
            // Empty poll body is unmapped — keep the existing Soscom status.
            return new DeliveryReport($providerMessageId, '', '');
        }

        if (! str_contains($raw, ',')) {
            return new DeliveryReport($providerMessageId, $raw, $raw);
        }

        $parts = array_map('trim', explode(',', $raw));
        $messageId = ($parts[0] ?? '') !== '' ? $parts[0] : $providerMessageId;
        $status = ($parts[1] ?? '') !== '' ? $parts[1] : $raw;
        $occurredAt = count($parts) > 2
            ? implode(', ', array_slice($parts, 2))
            : null;
        $occurredAt = $occurredAt !== '' ? $occurredAt : null;

        return new DeliveryReport($messageId, $status, $raw, $occurredAt);
    }

    private function mapCode(OutboundSms $message, string $body): GatewaySendResult
    {
        $code = $this->normalizeCode($body);
        $messageId = $this->extractMessageId($body);

        if (in_array($code, ['013', 'timeout'], true)) {
            return new GatewaySendResult(
                recipientId: $message->recipientId,
                phone: $message->phone,
                accepted: false,
                timedOut: true,
                code: $code,
                raw: $body,
            );
        }

        if ($this->isHardFail($code)) {
            return new GatewaySendResult(
                recipientId: $message->recipientId,
                phone: $message->phone,
                accepted: false,
                code: $code,
                raw: $body,
            );
        }

        if ($this->isSuccessCode($code) || $messageId !== null) {
            if ($messageId === null) {
                // Accepted by code but no trackable id — treat as uncertain (do not invent MSH-R*).
                return new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    timedOut: true,
                    code: '000',
                    raw: $body,
                );
            }

            return new GatewaySendResult(
                recipientId: $message->recipientId,
                phone: $message->phone,
                accepted: true,
                providerMessageId: $messageId,
                code: '000',
                raw: $body,
            );
        }

        return new GatewaySendResult(
            recipientId: $message->recipientId,
            phone: $message->phone,
            accepted: false,
            code: $code !== '' ? $code : '001',
            raw: $body,
        );
    }

    /**
     * @param  list<OutboundSms>  $messages
     * @return list<GatewaySendResult>
     */
    private function mapJsonResults(array $messages, Response $response, mixed $decoded): array
    {
        $rows = [];
        if (is_array($decoded)) {
            $rows = array_is_list($decoded) ? $decoded : [$decoded];
        }

        $byNumber = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $number = ProviderPhone::digits((string) ($row['number'] ?? ''));
            if ($number !== '') {
                $byNumber[$number] = $row;
            }
        }

        $results = [];
        foreach ($messages as $index => $message) {
            $digits = ProviderPhone::digits($message->phone);
            $row = $byNumber[$digits] ?? ($rows[$index] ?? null);

            if (! is_array($row)) {
                $results[] = $this->mapCode($message, trim($response->body()));

                continue;
            }

            $code = $this->normalizeCode((string) ($row['response'] ?? $row['str_response'] ?? ''));
            $msgId = $this->messageIdFromRow($row);

            if ($code === '013') {
                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    timedOut: true,
                    code: '013',
                    raw: json_encode($row) ?: '',
                );

                continue;
            }

            if ($this->isHardFail($code)) {
                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: false,
                    code: $code !== '' ? $code : '001',
                    raw: json_encode($row) ?: '',
                );

                continue;
            }

            if ($this->isSuccessCode($code) || $msgId !== null) {
                if ($msgId === null) {
                    $results[] = new GatewaySendResult(
                        recipientId: $message->recipientId,
                        phone: $message->phone,
                        accepted: false,
                        timedOut: true,
                        code: '000',
                        raw: json_encode($row) ?: '',
                    );

                    continue;
                }

                $results[] = new GatewaySendResult(
                    recipientId: $message->recipientId,
                    phone: $message->phone,
                    accepted: true,
                    providerMessageId: $msgId,
                    code: '000',
                    raw: json_encode($row) ?: '',
                );

                continue;
            }

            $results[] = new GatewaySendResult(
                recipientId: $message->recipientId,
                phone: $message->phone,
                accepted: false,
                code: $code !== '' ? $code : '001',
                raw: json_encode($row) ?: '',
            );
        }

        return $results;
    }

    private function normalizeCode(string $body): string
    {
        $trimmed = strtolower(trim($body));

        if (str_contains($trimmed, ',')) {
            $first = trim(explode(',', $trimmed, 2)[0]);
            if ($first !== '') {
                $trimmed = $first;
            }
        }

        if (preg_match('/\b(\d{3})\b/', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    private function extractMessageId(string $body): ?string
    {
        $trimmed = trim($body);

        if (preg_match('/\b(M-\d+)\b/i', $trimmed, $matches) === 1 && $this->looksLikeProviderMessageId($matches[1])) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{6,})\b/', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        if (str_contains($trimmed, ',')) {
            foreach (array_map('trim', explode(',', $trimmed)) as $part) {
                if ($this->looksLikeProviderMessageId($part)) {
                    return $part;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function messageIdFromRow(array $row): ?string
    {
        foreach (['msg_id', 'msgid', 'message_id', 'messageid', 'MessageId'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $id = trim((string) $row[$key]);
            if ($this->looksLikeProviderMessageId($id)) {
                return $id;
            }
        }

        return null;
    }

    private function looksLikeProviderMessageId(string $id): bool
    {
        if ($id === '' || $this->isSuccessCode(strtolower($id)) || $this->isHardFail(strtolower($id))) {
            return false;
        }

        return preg_match('/^(?:M-\d+|\d{6,})$/i', $id) === 1;
    }

    private function isSuccessCode(string $code): bool
    {
        return in_array($code, ['000', 'send successful', 'send success', 'success'], true);
    }

    private function isHardFail(string $code): bool
    {
        return in_array($code, ['001', '003', '005', '006', '007', '008', '009', '010', '011', '012'], true);
    }

    /**
     * @param  list<OutboundSms>  $messages
     * @return array{count: int, encoding: string, senderid: string|null, mobileno: list<string>}
     */
    private function bulkLogRequest(array $messages): array
    {
        return [
            'count' => count($messages),
            'encoding' => ($messages[0]->encoding ?? MessageEncoding::Gsm)->value,
            'senderid' => $messages[0]->sender ?? null,
            'mobileno' => array_map(
                fn (OutboundSms $message): string => ProviderPhone::digits($message->phone),
                array_slice($messages, 0, 5),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $decoded
     * @return array{accepted: int|null, http_status: int, body: string}
     */
    private function bulkLogResponse(int $httpStatus, mixed $decoded, string $rawBody): array
    {
        $accepted = null;
        if (is_array($decoded)) {
            $accepted = count($decoded);
        }

        return [
            'accepted' => $accepted,
            'http_status' => $httpStatus,
            'body' => mb_substr(trim($rawBody), 0, 500),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        if (array_key_exists('pwd', $payload)) {
            $payload['pwd'] = '***';
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return list<array<string, mixed>>
     */
    private function redactList(array $payload): array
    {
        return array_map($this->redact(...), $payload);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    private function log(
        Provider $provider,
        string $action,
        ?array $request,
        ?array $response,
        ?int $httpStatus,
        string $status,
        ?string $error = null,
        ?int $campaignId = null,
    ): void {
        try {
            ProviderLog::query()->create([
                'provider_id' => $provider->id,
                'campaign_id' => $campaignId,
                'action' => $action,
                'request_payload' => $request,
                'response_payload' => $response,
                'http_status' => $httpStatus,
                'status' => $status,
                'error' => $error,
            ]);
        } catch (Throwable) {
            // Logging must never block sending.
        }
    }
}
