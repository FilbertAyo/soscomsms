<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Customer;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory()->complete(),
            'name' => 'Delivery webhook',
            'url' => 'https://hooks.example.test/soscom',
            'secret' => 'whsec_'.bin2hex(random_bytes(24)),
            'events' => [WebhookEvent::DeliveryUpdated->value, WebhookEvent::CampaignCompleted->value],
            'enabled' => true,
        ];
    }
}
