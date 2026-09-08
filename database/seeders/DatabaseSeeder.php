<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed branches first (users need branch FKs)
        $this->call(BranchSeeder::class);

        // 2. Seed staff users (personas + random) and link branch managers
        $this->call(UserSeeder::class);

        // 3. Seed the bank customers
        $this->call(CustomerSeeder::class);

        // 4. Seed bank accounts (1–3 per customer)
        $this->call(AccountSeeder::class);

        // 5. Seed transactions (8–12 per account → 500+ records)
        $this->call(TransactionSeeder::class);

        // 6. Seed customer loans (40 records: 35 random + 5 high-risk)
        $this->call(LoanSeeder::class);

        // 7. Seed alerts (33+ records)
        $this->call(AlertSeeder::class);

        // 8. Seed audit logs (120+ entries across employees)
        $this->call(AuditLogSeeder::class);

        // 9. Seed drag & drop dashboard layouts for all users (role-tailored)
        $this->call(DashboardLayoutSeeder::class);
    }
}
