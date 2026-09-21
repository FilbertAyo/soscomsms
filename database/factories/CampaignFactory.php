<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SenderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = Customer::factory()->complete();

        return [
            'customer_id' => $customer,
            'sender_id_id' => SenderId::factory()->generic(),
            'message_id' => Message::factory()->state([
                'customer_id' => $customer,
            ]),
            'name' => 'School fee reminder',
            'sender_name' => 'MWALIKO',
            'status' => CampaignStatus::Queued,
            'recipient_count' => 0,
            'total_segments' => 0,
            'queued_at' => now(),
        ];
    }
}
