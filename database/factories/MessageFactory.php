<?php

namespace Database\Factories;

use App\Enums\MessageEncoding;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = 'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba.';

        return [
            'customer_id' => Customer::factory()->complete(),
            'body' => $body,
            'encoding' => MessageEncoding::Gsm,
            'character_count' => strlen($body),
            'segment_count' => 1,
        ];
    }
}
