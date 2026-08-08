<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'suspicious_transaction',
            'suspicious_transaction', // weighted: most common
            'kyc_expiring',
            'login_attempt',
            'loan_delinquent',
        ]);

        // Alert type determines appropriate severity
        $severity = match ($type) {
            'suspicious_transaction' => $this->faker->randomElement(['high', 'high', 'medium']),
            'kyc_expiring'           => $this->faker->randomElement(['medium', 'low']),
            'login_attempt'          => $this->faker->randomElement(['high', 'medium']),
            'loan_delinquent'        => $this->faker->randomElement(['medium', 'low']),
        };

        // Realistic description per alert type
        $description = match ($type) {
            'suspicious_transaction' => $this->faker->randomElement([
                'Large cash transaction exceeding reporting threshold detected.',
                'Multiple rapid withdrawals flagged by fraud detection system.',
                'Transaction pattern inconsistent with customer profile.',
                'Wire transfer to high-risk jurisdiction flagged for review.',
            ]),
            'kyc_expiring' => $this->faker->randomElement([
                'Customer KYC documents are expiring within 30 days.',
                'Identity verification renewal required for continued service.',
                'AML screening must be refreshed before next review cycle.',
            ]),
            'login_attempt' => $this->faker->randomElement([
                'Multiple failed login attempts detected from unknown IP.',
                'Login from unrecognized device and geographic location.',
                'Brute-force attempt detected on customer portal account.',
            ]),
            'loan_delinquent' => $this->faker->randomElement([
                'Loan payment overdue by more than 30 days.',
                'Customer has missed two consecutive monthly payments.',
                'Loan account approaching default threshold — escalation required.',
            ]),
        };

        // Randomly pick a polymorphic subject from available entities
        $subjects = [
            [Customer::class,    Customer::inRandomOrder()->first()?->id],
            [Account::class,     Account::inRandomOrder()->first()?->id],
            [Transaction::class, Transaction::inRandomOrder()->first()?->id],
            [Loan::class,        Loan::inRandomOrder()->first()?->id],
        ];
        $subject = $this->faker->randomElement($subjects);

        $status = $this->faker->randomElement([
            'open', 'open', 'open',  // 3/5 open (most alerts unresolved)
            'in-progress',           // 1/5
            'resolved',              // 1/5
        ]);

        $resolvedAt = ($status === 'resolved')
            ? $this->faker->dateTimeBetween('-3 months', 'now')
            : null;

        $createdAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $year      = (is_object($createdAt) ? $createdAt : new \DateTime($createdAt))->format('Y');

        return [
            'alert_number'   => 'ALT-' . $year . '-' . $this->faker->unique()->numerify('#####'),
            'alert_type'     => $type,
            'severity'       => $severity,
            'description'    => $description,
            'resolved_at'    => $resolvedAt,
            'status'         => $status,
            'assigned_to'    => User::inRandomOrder()->first()?->id,
            'alertable_type' => $subject[0],
            'alertable_id'   => $subject[1],
        ];
    }
}
