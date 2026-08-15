<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $action = $this->faker->randomElement([
            'view', 'view', 'view',              // 3/10 view actions
            'update', 'update',                  // 2/10 update actions
            'approve',                           // 1/10 approve
            'reject',                            // 1/10 reject
            'create',                            // 1/10 create
            'login',                             // 1/10 login
            'logout',                            // 1/10 logout
        ]);

        $tableName = match ($action) {
            'login', 'logout' => 'users',
            'approve', 'reject' => $this->faker->randomElement(['transactions', 'loans', 'alerts']),
            default => $this->faker->randomElement(['customers', 'accounts', 'transactions', 'loans', 'alerts', 'users']),
        };

        $modelMap = [
            'users'        => \App\Models\User::class,
            'customers'    => \App\Models\Customer::class,
            'accounts'     => \App\Models\Account::class,
            'transactions' => \App\Models\Transaction::class,
            'loans'        => \App\Models\Loan::class,
            'alerts'       => \App\Models\Alert::class,
        ];

        $modelClass = $modelMap[$tableName] ?? \App\Models\User::class;
        $recordId   = $modelClass::inRandomOrder()->first()?->id ?? $modelClass::factory()->create()->id;

        // Generate contextual old_values and new_values JSON arrays based on action
        $oldValues = null;
        $newValues = null;

        if ($action === 'update') {
            if ($tableName === 'customers') {
                $oldValues = ['risk_level' => 'low', 'kyc_status' => 'pending'];
                $newValues = ['risk_level' => 'medium', 'kyc_status' => 'verified'];
            } elseif ($tableName === 'accounts') {
                $oldValues = ['status' => 'active'];
                $newValues = ['status' => 'frozen'];
            } else {
                $oldValues = ['status' => 'pending'];
                $newValues = ['status' => 'active'];
            }
        } elseif ($action === 'create') {
            $newValues = ['id' => $recordId, 'created_by' => 'system'];
        } elseif ($action === 'delete') {
            $oldValues = ['id' => $recordId, 'status' => 'deleted'];
        } elseif ($action === 'approve') {
            $oldValues = ['status' => 'pending'];
            $newValues = ['status' => 'approved', 'approved_at' => now()->toDateTimeString()];
        } elseif ($action === 'reject') {
            $oldValues = ['status' => 'pending'];
            $newValues = ['status' => 'rejected', 'reason' => 'Compliance check failure'];
        }

        // 10% chance of system/deleted user action (user_id = null)
        $userId = $this->faker->boolean(90)
            ? (User::inRandomOrder()->first()?->id ?? User::factory())
            : null;

        return [
            'user_id'    => $userId,
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
