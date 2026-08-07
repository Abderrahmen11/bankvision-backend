<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Loan;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 40 realistic loans: 35 random + 5 guaranteed high-risk records.
     */
    public function run(): void
    {
        // 35 random loans spread across random customers
        Loan::factory()->count(35)->create();

        // 5 guaranteed delinquent/defaulted loans for compliance/risk dashboard testing
        $highRiskCustomers = Customer::inRandomOrder()->take(5)->get();

        foreach ($highRiskCustomers as $customer) {
            Loan::factory()->create([
                'customer_id'         => $customer->id,
                'status'              => fake()->randomElement(['delinquent', 'defaulted']),
                'next_payment_date'   => null,
                'outstanding_balance' => fake()->randomFloat(2, 5000, 100000),
            ]);
        }
    }
}
