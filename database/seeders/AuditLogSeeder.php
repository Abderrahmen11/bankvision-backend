<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates 120+ audit log entries distributed across employees.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            AuditLog::factory()->count(120)->create();
            return;
        }

        // Generate 5-8 audit entries for each employee to create realistic timeline data
        foreach ($users as $user) {
            $count = rand(5, 8);

            AuditLog::factory()->count($count)->create([
                'user_id' => $user->id,
            ]);
        }

        // Add 10 explicit high-impact administrative actions for audit dashboard testing
        AuditLog::factory()->count(10)->create([
            'action' => 'approve',
            'table_name' => 'transactions',
            'new_values' => ['status' => 'approved', 'amount' => 150000.00, 'flag' => 'high_value_override'],
        ]);
    }
}
