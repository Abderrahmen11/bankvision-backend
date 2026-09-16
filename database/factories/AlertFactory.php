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

        // Map alert_type to appropriate polymorphic target entity
        $targetMap = [
            'suspicious_transaction' => [Transaction::class, fn () => Transaction::inRandomOrder()->first()?->id ?? Transaction::factory()],
            'kyc_expiring'           => [Customer::class,    fn () => Customer::inRandomOrder()->first()?->id ?? Customer::factory()],
            'login_attempt'          => [Customer::class,    fn () => Customer::inRandomOrder()->first()?->id ?? Customer::factory()],
            'loan_delinquent'        => [Loan::class,        fn () => Loan::inRandomOrder()->first()?->id ?? Loan::factory()],
        ];

        $target      = $targetMap[$type];
        $alertableId = call_user_func($target[1]);

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
            'alertable_type' => $target[0],
            'alertable_id'   => $alertableId,
            'assigned_to'    => function (array $attributes) {
                // 30% chance unassigned
                if (fake()->boolean(30)) {
                    return null;
                }

                $morphClass = $attributes['alertable_type'] ?? null;
                $entityId   = $attributes['alertable_id'] ?? null;

                $entityBranchId = $this->resolveEntityBranchId($morphClass, $entityId);
                if (!$entityBranchId) {
                    return null;
                }

                return User::where('branch_id', $entityBranchId)
                    ->where('status', 'active')
                    ->whereNotIn('role', ['admin'])
                    ->inRandomOrder()
                    ->value('id');
            },
        ];
    }

    /**
     * Derive the branch_id of the entity this alert is about.
     * Returns null if the entity doesn't exist yet (factory-created inline).
     */
    private function resolveEntityBranchId(?string $morphClass, mixed $id): ?int
    {
        if ($id instanceof \Illuminate\Database\Eloquent\Model) {
            $id = $id->id;
        }

        if (!$morphClass || (!is_int($id) && !is_string($id))) {
            return null; // factory instance, not yet persisted
        }

        return match ($morphClass) {
            Customer::class    => Customer::find($id)?->branch_id,
            Account::class     => Account::with('customer')->find($id)?->customer?->branch_id,
            Transaction::class => Transaction::with('account.customer')->find($id)?->account?->customer?->branch_id,
            Loan::class        => Loan::with('customer')->find($id)?->customer?->branch_id,
            default            => null,
        };
    }
}
