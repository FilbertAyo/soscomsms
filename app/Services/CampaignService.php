<?php

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\ContactStatus;
use App\Enums\MessageEncoding;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Message;
use App\Support\PersonalizedMessage;
use App\Support\TanzaniaPhone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function __construct(
        private SegmentationService $segmentation,
        private SenderIdService $senderIds,
    ) {}

    /**
     * @param  array{source: string, numbers?: list<string>, named_recipients?: list<array{phone: string, name?: string|null}>, contact_ids?: list<int>, group_id?: int|null, message: string, sender_id?: string|null, personalize?: bool}  $payload
     * @return array<string, mixed>
     */
    public function preview(Customer $customer, array $payload): array
    {
        $personalize = (bool) ($payload['personalize'] ?? false);
        $message = $payload['message'];
        $analysis = $this->segmentation->analyze($message);
        $resolved = $this->resolveRecipients($customer, $payload, $personalize);
        $placeholderReady = ! $personalize || PersonalizedMessage::contains($message);
        $prepared = $placeholderReady
            ? $this->prepareRecipients($message, $resolved['accepted'], $personalize, $analysis)
            : ['billable' => [], 'missing' => [], 'overLimit' => 0];

        if ($personalize && $placeholderReady) {
            $billable = $prepared['billable'];
            $counts = array_column($billable, 'segment_count');
            $segmentsMin = $counts === [] ? 0 : (int) min($counts);
            $segmentsMax = $counts === [] ? 0 : (int) max($counts);
            $recipientCount = count($billable);
            $totalSms = (int) array_sum($counts);
            $segmentsPerRecipient = $segmentsMax;
            $example = $this->exampleFrom($billable, $message, true);
        } elseif ($personalize) {
            $recipientCount = count($resolved['accepted']);
            $segmentsPerRecipient = $analysis['segmentCount'];
            $segmentsMin = $segmentsPerRecipient;
            $segmentsMax = $segmentsPerRecipient;
            $totalSms = $recipientCount * $segmentsPerRecipient;
            $example = null;
        } else {
            $billable = $prepared['billable'];
            $recipientCount = count($billable);
            $segmentsPerRecipient = $analysis['segmentCount'];
            $segmentsMin = $segmentsPerRecipient;
            $segmentsMax = $segmentsPerRecipient;
            $totalSms = $recipientCount * $segmentsPerRecipient;
            $example = $this->exampleFrom($billable, $message, false);
        }

        $unitPrice = (int) config('sms.send_unit_price_tzs', 30);
        $balance = $customer->sms_balance;

        return [
            'encoding' => $analysis['encoding'],
            'characterCount' => $analysis['characterCount'],
            'segmentsPerRecipient' => $segmentsPerRecipient,
            'singleLimit' => $analysis['singleLimit'],
            'concatLimit' => $analysis['concatLimit'],
            'recipientCount' => $recipientCount,
            'invalidCount' => count($resolved['invalid']),
            'duplicateCount' => $resolved['duplicates'],
            'invalidNumbers' => array_slice($resolved['invalid'], 0, 25),
            'totalSms' => $totalSms,
            'unitPriceTzs' => $unitPrice,
            'costTzs' => $totalSms * $unitPrice,
            'smsBalance' => $balance,
            'balanceAfter' => $balance - $totalSms,
            'enoughCredits' => $totalSms > 0 && $balance >= $totalSms,
            'personalized' => $personalize,
            'placeholderReady' => $placeholderReady,
            'missingNameCount' => count($prepared['missing']),
            'missingNameNumbers' => array_slice(array_column($prepared['missing'], 'phone'), 0, 10),
            'overLimitCount' => $prepared['overLimit'],
            'segmentsMin' => $segmentsMin,
            'segmentsMax' => $segmentsMax,
            'example' => $example,
        ];
    }

    /**
     * @param  array{source: string, numbers?: list<string>, named_recipients?: list<array{phone: string, name?: string|null}>, contact_ids?: list<int>, group_id?: int|null, message: string, sender_id: string, name?: string|null, scheduled_at?: string|null, personalize?: bool}  $payload
     */
    public function create(Customer $customer, array $payload): Campaign
    {
        $customer->assertCanSend();
        $sender = $this->senderIds->assertUsable($customer, $payload['sender_id']);
        $personalize = (bool) ($payload['personalize'] ?? false);
        $messageBody = $payload['message'];
        $analysis = $this->segmentation->analyze($messageBody);

        if ($analysis['segmentCount'] < 1) {
            throw ValidationException::withMessages([
                'message' => 'Type a message before sending.',
            ]);
        }

        if ($personalize && ! PersonalizedMessage::contains($messageBody)) {
            throw ValidationException::withMessages([
                'message' => 'Add {name} where each person’s name should appear, or turn names off.',
            ]);
        }

        $resolved = $this->resolveRecipients($customer, $payload, $personalize);
        $prepared = $this->prepareRecipients($messageBody, $resolved['accepted'], $personalize, $analysis);

        if ($prepared['missing'] !== []) {
            $phones = array_map(
                fn (array $row): string => $row['phone'],
                $prepared['missing'],
            );
            $listed = implode(', ', array_slice($phones, 0, 5));
            $suffix = count($phones) > 5 ? '…' : '.';

            throw ValidationException::withMessages([
                'recipients' => 'Add a name for every number. Missing a name for: '.$listed.$suffix,
            ]);
        }

        if ($prepared['overLimit'] > 0) {
            throw ValidationException::withMessages([
                'message' => 'A name makes this message longer than 2,000 characters. Shorten the message or the name.',
            ]);
        }

        $accepted = $prepared['billable'];

        if ($accepted === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Add at least one valid Tanzanian mobile number.',
            ]);
        }

        $scheduledAt = $this->scheduledAt($payload['scheduled_at'] ?? null);
        $totalSms = (int) array_sum(array_column($accepted, 'segment_count'));
        $status = $scheduledAt === null ? CampaignStatus::Queued : CampaignStatus::Scheduled;
        $recipientStatus = $scheduledAt === null
            ? CampaignRecipientStatus::Queued
            : CampaignRecipientStatus::Pending;

        $campaign = DB::transaction(function () use (
            $customer,
            $sender,
            $messageBody,
            $analysis,
            $accepted,
            $totalSms,
            $status,
            $recipientStatus,
            $scheduledAt,
            $payload,
            $personalize,
        ): Campaign {
            /** @var Customer $locked */
            $locked = Customer::query()
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->sms_balance < $totalSms) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough SMS credits for this campaign.',
                ]);
            }

            $message = Message::query()->create([
                'customer_id' => $customer->id,
                'body' => $messageBody,
                'encoding' => MessageEncoding::from($analysis['encoding']),
                'character_count' => $analysis['characterCount'],
                'segment_count' => $analysis['segmentCount'],
            ]);

            $name = $this->campaignName($payload['name'] ?? null, $messageBody);

            $campaign = Campaign::query()->create([
                'customer_id' => $customer->id,
                'sender_id_id' => $sender->id,
                'message_id' => $message->id,
                'name' => $name,
                'sender_name' => $sender->name,
                'personalized' => $personalize,
                'status' => $status,
                'recipient_count' => count($accepted),
                'total_segments' => $totalSms,
                'reserved_segments' => $totalSms,
                'consumed_segments' => 0,
                'scheduled_at' => $scheduledAt,
                'queued_at' => $scheduledAt === null ? now() : null,
            ]);

            $transaction = app(CreditLedger::class)->reserveCampaign(
                $locked,
                $campaign->id,
                $totalSms,
                $name,
            );

            $campaign->credit_transaction_id = $transaction->id;
            $campaign->save();

            $now = now();
            foreach (array_chunk($accepted, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $row) {
                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'contact_id' => $row['contact_id'],
                        'phone' => $row['phone'],
                        'recipient_name' => $row['name'],
                        'body' => $row['body'],
                        'status' => $recipientStatus->value,
                        'segment_count' => $row['segment_count'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                CampaignRecipient::query()->insert($rows);
            }

            return $campaign->fresh('message') ?? $campaign;
        });

        if ($campaign->status === CampaignStatus::Queued) {
            $campaign = app(CampaignDispatcher::class)->dispatch($campaign);
        }

        return $campaign->fresh('message') ?? $campaign;
    }

    /**
     * @param  array{source: string, numbers?: list<string>, named_recipients?: list<array{phone: string, name?: string|null}>, contact_ids?: list<int>, group_id?: int|null}  $payload
     * @return array{accepted: list<array{phone: string, contact_id: int|null, name: string|null}>, invalid: list<string>, duplicates: int}
     */
    private function resolveRecipients(Customer $customer, array $payload, bool $personalize): array
    {
        $source = $payload['source'];
        $raw = match ($source) {
            'numbers', 'upload' => $personalize
                ? $this->entriesFromNamed($payload['named_recipients'] ?? [])
                : $this->entriesFromPhones($payload['numbers'] ?? []),
            'contacts' => $this->peopleFromContacts($customer, $payload['contact_ids'] ?? []),
            'group' => $this->peopleFromGroup($customer, $payload['group_id'] ?? null),
            default => throw ValidationException::withMessages([
                'source' => 'Choose numbers, contacts, a group, or a file.',
            ]),
        };

        $max = (int) config('sms.max_recipients_per_campaign', 5000);
        $accepted = [];
        $seen = [];
        $invalid = [];
        $duplicates = 0;

        foreach ($raw as $entry) {
            $phone = (string) $entry['phone'];
            $contactId = $entry['contact_id'];
            $name = $personalize
                ? PersonalizedMessage::cleanName((string) ($entry['name'] ?? ''))
                : null;

            if (! TanzaniaPhone::isValid($phone)) {
                if (trim($phone) !== '') {
                    $invalid[] = $phone;
                }

                continue;
            }

            $normalized = TanzaniaPhone::normalize($phone);
            if (isset($seen[$normalized])) {
                $duplicates++;

                continue;
            }

            $seen[$normalized] = true;
            $accepted[] = [
                'phone' => $normalized,
                'contact_id' => $contactId,
                'name' => $name === '' ? null : $name,
            ];

            if (count($accepted) >= $max) {
                break;
            }
        }

        return [
            'accepted' => $accepted,
            'invalid' => $invalid,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param  list<array{phone: string, contact_id: int|null, name: string|null}>  $accepted
     * @param  array{encoding: string, characterCount: int, segmentCount: int, singleLimit: int, concatLimit: int}  $templateAnalysis
     * @return array{billable: list<array{phone: string, contact_id: int|null, name: string|null, body: string|null, segment_count: int}>, missing: list<array{phone: string, contact_id: int|null, name: string|null}>, overLimit: int}
     */
    private function prepareRecipients(string $message, array $accepted, bool $personalize, array $templateAnalysis): array
    {
        $billable = [];
        $missing = [];
        $overLimit = 0;

        foreach ($accepted as $row) {
            if (! $personalize) {
                $billable[] = [
                    'phone' => $row['phone'],
                    'contact_id' => $row['contact_id'],
                    'name' => null,
                    'body' => null,
                    'segment_count' => $templateAnalysis['segmentCount'],
                ];

                continue;
            }

            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                $missing[] = $row;

                continue;
            }

            $body = PersonalizedMessage::render($message, $name);
            if (mb_strlen($body) > 2000) {
                $overLimit++;

                continue;
            }

            $rendered = $this->segmentation->analyze($body);
            $billable[] = [
                'phone' => $row['phone'],
                'contact_id' => $row['contact_id'],
                'name' => $name,
                'body' => $body,
                'segment_count' => $rendered['segmentCount'],
            ];
        }

        return [
            'billable' => $billable,
            'missing' => $missing,
            'overLimit' => $overLimit,
        ];
    }

    /**
     * @param  list<array{phone: string, contact_id: int|null, name: string|null, body: string|null, segment_count: int}>  $billable
     * @return array{phone: string, name: string|null, message: string}|null
     */
    private function exampleFrom(array $billable, string $template, bool $personalize): ?array
    {
        if ($billable === []) {
            return null;
        }

        $first = $billable[0];

        return [
            'phone' => $first['phone'],
            'name' => $personalize ? $first['name'] : null,
            'message' => $personalize ? (string) $first['body'] : $template,
        ];
    }

    /**
     * @param  list<string>  $numbers
     * @return list<array{phone: string, contact_id: null, name: null}>
     */
    private function entriesFromPhones(array $numbers): array
    {
        $entries = [];
        foreach ($numbers as $phone) {
            $entries[] = [
                'phone' => (string) $phone,
                'contact_id' => null,
                'name' => null,
            ];
        }

        return $entries;
    }

    /**
     * @param  list<array{phone?: string, name?: string|null}>  $rows
     * @return list<array{phone: string, contact_id: null, name: string}>
     */
    private function entriesFromNamed(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $entries[] = [
                'phone' => (string) ($row['phone'] ?? ''),
                'contact_id' => null,
                'name' => (string) ($row['name'] ?? ''),
            ];
        }

        return $entries;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{phone: string, contact_id: int, name: string}>
     */
    private function peopleFromContacts(Customer $customer, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $customer->contacts()
            ->where('status', ContactStatus::Active)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'phone', 'name'])
            ->map(fn (Contact $contact): array => [
                'phone' => $contact->phone,
                'contact_id' => $contact->id,
                'name' => (string) $contact->name,
            ])
            ->all();
    }

    /**
     * @return list<array{phone: string, contact_id: int, name: string}>
     */
    private function peopleFromGroup(Customer $customer, ?int $groupId): array
    {
        if ($groupId === null || $groupId < 1) {
            throw ValidationException::withMessages([
                'group_id' => 'Choose a contact group.',
            ]);
        }

        $group = $customer->contactGroups()->whereKey($groupId)->first();
        if ($group === null) {
            throw ValidationException::withMessages([
                'group_id' => 'That group was not found.',
            ]);
        }

        return $group->contacts()
            ->where('status', ContactStatus::Active)
            ->orderBy('id')
            ->get(['id', 'phone', 'name'])
            ->map(fn (Contact $contact): array => [
                'phone' => $contact->phone,
                'contact_id' => $contact->id,
                'name' => (string) $contact->name,
            ])
            ->all();
    }

    private function scheduledAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $at = CarbonImmutable::parse((string) $value, (string) config('app.timezone'));

        if ($at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Choose a future date and time.',
            ]);
        }

        return $at;
    }

    private function campaignName(?string $name, string $body): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed !== '') {
            return Str::limit($trimmed, 80, '');
        }

        $fromBody = trim(preg_replace('/\s+/', ' ', $body) ?? $body);

        return Str::limit($fromBody !== '' ? $fromBody : 'SMS campaign', 80, '');
    }
}
