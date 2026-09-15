<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Enums\AuditAction;
use App\Services\AuditService;use App\Models\User;

class DashboardLayoutService
{
    /**
     * Get user's layout or generate and persist their role default layout.
     */
    public function getOrCreateLayout(User $user): DashboardLayout
    {
        $layout = DashboardLayout::where('user_id', $user->id)->first();

        if ($layout) {
            return $layout;
        }

        $defaultData = $this->getDefaultLayoutForRole($user->role ?? 'csr');

        return DashboardLayout::create([
            'user_id'     => $user->id,
            'layout_data' => $defaultData,
            'is_default'  => true,
        ]);
    }

    /**
     * Save user customized layout.
     */
    public function saveLayout(User $user, array $layoutData): DashboardLayout
    {
        $layout = DashboardLayout::updateOrCreate(
            ['user_id' => $user->id],
            [
                'layout_data' => $layoutData,
                'is_default'  => false,
            ]
        );

        AuditService::log($user, AuditAction::LayoutSaved, 'dashboard_layouts', $layout->id, [], [
            'widgets' => count($layoutData['widgets'] ?? []),
        ]);

        return $layout;
    }

    /**
     * Reset user layout back to role-based default.
     */
    public function resetLayout(User $user): DashboardLayout
    {
        $defaultData = $this->getDefaultLayoutForRole($user->role ?? 'csr');

        $layout = DashboardLayout::updateOrCreate(
            ['user_id' => $user->id],
            [
                'layout_data' => $defaultData,
                'is_default'  => true,
            ]
        );

        AuditService::log($user, AuditAction::LayoutReset, 'dashboard_layouts', $layout->id);

        return $layout;
    }

