<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['savings', 'checking', 'business']);

        // Realistic balance ranges per account type
        $balance = match ($type) {
            'savings'  => $this->faker->randomFloat(2, 500, 50000),
            'checking' => $this->faker->randomFloat(2, 100, 15000),
            'business' => $this->faker->randomFloat(2, 5000, 500000),
        };

        // Realistic interest rates per account type (annual %)
        $interestRate = match ($type) {
            'savings'  => $this->faker->randomFloat(2, 1.5, 5.0),
            'checking' => $this->faker->randomFloat(2, 0, 1.0),
            'business' => $this->faker->randomFloat(2, 0.5, 3.5),
        };

        $year = $this->faker->numberBetween(2018, 2025);

        return [
            'account_number' => 'ACC-' . $year . '-' . $this->faker->unique()->numerify('#####'),
            'customer_id'    => Customer::inRandomOrder()->first()?->id ?? Customer::factory(),
            'account_type'   => $type,
            'currency'       => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'USD', 'USD']), // USD weighted
            'balance'        => $balance,
            'status'         => $this->faker->randomElement(['active', 'active', 'active', 'frozen', 'closed']), // active weighted
            'opened_date'    => $this->faker->dateTimeBetween('-7 years', 'now')->format('Y-m-d'),
            'interest_rate'  => $interestRate,
        ];
    }
}
