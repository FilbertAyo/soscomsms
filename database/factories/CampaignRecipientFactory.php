<?php

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tail = str_pad((string) fake()->unique()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);

        return [
            'campaign_id' => Campaign::factory(),
            'message_id' => Message::factory(),
            'phone' => '+2557'.$tail,
            'status' => CampaignRecipientStatus::Queued,
            'segment_count' => 1,
        ];
    }
}
