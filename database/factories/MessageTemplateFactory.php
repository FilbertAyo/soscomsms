<?php

namespace Database\Factories;

use App\Enums\MessageEncoding;
use App\Enums\MessageTemplateType;
use App\Models\Customer;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = 'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba.';

        return [
            'customer_id' => Customer::factory()->complete(),
            'name' => fake()->unique()->words(2, true),
            'type' => MessageTemplateType::Transactional,
            'body' => $body,
            'encoding' => MessageEncoding::Gsm,
            'character_count' => strlen($body),
            'segment_count' => 1,
        ];
    }
}
