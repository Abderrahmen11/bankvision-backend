<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Loop through every customer and create 1–3 accounts for each
        Customer::all()->each(function (Customer $customer) {
            $count = rand(1, 3);

            Account::factory()->count($count)->create([
                'customer_id' => $customer->id,
            ]);
        });
    }
}
