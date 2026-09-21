<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tail = str_pad((string) fake()->unique()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);

        return [
            'customer_id' => Customer::factory(),
            'contact_group_id' => null,
            'name' => fake()->name(),
            'phone' => '+2557'.$tail,
            'status' => ContactStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Inactive,
        ]);
    }
}
