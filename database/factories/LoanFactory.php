<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['mortgage', 'personal', 'auto', 'business']);

        // Realistic principal ranges per loan type
        $principal = match ($type) {
            'mortgage' => $this->faker->randomFloat(2, 50000, 800000),
            'personal' => $this->faker->randomFloat(2, 1000, 50000),
            'auto'     => $this->faker->randomFloat(2, 5000, 60000),
            'business' => $this->faker->randomFloat(2, 10000, 500000),
        };

        // Realistic annual interest rates per loan type
        $interestRate = match ($type) {
            'mortgage' => $this->faker->randomFloat(2, 3.0, 7.5),
            'personal' => $this->faker->randomFloat(2, 8.0, 24.0),
            'auto'     => $this->faker->randomFloat(2, 4.0, 12.0),
            'business' => $this->faker->randomFloat(2, 5.0, 18.0),
        };

        // Realistic term lengths in months
        $termMonths = match ($type) {
            'mortgage' => $this->faker->randomElement([120, 180, 240, 300, 360]),
            'personal' => $this->faker->randomElement([12, 24, 36, 48, 60]),
            'auto'     => $this->faker->randomElement([24, 36, 48, 60, 72]),
            'business' => $this->faker->randomElement([12, 24, 36, 60, 84]),
        };

        $startDate = Carbon::parse($this->faker->dateTimeBetween('-5 years', '-1 month'));
        $endDate   = $startDate->copy()->addMonths($termMonths);

        // Simulate partial repayment: outstanding is 20%–100% of principal
        $repaidRatio        = $this->faker->randomFloat(2, 0, 0.80);
        $outstandingBalance = round($principal * (1 - $repaidRatio), 2);

        // Derive status from outstanding balance and end date
        $status = 'active';
        if ($outstandingBalance <= 0) {
            $status             = 'completed';
            $outstandingBalance = 0;
        } elseif ($endDate->isPast()) {
            $status = $this->faker->randomElement(['delinquent', 'defaulted']);
        } else {
            $status = $this->faker->randomElement(['active', 'active', 'active', 'delinquent']);
        }

        // Next payment is next month for active, overdue past date for delinquent, and null for completed/defaulted
        $nextPaymentDate = match ($status) {
            'active'     => Carbon::now()->addMonth()->startOfMonth(),
            'delinquent' => Carbon::now()->subDays(rand(5, 45)),
            default      => null, // completed, defaulted
        };

        $year = $startDate->format('Y');

        return [
            'loan_number'         => 'LN-' . $year . '-' . $this->faker->unique()->numerify('#####'),
            'customer_id'         => Customer::inRandomOrder()->first()?->id ?? Customer::factory(),
            'loan_type'           => $type,
            'principal_amount'    => $principal,
            'outstanding_balance' => $outstandingBalance,
            'interest_rate'       => $interestRate,
            'term_months'         => $termMonths,
            'start_date'          => $startDate->format('Y-m-d'),
            'end_date'            => $endDate->format('Y-m-d'),
            'status'              => $status,
            'next_payment_date'   => $nextPaymentDate?->format('Y-m-d'),
        ];
    }
}
