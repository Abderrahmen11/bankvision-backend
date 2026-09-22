<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction): void {
            if ($transaction->account_id) {
                $transaction->currency = Account::find($transaction->account_id)?->currency ?? $transaction->currency;
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'deposit', 'deposit',                      // weighted: deposits more common
            'withdrawal', 'withdrawal',
            'transfer',
            'wire',
        ]);

        // Realistic amount ranges per transaction type
        $amount = match ($type) {
            'deposit'    => $this->faker->randomFloat(2, 50, 25000),
            'withdrawal' => $this->faker->randomFloat(2, 20, 5000),
            'transfer'   => $this->faker->randomFloat(2, 100, 50000),
            'wire'       => $this->faker->randomFloat(2, 500, 250000),
        };

        // Counterparty only makes sense for transfers and wires
        $counterparty = in_array($type, ['transfer', 'wire'])
            ? $this->faker->company()
            : null;

        $status = $this->faker->randomElement([
            'completed', 'completed', 'completed', 'completed', // 4/7 chance completed
            'pending',                                           // 1/7
            'failed',                                            // 1/7
            'flagged',                                           // 1/7
        ]);

        $transactionDate = $this->faker->dateTimeBetween('-3 years', 'now');

        // Large or flagged transactions need an approver
        $needsApproval = $amount > 10000 || $status === 'flagged';
        $approvedBy    = $needsApproval ? (User::inRandomOrder()->first()?->id) : null;
        $approvedAt    = ($approvedBy && $status === 'completed')
            ? $this->faker->dateTimeBetween($transactionDate, 'now')
            : null;

        $year = $transactionDate->format('Y');

        return [
            'transaction_number' => 'TXN-' . $year . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999999), 7, '0', STR_PAD_LEFT),
            'account_id'         => Account::inRandomOrder()->first()?->id ?? Account::factory(),
            'transaction_type'   => $type,
            'amount'             => $amount,
            'currency'           => $this->faker->randomElement(['USD', 'USD', 'USD', 'EUR', 'GBP']),
            'transaction_date'   => $transactionDate,
            'description'        => $this->faker->optional(0.7)->sentence(),
            'status'             => $status,
            'channel'            => $this->faker->randomElement(['online', 'online', 'branch', 'atm', 'mobile']),
            'counterparty'       => $counterparty,
            'approved_by'        => $approvedBy,
            'approved_at'        => $approvedAt,
        ];
    }
}
