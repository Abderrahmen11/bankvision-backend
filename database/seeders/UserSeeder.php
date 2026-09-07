<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Total random staff users to generate (excluding the 6 guaranteed personas).
     */
    private const RANDOM_STAFF_COUNT = 40;

    /**
     * The six guaranteed persona accounts — one per role, always ACTIVE.
     * Password for all: password
     */
    private const PERSONAS = [
        [
            'name'   => 'System Administrator',
            'email'  => 'admin@bankvision.com',
            'role'   => 'admin',
        ],
        [
            'name'   => 'Branch Manager',
            'email'  => 'manager@bankvision.com',
            'role'   => 'manager',
        ],
        [
            'name'   => 'Compliance Officer',
            'email'  => 'compliance@bankvision.com',
            'role'   => 'compliance',
        ],
        [
            'name'   => 'Senior Analyst',
            'email'  => 'analyst@bankvision.com',
            'role'   => 'analyst',
        ],
        [
            'name'   => 'Customer Service Rep',
            'email'  => 'csr@bankvision.com',
            'role'   => 'csr',
        ],
        [
            'name'   => 'Internal Auditor',
            'email'  => 'auditor@bankvision.com',
            'role'   => 'auditor',
        ],
    ];

    public function run(): void
    {
        $firstBranchId = Branch::orderBy('id')->value('id');

        // -----------------------------------------------------------------------
        // 1. Upsert the six guaranteed persona accounts (always active).
        //    Using updateOrCreate so re-running the seeder is safe (idempotent).
        // -----------------------------------------------------------------------
        foreach (self::PERSONAS as $persona) {
            User::updateOrCreate(
                ['email' => $persona['email']],
                [
                    'name'              => $persona['name'],
                    'role'              => $persona['role'],
                    'status'            => 'active',
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'branch_id'         => $firstBranchId,
                    'phone'             => fake()->phoneNumber(),
                ]
            );
        }

        // -----------------------------------------------------------------------
        // 2. Enforce single-admin rule: delete any admin rows that are NOT the
        //    guaranteed persona (defensive guard against previous bad seeds).
        // -----------------------------------------------------------------------
        User::where('role', 'admin')
            ->where('email', '!=', 'admin@bankvision.com')
            ->delete();

        // -----------------------------------------------------------------------
        // 3. Generate random staff with weighted role + status distribution.
        //    The factory never produces admin users, so this is safe.
        // -----------------------------------------------------------------------
        User::factory()
            ->count(self::RANDOM_STAFF_COUNT)
            ->create();

        // -----------------------------------------------------------------------
        // 4. Ensure at least one active manager per branch for FK constraints.
        //    Link each branch to a manager (or create one if none exist yet).
        // -----------------------------------------------------------------------
        $branches = Branch::all();
        $managers = User::where('role', 'manager')->where('status', 'active')->get();

        if ($managers->isEmpty()) {
            $managers = User::factory()
                ->manager()
                ->active()
                ->count($branches->count())
                ->create();
        }

        foreach ($branches as $branch) {
            // Prefer a manager already in that branch; otherwise pick any active manager
            $manager = $managers->firstWhere('branch_id', $branch->id)
                ?? $managers->random();

            $manager->update(['branch_id' => $branch->id]);
            $branch->update(['manager_id'  => $manager->id]);
        }
    }
}
