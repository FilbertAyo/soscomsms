<?php

namespace Database\Seeders;

use App\Enums\SenderIdRequestStatus;
use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use App\Models\Campaign;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Database\Seeder;

class SenderIdSeeder extends Seeder
{
    public function run(): void
    {
        $names = config('sms.generic_sender_ids', ['HARUSI', 'SEND OFF', 'MWALIKO', 'MCHANGO']);

        $obsolete = SenderId::query()
            ->where('source', SenderIdSource::Generic)
            ->whereNotIn('name', $names)
            ->get();

        $usedIds = Campaign::query()
            ->whereIn('sender_id_id', $obsolete->pluck('id'))
            ->pluck('sender_id_id');

        SenderId::query()
            ->whereIn('id', $obsolete->pluck('id')->diff($usedIds))
            ->delete();

        SenderId::query()
            ->whereIn('id', $usedIds)
            ->update(['status' => SenderIdStatus::Inactive]);

        foreach ($names as $name) {
            SenderId::query()->updateOrCreate(
                ['name' => $name],
                [
                    'customer_id' => null,
                    'sender_id_request_id' => null,
                    'status' => SenderIdStatus::Active,
                    'source' => SenderIdSource::Generic,
                    'available_to_all' => true,
                ],
            );
        }

        $customer = User::query()->where('email', 'customer@soscom.co.tz')->first()?->customer;

        if ($customer === null) {
            return;
        }

        $customer->senderIdRequests()->updateOrCreate(
            ['name' => 'LINKADI'],
            [
                'purpose' => 'Customer updates',
                'organization' => $customer->business_name ?? 'Linkadi Ltd',
                'description' => 'Use LINKADI on customer notifications.',
                'status' => SenderIdRequestStatus::UnderReview,
            ],
        );
    }
}