    /**
     * Get predefined default widget grid configuration for a given role.
     */
    public function getDefaultLayoutForRole(string $role): array
    {
        $layouts = [
            'admin' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-dark',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Key Performance Indicators',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-system-health',
                        'type'     => 'system_health',
                        'title'    => 'System Health & Metrics',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => ['refreshInterval' => 30],
                    ],
                    [
                        'id'       => 'widget-top-branches',
                        'type'     => 'top_branches',
                        'title'    => 'Top Performing Branches',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => ['refreshInterval' => 120],
                    ],
                    [
                        'id'       => 'widget-transaction-chart',
                        'type'     => 'transaction_chart',
                        'title'    => 'Transaction Volume Trends',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 10, 'w' => 8, 'h' => 6],
                        'settings' => ['days' => 30],
                    ],
                    [
                        'id'       => 'widget-alerts-panel',
                        'type'     => 'alerts_panel',
                        'title'    => 'System & Security Alerts',
                        'visible'  => true,
                        'position' => ['x' => 8, 'y' => 10, 'w' => 4, 'h' => 6],
                        'settings' => ['limit' => 5],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Recent Transactions Ledger',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 16, 'w' => 12, 'h' => 6],
                        'settings' => ['limit' => 10],
                    ],
                ],
            ],

            'manager' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-teal',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Branch Overview & Metrics',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-transaction-chart',
                        'type'     => 'transaction_chart',
                        'title'    => 'Transaction Activity',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 8, 'h' => 6],
                        'settings' => ['days' => 30],
                    ],
                    [
                        'id'       => 'widget-top-branches',
                        'type'     => 'top_branches',
                        'title'    => 'Branch Benchmarks',
                        'visible'  => true,
                        'position' => ['x' => 8, 'y' => 4, 'w' => 4, 'h' => 6],
                        'settings' => ['refreshInterval' => 120],
                    ],
                    [
                        'id'       => 'widget-loan-portfolio',
                        'type'     => 'loan_portfolio',
                        'title'    => 'Branch Loan Portfolio',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 10, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-account-distribution',
                        'type'     => 'account_distribution',
                        'title'    => 'Account Types Distribution',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 10, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Branch Transactions',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 16, 'w' => 12, 'h' => 6],
                        'settings' => ['limit' => 10],
                    ],
                ],
            ],

            'compliance' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-amber',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Compliance Overview',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-compliance',
                        'type'     => 'compliance',
                        'title'    => 'KYC & AML Compliance Status',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-alerts-panel',
                        'type'     => 'alerts_panel',
                        'title'    => 'High-Risk & Fraud Alerts',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => ['severity' => 'critical'],
                    ],
                    [
                        'id'       => 'widget-transaction-chart',
                        'type'     => 'transaction_chart',
                        'title'    => 'Suspicious Transaction Velocity',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 10, 'w' => 7, 'h' => 6],
                        'settings' => ['days' => 30],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Flagged & High-Value Transactions',
                        'visible'  => true,
                        'position' => ['x' => 7, 'y' => 10, 'w' => 5, 'h' => 6],
                        'settings' => ['limit' => 8],
                    ],
                ],
            ],

            'analyst' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-purple',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Analytics & Market Metrics',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-transaction-chart',
                        'type'     => 'transaction_chart',
                        'title'    => 'Transaction Volume Trends',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 8, 'h' => 6],
                        'settings' => ['days' => 30],
                    ],
                    [
                        'id'       => 'widget-loan-portfolio',
                        'type'     => 'loan_portfolio',
                        'title'    => 'Loan Delinquency & Risk Matrix',
                        'visible'  => true,
                        'position' => ['x' => 8, 'y' => 4, 'w' => 4, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-account-distribution',
                        'type'     => 'account_distribution',
                        'title'    => 'Deposit & Account Analysis',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 10, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-top-branches',
                        'type'     => 'top_branches',
                        'title'    => 'Regional Branch Performance',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 10, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Volume Sampling Stream',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 16, 'w' => 12, 'h' => 6],
                        'settings' => ['limit' => 10],
                    ],
                ],
            ],

            'csr' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-blue',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Daily Service Desk KPIs',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Recent Transactions',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 8, 'h' => 7],
                        'settings' => ['limit' => 10],
                    ],
                    [
                        'id'       => 'widget-alerts-panel',
                        'type'     => 'alerts_panel',
                        'title'    => 'Customer Service Alerts',
                        'visible'  => true,
                        'position' => ['x' => 8, 'y' => 4, 'w' => 4, 'h' => 7],
                        'settings' => ['limit' => 6],
                    ],
                    [
                        'id'       => 'widget-account-distribution',
                        'type'     => 'account_distribution',
                        'title'    => 'Account Types Overview',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 11, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-compliance',
                        'type'     => 'compliance',
                        'title'    => 'Customer KYC Status',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 11, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                ],
            ],

            'auditor' => [
                'columns' => 12,
                'theme'   => 'glassmorphism-slate',
                'widgets' => [
                    [
                        'id'       => 'widget-stats',
                        'type'     => 'stats',
                        'title'    => 'Audit & Risk Summary',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        'settings' => ['refreshInterval' => 60],
                    ],
                    [
                        'id'       => 'widget-compliance',
                        'type'     => 'compliance',
                        'title'    => 'Regulatory Compliance Overview',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => [],
                    ],
                    [
                        'id'       => 'widget-alerts-panel',
                        'type'     => 'alerts_panel',
                        'title'    => 'Audit Alerts & Violations',
                        'visible'  => true,
                        'position' => ['x' => 6, 'y' => 4, 'w' => 6, 'h' => 6],
                        'settings' => ['limit' => 8],
                    ],
                    [
                        'id'       => 'widget-recent-transactions',
                        'type'     => 'recent_transactions',
                        'title'    => 'Transaction Audit Trail',
                        'visible'  => true,
                        'position' => ['x' => 0, 'y' => 10, 'w' => 8, 'h' => 6],
                        'settings' => ['limit' => 12],
                    ],
                ],
            ],
        ];

        return $layouts[$role] ?? $layouts['csr'];
    }
}
