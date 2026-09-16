<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();

        if ($branches->isEmpty()) {
            Customer::factory()->count(50)->create();
            return;
        }

        $totalCustomers = 50;
        $branchesCount = $branches->count();
        $baseCount = intdiv($totalCustomers, $branchesCount);
        $remainder = $totalCustomers % $branchesCount;

        foreach ($branches as $index => $branch) {
            $count = $baseCount + ($index < $remainder ? 1 : 0);
            $branchUsers = User::where('branch_id', $branch->id)
                ->where('status', 'active')
                ->whereIn('role', ['manager', 'csr'])
                ->pluck('id');

            Customer::factory()->count($count)->create([
                'branch_id'               => $branch->id,
                'relationship_manager_id' => fn () => $branchUsers->isNotEmpty() ? $branchUsers->random() : null,
            ]);
        }
    }
}
