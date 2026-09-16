<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Random non-manager staff to generate (excluding the 6 guaranteed personas
     * and the exactly-one-manager-per-branch accounts).
     */
    private const RANDOM_STAFF_COUNT = 35;

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
        $branches    = Branch::orderBy('id')->get();
        $firstBranch = $branches->first();

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
                    'branch_id'         => $firstBranch?->id,
                    'phone'             => fake()->phoneNumber(),
                ]
            );
        }

        // -----------------------------------------------------------------------
        // 2. Enforce single-admin rule.
        // -----------------------------------------------------------------------
        User::where('role', 'admin')
            ->where('email', '!=', 'admin@bankvision.com')
            ->delete();

        // -----------------------------------------------------------------------
        // 3. Ensure EXACTLY ONE active manager per branch.
        //    - Promote the persona manager to the first branch (already done above).
        //    - For every other branch, find an existing active manager already
        //      assigned there. If none exists, create one. Suspend/demote any
        //      extra managers in that branch to prevent duplicates.
        // -----------------------------------------------------------------------
        foreach ($branches as $branch) {
            $branchManagers = User::where('role', 'manager')
                ->where('branch_id', $branch->id)
                ->where('email', '!=', 'manager@bankvision.com') // persona handled separately
                ->get();

            // Is the persona manager assigned to this branch?
            $personaManagerHere = ($branch->id === $firstBranch?->id);

            if ($personaManagerHere) {
                // First branch already has the persona manager — demote/suspend extras.
                $branchManagers->each(function (User $u) {
                    // Extra managers in first branch: make them CSR instead
                    $u->update(['role' => 'csr']);
                });
                // Point branch at persona manager
                $personaManager = User::where('email', 'manager@bankvision.com')->first();
                if ($personaManager) {
                    $branch->update(['manager_id' => $personaManager->id]);
                }
                continue;
            }

            if ($branchManagers->count() >= 1) {
                // Keep the first one active, convert the rest to csr
                $keeper = $branchManagers->first();
                $keeper->update(['status' => 'active']);
                $branchManagers->skip(1)->each(fn (User $u) => $u->update(['role' => 'csr']));
                $branch->update(['manager_id' => $keeper->id]);
            } else {
                // No manager yet — create exactly one
                $newManager = User::factory()->manager()->active()->create([
                    'branch_id' => $branch->id,
                ]);
                $branch->update(['manager_id' => $newManager->id]);
            }
        }

        // -----------------------------------------------------------------------
        // 4. Generate random non-manager staff (csr, compliance, analyst, auditor).
        //    The factory now uses 90/5/5 active/suspended/pending weights.
        // -----------------------------------------------------------------------
        User::factory()
            ->count(self::RANDOM_STAFF_COUNT)
            ->state(['role' => fake()->randomElement(['csr', 'compliance', 'analyst', 'auditor'])])
            ->create();

        // -----------------------------------------------------------------------
        // 5. Hard cap: ensure total suspended users ≤ 5% of total staff (max 1-2 bank-wide).
        //    Convert any excess suspended users to active (oldest first).
        //    Managers are NEVER suspended — always active.
        // -----------------------------------------------------------------------
        User::where('role', 'manager')->where('status', '!=', 'active')->update(['status' => 'active']);

        $totalUsers     = User::count();
        $maxSuspended   = min(2, (int) floor($totalUsers * 0.05));
        $suspendedUsers = User::where('status', 'suspended')
            ->where('role', '!=', 'admin')
            ->where('role', '!=', 'manager')
            ->orderBy('id')
            ->get();

        if ($suspendedUsers->count() > $maxSuspended) {
            $suspendedUsers->skip($maxSuspended)->each(fn (User $u) => $u->update(['status' => 'active']));
        }

        // -----------------------------------------------------------------------
        // 6. Safety net: no branch should be left without an active manager.
        // -----------------------------------------------------------------------
        foreach ($branches as $branch) {
            $hasActiveManager = User::where('branch_id', $branch->id)
                ->where('role', 'manager')
                ->where('status', 'active')
                ->exists();

            if (!$hasActiveManager) {
                $rescue = User::factory()->manager()->active()->create([
                    'branch_id' => $branch->id,
                ]);
                $branch->update(['manager_id' => $rescue->id]);
            }
        }
    }
}
