<?php

namespace Database\Seeders;

use App\Models\DashboardLayout;
use App\Models\User;
use App\Services\DashboardLayoutService;
use Illuminate\Database\Seeder;

class DashboardLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(DashboardLayoutService $layoutService): void
    {
        // Seed a custom 12-column widget layout for every user in the system based on their role
        User::all()->each(function (User $user) use ($layoutService) {
            $role = $user->role ?? 'csr';
            $layoutData = $layoutService->getDefaultLayoutForRole($role);

            DashboardLayout::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'layout_data' => $layoutData,
                    'is_default'  => true,
                ]
            );
        });
    }
}
