<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => null,
            'phone' => null,
            'job_title' => null,
            'status' => CustomerStatus::PendingProfile,
            'sms_balance' => 0,
        ];
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_name' => fake()->company(),
            'phone' => '+255754221890',
            'job_title' => 'Owner',
            'status' => CustomerStatus::Active,
        ]);
    }
}
