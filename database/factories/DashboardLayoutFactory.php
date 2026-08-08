<?php

namespace Database\Factories;

use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardLayout>
 */
class DashboardLayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'layout_data' => [
                'theme' => 'glassmorphism-dark',
                'columns' => 4,
                'widgets' => [
                    [
                        'id' => 'w_kpi_summary',
                        'type' => 'kpi_summary',
                        'title' => 'Executive KPI Overview',
                        'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1],
                        'visible' => true,
                    ],
                    [
                        'id' => 'w_recent_transactions',
                        'type' => 'recent_transactions',
                        'title' => 'Live Financial Transactions',
                        'position' => ['x' => 0, 'y' => 1, 'w' => 2, 'h' => 2],
                        'visible' => true,
                    ],
                    [
                        'id' => 'w_compliance_alerts',
                        'type' => 'compliance_alerts',
                        'title' => 'Security & Compliance Queue',
                        'position' => ['x' => 2, 'y' => 1, 'w' => 2, 'h' => 2],
                        'visible' => true,
                    ],
                    [
                        'id' => 'w_loan_overview',
                        'type' => 'loan_overview',
                        'title' => 'Loan Portfolio Risk',
                        'position' => ['x' => 0, 'y' => 3, 'w' => 4, 'h' => 2],
                        'visible' => true,
                    ],
                ],
            ],
            'is_default' => false,
        ];
    }
}
