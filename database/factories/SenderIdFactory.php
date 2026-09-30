<?php

namespace Database\Factories;

use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use App\Models\Customer;
use App\Models\SenderId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderId>
 */
class SenderIdFactory extends Factory
{
    protected $model = SenderId::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory()->complete(),
            'sender_id_request_id' => null,
            'name' => strtoupper(fake()->unique()->lexify('??????')),
            'status' => SenderIdStatus::Active,
            'source' => SenderIdSource::Request,
            'available_to_all' => false,
        ];
    }

    public function generic(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => null,
            'sender_id_request_id' => null,
            'source' => SenderIdSource::Generic,
            'status' => SenderIdStatus::Active,
            'available_to_all' => true,
        ]);
    }
}
