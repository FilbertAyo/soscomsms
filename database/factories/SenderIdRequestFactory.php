<?php

namespace Database\Factories;

use App\Enums\SenderIdRequestStatus;
use App\Models\Customer;
use App\Models\SenderIdRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderIdRequest>
 */
class SenderIdRequestFactory extends Factory
{
    protected $model = SenderIdRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory()->complete(),
            'name' => strtoupper(fake()->unique()->lexify('??????')),
            'purpose' => 'Business notifications',
            'organization' => fake()->company(),
            'description' => fake()->sentence(),
            'status' => SenderIdRequestStatus::Submitted,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdRequestStatus::UnderReview,
        ]);
    }

    public function submittedToProvider(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdRequestStatus::SubmittedToProvider,
            'submitted_to_provider_at' => now(),
        ]);
    }

    public function needsResubmission(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdRequestStatus::NeedsResubmission,
            'rejection_reason' => 'The identity PDF could not be opened.',
        ]);
    }
}
