<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\CampaignRecipient;
use App\Models\Delivery;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_recipient_id' => CampaignRecipient::factory(),
            'provider_id' => Provider::factory(),
            'status' => DeliveryStatus::Pending,
        ];
    }
}
