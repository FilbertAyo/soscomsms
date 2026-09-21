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
        private CreditLedger $ledger,
    ) {}

    /**
     * @param  array{source: string, numbers?: list<string>, contact_ids?: list<int>, group_id?: int|null, message: string, sender_id?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function preview(Customer $customer, array $payload): array
    {
        $analysis = $this->segmentation->analyze($payload['message']);
        $resolved = $this->resolveRecipients($customer, $payload);
        $recipientCount = count($resolved['accepted']);
        $segments = $analysis['segmentCount'];
        $totalSms = $recipientCount * $segments;
        $unitPrice = (int) config('sms.send_unit_price_tzs', 30);
        $balance = $customer->sms_balance;

        return [
            'encoding' => $analysis['encoding'],
            'characterCount' => $analysis['characterCount'],
            'segmentsPerRecipient' => $segments,
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
        ];
    }

    /**
     * @param  array{source: string, numbers?: list<string>, contact_ids?: list<int>, group_id?: int|null, message: string, sender_id: string, name?: string|null, scheduled_at?: string|null}  $payload
     */
    public function create(Customer $customer, array $payload): Campaign
    {
        $customer->assertCanSend();
        $sender = $this->senderIds->assertUsable($customer, $payload['sender_id']);
        $messageBody = $payload['message'];
        $analysis = $this->segmentation->analyze($messageBody);

        if ($analysis['segmentCount'] < 1) {
            throw ValidationException::withMessages([
                'message' => 'Type a message before sending.',
            ]);
        }

        $resolved = $this->resolveRecipients($customer, $payload);
        $accepted = $resolved['accepted'];

        if ($accepted === []) {
            throw ValidationException::withMessages([
                'recipients' => 'Add at least one valid Tanzanian mobile number.',
            ]);
        }

        $scheduledAt = $this->scheduledAt($payload['scheduled_at'] ?? null);
        $totalSms = count($accepted) * $analysis['segmentCount'];

        if ($customer->sms_balance < $totalSms) {
            throw ValidationException::withMessages([
                'quantity' => 'Not enough SMS credits for this campaign.',
            ]);
        }

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
        ): Campaign {
            $message = Message::query()->create([
                'customer_id' => $customer->id,
                'body' => $messageBody,
                'encoding' => MessageEncoding::from($analysis['encoding']),
                'character_count' => $analysis['characterCount'],
                'segment_count' => $analysis['segmentCount'],
            ]);

            $campaign = Campaign::query()->create([
                'customer_id' => $customer->id,
                'sender_id_id' => $sender->id,
                'message_id' => $message->id,
                'name' => $this->campaignName($payload['name'] ?? null, $messageBody),
                'sender_name' => $sender->name,
                'status' => $status,
                'recipient_count' => count($accepted),
                'total_segments' => $totalSms,
                'scheduled_at' => $scheduledAt,
                'queued_at' => $scheduledAt === null ? now() : null,
            ]);

            $now = now();
            foreach (array_chunk($accepted, 500) as $chunk) {
                $rows = [];
                foreach ($chunk as $row) {
                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'message_id' => $message->id,
                        'contact_id' => $row['contact_id'],
                        'phone' => $row['phone'],
                        'status' => $recipientStatus->value,
                        'segment_count' => $analysis['segmentCount'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                CampaignRecipient::query()->insert($rows);
            }

            $transaction = $this->ledger->debitUsage(
                $customer->fresh() ?? $customer,
                $totalSms,
                "Campaign {$campaign->name}",
                'USE-CAMP-'.$campaign->id,
            );

            $campaign->credit_transaction_id = $transaction->id;
            $campaign->save();

            return $campaign->fresh('message') ?? $campaign;
        });

        if ($campaign->status === CampaignStatus::Queued) {
            app(CampaignDispatcher::class)->queue($campaign);
        }

        return $campaign->fresh('message') ?? $campaign;
    }

    /**
     * @param  array{source: string, numbers?: list<string>, contact_ids?: list<int>, group_id?: int|null}  $payload
     * @return array{accepted: list<array{phone: string, contact_id: int|null}>, invalid: list<string>, duplicates: int}
     */
    private function resolveRecipients(Customer $customer, array $payload): array
    {
        $source = $payload['source'];
        $raw = match ($source) {
            'numbers', 'upload' => $payload['numbers'] ?? [],
            'contacts' => $this->phonesFromContacts($customer, $payload['contact_ids'] ?? []),
            'group' => $this->phonesFromGroup($customer, $payload['group_id'] ?? null),
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
            $phone = is_array($entry) ? (string) $entry['phone'] : (string) $entry;
            $contactId = is_array($entry) ? $entry['contact_id'] : null;

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
     * @param  list<int>  $ids
     * @return list<array{phone: string, contact_id: int|null}>
     */
    private function phonesFromContacts(Customer $customer, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $customer->contacts()
            ->where('status', ContactStatus::Active)
            ->whereIn('id', $ids)
            ->get(['id', 'phone'])
            ->map(fn (Contact $contact): array => [
                'phone' => $contact->phone,
                'contact_id' => $contact->id,
            ])
            ->all();
    }

    /**
     * @return list<array{phone: string, contact_id: int|null}>
     */
    private function phonesFromGroup(Customer $customer, ?int $groupId): array
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
            ->get(['id', 'phone'])
            ->map(fn (Contact $contact): array => [
                'phone' => $contact->phone,
                'contact_id' => $contact->id,
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
