<?php

namespace Database\Factories;

use App\Models\SarFiling;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SarFiling>
 */
class SarFilingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference'       => 'SAR-' . date('Y') . '-' . strtoupper($this->faker->unique()->bothify('######')),
            'customer_name'   => $this->faker->company(),
            'customer_number' => 'CUST-' . $this->faker->numberBetween(1000, 9999),
            'category'        => $this->faker->randomElement(SarFiling::CATEGORIES),
            'amount'          => $this->faker->randomFloat(2, 5000, 500000),
            'status'          => $this->faker->randomElement(['under_review', 'under_review', 'filed', 'escalated']),
            'narrative'       => $this->faker->paragraph(),
            'action_taken'    => $this->faker->randomElement([
                'Account Flagged & Monitoring Active',
                'FinCEN BSA Form 111 Transmitted',
                'Accounts Frozen & Legal Notified',
            ]),
            'user_id'         => User::factory(),
        ];
    }
}
