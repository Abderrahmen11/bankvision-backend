<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'branch_code' => 'BR001',
                'branch_name' => 'Downtown Main Branch',
                'address' => '100 Financial Way, Suite 100',
                'city' => 'Metropolis',
                'phone' => '555-0100',
                'status' => 'active',
            ],
            [
                'branch_code' => 'BR002',
                'branch_name' => 'Northside Retail Branch',
                'address' => '2400 Commerce Blvd',
                'city' => 'Metropolis',
                'phone' => '555-0200',
                'status' => 'active',
            ],
            [
                'branch_code' => 'BR003',
                'branch_name' => 'West End Branch',
                'address' => '850 Sunset Drive',
                'city' => 'Metropolis',
                'phone' => '555-0300',
                'status' => 'under_renovation',
            ],
            [
                'branch_code' => 'BR004',
                'branch_name' => 'Metro Airport Branch',
                'address' => 'Terminal 2 Arrivals, Airport Rd',
                'city' => 'Metropolis',
                'phone' => '555-0400',
                'status' => 'active',
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }

        // Generate 3 more random branches using the factory
        Branch::factory()->count(3)->create();
    }
}
