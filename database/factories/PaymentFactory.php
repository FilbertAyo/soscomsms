<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = 10000;
        $unitPrice = 22;

        return [
            'customer_id' => Customer::factory()->complete(),
            'quantity' => $quantity,
            'unit_price_tzs' => $unitPrice,
            'amount_tzs' => $quantity * $unitPrice,
            'method' => PaymentMethod::Snippe,
            'lipa_namba' => null,
            'payer_reference' => '+255754221890',
            'status' => PaymentStatus::Pending,
            'expires_at' => now()->addHours(72),
        ];
    }
}
