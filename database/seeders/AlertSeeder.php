<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 33 total alerts: 25 random + 5 high-severity + 3 unassigned.
     */
    public function run(): void
    {
        // 25 random alerts across all types and severities
        Alert::factory()->count(25)->create();

        // 5 guaranteed HIGH-severity open alerts (visible on dashboard immediately)
        $flaggedTransactions = Transaction::where('status', 'flagged')
            ->inRandomOrder()->take(5)->get();

        if ($flaggedTransactions->isEmpty()) {
            $flaggedTransactions = Transaction::inRandomOrder()->take(5)->get();
            $flaggedTransactions->each->update(['status' => 'flagged']);
        }

        foreach ($flaggedTransactions as $transaction) {
            $branchId = $transaction->account?->customer?->branch_id;
            $assigneeId = null;
            if ($branchId && fake()->boolean(70)) {
                $assigneeId = User::where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->whereNotIn('role', ['admin'])
                    ->inRandomOrder()
                    ->value('id');
            }

            Alert::factory()->create([
                'alert_type'     => 'suspicious_transaction',
                'severity'       => 'high',
                'status'         => 'open',
                'description'    => 'Flagged transaction requires immediate compliance review.',
                'resolved_at'    => null,
                'alertable_type' => Transaction::class,
                'alertable_id'   => $transaction->id,
                'assigned_to'    => $assigneeId,
            ]);
        }

        // 3 unassigned alerts to test the unassigned queue UI
        Alert::factory()->count(3)->create([
            'assigned_to' => null,
            'status'      => 'open',
        ]);

        // 5 KYC expiry alerts linked to customers
        $customers = Customer::where('kyc_status', 'expired')
            ->orWhere('kyc_status', 'pending')
            ->inRandomOrder()->take(5)->get();

        foreach ($customers as $customer) {
            $branchId = $customer->branch_id;
            $assigneeId = null;
            if ($branchId && fake()->boolean(70)) {
                $assigneeId = User::where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->whereNotIn('role', ['admin'])
                    ->inRandomOrder()
                    ->value('id');
            }

            Alert::factory()->create([
                'alert_type'     => 'kyc_expiring',
                'severity'       => 'medium',
                'status'         => 'open',
                'description'    => 'Customer KYC status requires renewal or verification.',
                'alertable_type' => Customer::class,
                'alertable_id'   => $customer->id,
                'assigned_to'    => $assigneeId,
            ]);
        }
    }
}
