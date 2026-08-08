<?php

namespace Database\Seeders;

use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Database\Seeder;

class DashboardLayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleLayoutTemplates = [
            'admin' => [
                'theme' => 'glassmorphism-dark',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_admin_kpis', 'type' => 'system_health', 'title' => 'System Health & Server Metrics', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1], 'visible' => true],
                    ['id' => 'w_audit_stream', 'type' => 'audit_trail', 'title' => 'System Audit Log Stream', 'position' => ['x' => 0, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                    ['id' => 'w_user_mgmt', 'type' => 'user_activity', 'title' => 'Active Staff & Role Distribution', 'position' => ['x' => 2, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                ],
            ],
            'manager' => [
                'theme' => 'glassmorphism-teal',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_branch_kpis', 'type' => 'branch_performance', 'title' => 'Branch Revenue & Account Growth', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1], 'visible' => true],
                    ['id' => 'w_staff_list', 'type' => 'staff_roster', 'title' => 'Branch Employee Performance', 'position' => ['x' => 0, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                    ['id' => 'w_customer_feed', 'type' => 'recent_customers', 'title' => 'New Customer Onboarding', 'position' => ['x' => 2, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                ],
            ],
            'compliance' => [
                'theme' => 'glassmorphism-amber',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_alerts_feed', 'type' => 'compliance_alerts', 'title' => 'Compliance & Fraud Alerts', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2], 'visible' => true],
                    ['id' => 'w_kyc_monitor', 'type' => 'kyc_expiry_list', 'title' => 'KYC Expiration Queue', 'position' => ['x' => 0, 'y' => 2, 'w' => 2, 'h' => 2], 'visible' => true],
                    ['id' => 'w_aml_transactions', 'type' => 'aml_flagged_txns', 'title' => 'Flagged AML Transactions', 'position' => ['x' => 2, 'y' => 2, 'w' => 2, 'h' => 2], 'visible' => true],
                ],
            ],
            'analyst' => [
                'theme' => 'glassmorphism-purple',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_risk_heatmap', 'type' => 'risk_matrix', 'title' => 'Portfolio Risk Matrix', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1], 'visible' => true],
                    ['id' => 'w_loan_delinquency', 'type' => 'delinquent_loans', 'title' => 'Delinquent & Defaulted Loans', 'position' => ['x' => 0, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                    ['id' => 'w_large_wires', 'type' => 'large_wires', 'title' => 'High Value Wires Monitor', 'position' => ['x' => 2, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                ],
            ],
            'csr' => [
                'theme' => 'glassmorphism-blue',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_cust_lookup', 'type' => 'quick_lookup', 'title' => 'Customer & Account Search', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1], 'visible' => true],
                    ['id' => 'w_ticket_queue', 'type' => 'service_requests', 'title' => 'Assigned Service Requests', 'position' => ['x' => 0, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                    ['id' => 'w_txns_feed', 'type' => 'live_transactions', 'title' => 'Recent Branch Activity', 'position' => ['x' => 2, 'y' => 1, 'w' => 2, 'h' => 2], 'visible' => true],
                ],
            ],
            'auditor' => [
                'theme' => 'glassmorphism-slate',
                'columns' => 4,
                'widgets' => [
                    ['id' => 'w_audit_full', 'type' => 'audit_trail_full', 'title' => 'Master Audit Trail & Overrides', 'position' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2], 'visible' => true],
                    ['id' => 'w_read_only_txns', 'type' => 'txn_history', 'title' => 'Immutable Transaction Ledger', 'position' => ['x' => 0, 'y' => 2, 'w' => 4, 'h' => 2], 'visible' => true],
                ],
            ],
        ];

        // Seed a custom layout for every employee in the system based on their role
        User::all()->each(function (User $user) use ($roleLayoutTemplates) {
            $role = $user->role ?? 'csr';
            $layoutData = $roleLayoutTemplates[$role] ?? $roleLayoutTemplates['csr'];

            DashboardLayout::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'layout_data' => $layoutData,
                    'is_default' => false,
                ]
            );
        });
    }
}
