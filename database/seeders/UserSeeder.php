<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Target headcount per branch (strictly between 8 and 17).
     * Flagship urban branches have 14–17 staff, regional branches 10–13,
     * and smaller points of sale / suburban branches 8–9.
     */
    public const BRANCH_HEADCOUNTS = [
        1  => 12, // Nabeul (Cap Bon Regional Hub)
        2  => 10, // Hammamet (Touristic Hub)
        3  => 8,  // Bir-Mchergua (Small Agency)
        4  => 9,  // Grombalia (Agricultural / Commercial)
        5  => 8,  // Menzel Bouzelfa (Local Branch)
        6  => 8,  // Korba (Coastal Local Branch)
        7  => 15, // EL MANAR (Capital Financial Hub)
        8  => 12, // BAB Souika (Medina Commercial Center)
        9  => 8,  // UTIQUE (Northern Rural Branch)
        10 => 11, // Agence Akouda (Sousse North)
        11 => 14, // Agence Alyssa (University & Business Campus)
        12 => 16, // Agence Avenue de Carthage (Downtown Tunis)
        13 => 15, // Agence Avenue de France (Downtown Tunis)
        14 => 17, // Agence Avenue de Paris I (Flagship Downtown)
        15 => 13, // Agence Avenue de Paris II (Downtown Secondary)
        16 => 14, // Agence Sfax Nord (Industrial Hub - Poudriere)
        17 => 15, // Agence Sfax Medina (Historical Commercial Center)
        18 => 10, // Agence Sfax Marbourg (Commercial Center)
        19 => 14, // Agence Sfax El Jadida (Regional Headquarters)
        20 => 11, // Agence Sfax Harzallah (Business District)
        21 => 13, // Agence Sfax Hached (Major Avenue)
        22 => 12, // Agence Sfax Habib Thameur (Commercial Axis)
        23 => 11, // Agence Sfax Gremda (Residential / Commercial Km 1.5)
        24 => 10, // Agence Sfax El Habib (Industrial Axis Km 3)
        25 => 16, // Agence Sfax Center (Financial Center)
        26 => 12, // Agence Sfax av 18 janvier 1952 (Urban Core)
        27 => 11, // Agence Sfax 5 aout (Commercial Axis)
        28 => 13, // Agence Sfax 2000 (Modern Business District)
        29 => 8,  // Agence Sayada (Sahel Coastal Agency)
        30 => 11, // Agence Sakiet Ezzit (Sfax Km 6.5)
        31 => 10, // Agence Sakiet Eddayer (Sfax Km 7)
        32 => 14, // Agence Sahloul 2 (Sousse Medical & Residential Hub)
        33 => 9,  // Agence Sahline (Monastir Road)
        34 => 17, // Agence Mohamed V (Flagship Banking District Tunis)
        35 => 11, // Agence Monfleury (South Tunis Hub)
        36 => 15, // Agence Montplaisir (Tunis Business & Tech Hub)
        37 => 12, // Agence Sfax Route de l'Aéroport (Transit & Logistics Hub)
        38 => 8,  // Point de Vente DenDen (West Tunis Express Counter)
    ];

    /**
     * The six guaranteed persona accounts in Branch 1 (Nabeul) — one per role, always ACTIVE.
     * Password for all: password
     */
    public const PERSONAS = [
        [
            'name'  => 'System Administrator',
            'email' => 'admin@bankvision.com',
            'role'  => 'admin',
        ],
        [
            'name'  => 'Branch Manager',
            'email' => 'manager@bankvision.com',
            'role'  => 'manager',
        ],
        [
            'name'  => 'Compliance Officer',
            'email' => 'compliance@bankvision.com',
            'role'  => 'compliance',
        ],
        [
            'name'  => 'Senior Analyst',
            'email' => 'analyst@bankvision.com',
            'role'  => 'analyst',
        ],
        [
            'name'  => 'Customer Service Rep',
            'email' => 'csr@bankvision.com',
            'role'  => 'csr',
        ],
        [
            'name'  => 'Internal Auditor',
            'email' => 'auditor@bankvision.com',
            'role'  => 'auditor',
        ],
    ];

    /**
     * Authentic Tunisian first names.
     */
    private const FIRST_NAMES = [
        'Mohamed', 'Ahmed', 'Youssef', 'Yassine', 'Amin', 'Karim', 'Hamza', 'Omar', 'Mehdi',
        'Tarek', 'Bilel', 'Anis', 'Zied', 'Firas', 'Walid', 'Hichem', 'Bassem', 'Mourad',
        'Sami', 'Khaled', 'Nabil', 'Chaker', 'Haythem', 'Ramzi', 'Sofiene', 'Raouf', 'Skander',
        'Kais', 'Aymen', 'Oussama', 'Moncef', 'Bechir', 'Riadh', 'Lassaad', 'Jamel', 'Faouzi',
        'Meriem', 'Fatma', 'Nour', 'Salma', 'Ines', 'Yasmine', 'Sarah', 'Rim', 'Nadia',
        'Cyrine', 'Amira', 'Houda', 'Olfa', 'Sondes', 'Dorsaf', 'Souhir', 'Manel', 'Asma',
        'Marwa', 'Nesrine', 'Leila', 'Khadija', 'Aya', 'Eya', 'Rania', 'Sirine', 'Wafa',
        'Ghada', 'Hela', 'Selima', 'Mouna', 'Sabrine', 'Ikram', 'Jihene', 'Sonia', 'Mariem',
    ];

    /**
     * Authentic Tunisian last names.
     */
    private const LAST_NAMES = [
        'Trabelsi', 'Chaabane', 'Khemir', 'Bouazizi', 'Gharbi', 'Dridi', 'Jaziri', 'Ben Ammar',
        'Mansour', 'Zouari', 'Louati', 'Rekik', 'Ghorbel', 'Fakhfakh', 'Karray', 'Triki',
        'Sellami', 'Hachicha', 'Kammoun', 'Masmoudi', 'Damak', 'Daoud', 'Ben Ayed', 'Affes',
        'Jallouli', 'Fourati', 'Mezghani', 'Chaari', 'Baccour', 'Abid', 'Mahjoub', 'Cheikh',
        'Riahi', 'Ayari', 'Ghariani', 'Ben Romdhane', 'Ayadi', 'Ellouze', 'Kolsi', 'Baklouti',
        'Khlif', 'Maalej', 'Jarraya', 'Sahnoun', 'Bouzid', 'Cherif', 'Hammami', 'Ben Salah',
        'Ben Ali', 'Mabrouk', 'Mejri', 'Nasri', 'Saidi', 'Slimani', 'Boukadida', 'Ben Salem',
    ];

    public function run(): void
    {
        $branches = Branch::orderBy('id')->get();
        if ($branches->isEmpty()) {
            return;
        }

        $hashedPassword = Hash::make('password');
        $usedEmails = [];

        // Track manager accounts defined in BranchSeeder
        $branchManagers = BranchSeeder::MANAGERS ?? [];

        // -----------------------------------------------------------------------
        // Process each branch: Guarantee ALL 6 role types & 8 to 17 users total.
        // Roles: manager, admin, compliance, analyst, auditor, csr
        // -----------------------------------------------------------------------
        foreach ($branches as $branch) {
            $branchId = (int) $branch->id;
            $targetCount = self::BRANCH_HEADCOUNTS[$branchId] ?? min(17, max(8, rand(9, 14)));

            $seededForBranch = [];

            // 1. Seed or resolve the Dedicated Branch Manager (Always Active)
            if ($branchId === 1) {
                $mgrPersona = self::PERSONAS[1]; // manager@bankvision.com
                $manager = User::updateOrCreate(
                    ['email' => $mgrPersona['email']],
                    [
                        'name'              => $mgrPersona['name'],
                        'role'              => 'manager',
                        'status'            => 'active',
                        'password'          => $hashedPassword,
                        'email_verified_at' => now(),
                        'branch_id'         => $branchId,
                        'phone'             => $branch->phone ?? '31 372 000',
                        'last_login_at'     => now()->subHours(rand(1, 48)),
                    ]
                );
            } elseif (isset($branchManagers[$branchId])) {
                $mgrData = $branchManagers[$branchId];
                $manager = User::updateOrCreate(
                    ['email' => $mgrData['email']],
                    [
                        'name'              => $mgrData['name'],
                        'role'              => 'manager',
                        'status'            => 'active',
                        'password'          => $hashedPassword,
                        'email_verified_at' => now(),
                        'branch_id'         => $branchId,
                        'phone'             => $mgrData['phone'],
                        'last_login_at'     => now()->subHours(rand(1, 48)),
                    ]
                );
            } else {
                $manager = User::where('branch_id', $branchId)->where('role', 'manager')->first();
                if (!$manager) {
                    $manager = User::create([
                        'name'              => 'Directeur ' . $branch->branch_name,
                        'email'             => 'manager.b' . $branchId . '@bankvision.com',
                        'role'              => 'manager',
                        'status'            => 'active',
                        'password'          => $hashedPassword,
                        'email_verified_at' => now(),
                        'branch_id'         => $branchId,
                        'phone'             => $branch->phone,
                        'last_login_at'     => now()->subHours(rand(1, 48)),
                    ]);
                }
            }

            $branch->update(['manager_id' => $manager->id]);
            $usedEmails[$manager->email] = true;
            $seededForBranch[] = $manager->id;

            // 2. For Branch 1: Seed the remaining 5 guaranteed personas
            if ($branchId === 1) {
                foreach (self::PERSONAS as $persona) {
                    if ($persona['role'] === 'manager') {
                        continue; // already seeded above
                    }
                    $u = User::updateOrCreate(
                        ['email' => $persona['email']],
                        [
                            'name'              => $persona['name'],
                            'role'              => $persona['role'],
                            'status'            => 'active',
                            'password'          => $hashedPassword,
                            'email_verified_at' => now(),
                            'branch_id'         => $branchId,
                            'phone'             => '31 372 00' . rand(0, 9),
                            'last_login_at'     => now()->subHours(rand(1, 72)),
                        ]
                    );
                    $usedEmails[$u->email] = true;
                    $seededForBranch[] = $u->id;
                }
            }

            // 3. Determine required role distribution for remaining slots
            // We must guarantee every branch has:
            // - admin (at least 1)
            // - compliance (at least 1, 2 if targetCount >= 14)
            // - analyst (at least 1, 2 if targetCount >= 15)
            // - auditor (at least 1)
            // - csr (all remaining slots, at least 3)
            $rolesToSeed = [];

            if ($branchId !== 1) {
                // Roles needed for non-persona branches:
                $rolesToSeed[] = 'admin';
                $rolesToSeed[] = 'compliance';
                if ($targetCount >= 14) {
                    $rolesToSeed[] = 'compliance';
                }
                $rolesToSeed[] = 'analyst';
                if ($targetCount >= 15) {
                    $rolesToSeed[] = 'analyst';
                }
                $rolesToSeed[] = 'auditor';
            }

            // Fill all remaining slots up to $targetCount with CSRs
            $currentCount = count($seededForBranch) + count($rolesToSeed);
            $csrSlots = max(1, $targetCount - $currentCount);
            for ($i = 0; $i < $csrSlots; $i++) {
                $rolesToSeed[] = 'csr';
            }

            // Seed each required staff member with realistic names & emails
            $staffIndex = 1;
            foreach ($rolesToSeed as $role) {
                $name = $this->generateUniqueName($branchId, $staffIndex, $usedEmails);
                $email = $this->generateUniqueEmail($name, $branchId, $role, $staffIndex, $usedEmails);
                $phone = $this->generateBranchPhone($branch->city, $branchId, $staffIndex);

                // Ensure at least 1 active user for each role, with rare pending/suspended for others
                $status = 'active';
                if ($role === 'csr' && $staffIndex > 5) {
                    $rand = rand(1, 100);
                    if ($rand <= 4) {
                        $status = 'suspended';
                    } elseif ($rand <= 8) {
                        $status = 'pending';
                    }
                }

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name'              => $name,
                        'role'              => $role,
                        'status'            => $status,
                        'password'          => $hashedPassword,
                        'email_verified_at' => now(),
                        'branch_id'         => $branchId,
                        'phone'             => $phone,
                        'last_login_at'     => $status === 'active' ? now()->subHours(rand(2, 240)) : null,
                    ]
                );

                $usedEmails[$user->email] = true;
                $seededForBranch[] = $user->id;
                $staffIndex++;
            }

            // Ensure any extra users previously assigned to this branch that aren't in this seeded set
            // are safely re-assigned or cleaned up so branch count is strictly between 8 and 17.
            $extraUsers = User::where('branch_id', $branchId)
                ->whereNotIn('id', $seededForBranch)
                ->get();

            foreach ($extraUsers as $extra) {
                // If the user has relations that prevent deletion, keep them as csr if within 17 cap
                if (count($seededForBranch) < 17) {
                    $extra->update(['role' => 'csr']);
                    $seededForBranch[] = $extra->id;
                } else {
                    // Safe cleanup of orphan legacy seed users
                    if ($extra->managedCustomers()->count() === 0 && $extra->auditLogs()->count() === 0) {
                        $extra->delete();
                    }
                }
            }
        }

        // -----------------------------------------------------------------------
        // Final Safety Guards:
        // 1. All managers must be active.
        // 2. Every branch must have an active manager_id set.
        // 3. Suspended users bank-wide capped at <= 5%.
        // -----------------------------------------------------------------------
        User::where('role', 'manager')->update(['status' => 'active']);

        foreach ($branches as $branch) {
            $hasActiveManager = User::where('branch_id', $branch->id)
                ->where('role', 'manager')
                ->where('status', 'active')
                ->first();

            if ($hasActiveManager) {
                $branch->update(['manager_id' => $hasActiveManager->id]);
            }
        }

        $totalUsers = User::count();
        $maxSuspended = max(1, (int) floor($totalUsers * 0.05));
        $suspendedUsers = User::where('status', 'suspended')
            ->where('role', '!=', 'admin')
            ->where('role', '!=', 'manager')
            ->orderBy('id')
            ->get();

        if ($suspendedUsers->count() > $maxSuspended) {
            $suspendedUsers->skip($maxSuspended)->each(fn (User $u) => $u->update(['status' => 'active']));
        }
    }

    /**
     * Generate a realistic Tunisian name.
     */
    private function generateUniqueName(int $branchId, int $index, array &$usedEmails): string
    {
        $fnCount = count(self::FIRST_NAMES);
        $lnCount = count(self::LAST_NAMES);

        $fnIndex = ($branchId * 17 + $index * 7) % $fnCount;
        $lnIndex = ($branchId * 13 + $index * 11) % $lnCount;

        return self::FIRST_NAMES[$fnIndex] . ' ' . self::LAST_NAMES[$lnIndex];
    }

    /**
     * Generate a unique professional email address.
     */
    private function generateUniqueEmail(string $name, int $branchId, string $role, int $index, array &$usedEmails): string
    {
        $base = strtolower(Str::slug($name, '.'));
        $email = "{$base}.b{$branchId}@bankvision.com";

        if (isset($usedEmails[$email])) {
            $email = "{$base}.{$role}.b{$branchId}@bankvision.com";
        }

        if (isset($usedEmails[$email])) {
            $email = "{$base}.b{$branchId}.{$index}@bankvision.com";
        }

        return $email;
    }

    /**
     * Generate realistic Tunisian phone numbers by region/branch.
     */
    private function generateBranchPhone(?string $city, int $branchId, int $index): string
    {
        $seq = sprintf('%03d', ($branchId * 10 + $index) % 900 + 100);

        if ($city === 'Sfax') {
            return "31 372 {$seq}";
        }
        if ($city === 'Sousse' || $city === 'Sahline' || $city === 'Sayada') {
            return "31 372 " . sprintf('3%02d', ($index * 7) % 90 + 10);
        }

        return "31 372 {$seq}";
    }
}
