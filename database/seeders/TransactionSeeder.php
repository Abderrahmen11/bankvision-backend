<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates 8–12 transactions per account → ~500–900 total records.
     */
    public function run(): void
    {
        Account::all()->each(function (Account $account) {
            $count = rand(8, 12);

            Transaction::factory()->count($count)->create([
                'account_id' => $account->id,
                'currency'   => $account->currency, // match the account's currency
            ]);
        });
    }
}
