<?php

namespace Database\Factories;

use App\Models\ContactGroup;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactGroup>
 */
class ContactGroupFactory extends Factory
{
    protected $model = ContactGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }
}
