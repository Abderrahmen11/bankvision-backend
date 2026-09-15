<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $analyst;
    private User $manager;
    private User $csr;
    private Branch $branch;
    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch      = Branch::factory()->create(['status' => 'active']);
        $this->otherBranch = Branch::factory()->create(['status' => 'active']);
        $this->admin       = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->analyst     = User::factory()->create(['role' => 'analyst', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager     = User::factory()->create(['role' => 'manager', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr         = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // ─── Stats ───────────────────────────────────────────────────────────────────

    public function test_dashboard_stats_returns_expected_keys(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'total_customers',
                    'total_accounts',
                    'total_transactions',
                    'total_loans',
                    'open_alerts',
                    'flagged_transactions',
                    'pending_loans',
                    'active_accounts',
                ],
            ]);
    }

    public function test_dashboard_stats_reflect_actual_counts(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id, 'status' => 'active']);

        Transaction::factory()->create(['account_id' => $account->id, 'status' => 'flagged']);
        Loan::factory()->create(['customer_id' => $customer->id, 'status' => 'pending']);
        Alert::factory()->create(['status' => 'open']);

        $response = $this->getJson('/api/dashboard/stats');
        $data = $response->json('data');

        $this->assertEquals(1, $data['total_customers']);
        $this->assertEquals(1, $data['total_accounts']);
        $this->assertEquals(1, $data['active_accounts']);
        $this->assertEquals(1, $data['total_transactions']);
        $this->assertEquals(1, $data['flagged_transactions']);
        $this->assertEquals(1, $data['total_loans']);
        $this->assertEquals(1, $data['pending_loans']);
        $this->assertEquals(1, $data['open_alerts']);
    }

    public function test_unauthenticated_user_cannot_access_dashboard_stats(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }

    // ─── Chart Data ──────────────────────────────────────────────────────────────

    public function test_chart_data_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->analyst);

        $response = $this->getJson('/api/dashboard/chart-data');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_chart_data_returns_transactions_within_last_30_days(): void
    {
        Sanctum::actingAs($this->analyst);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id]);

        // Recent transaction (should appear)
        Transaction::factory()->create([
            'account_id'       => $account->id,
            'transaction_date' => now()->subDays(5),
            'status'           => 'completed',
        ]);

        // Old transaction (should NOT appear)
        Transaction::factory()->create([
            'account_id'       => $account->id,
            'transaction_date' => now()->subDays(60),
            'status'           => 'completed',
        ]);

        $response = $this->getJson('/api/dashboard/chart-data');
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['count']);
    }

    // ─── Recent Activity ─────────────────────────────────────────────────────────

    public function test_recent_activity_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/recent-activity');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'recent_transactions',
                    'recent_alerts',
                ],
            ]);
    }

    public function test_recent_activity_returns_latest_transactions_and_open_alerts(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id]);

        Transaction::factory()->count(3)->create(['account_id' => $account->id, 'status' => 'completed']);
        Alert::factory()->count(2)->create(['status' => 'open']);
        Alert::factory()->create(['status' => 'resolved']); // should not appear

        $response = $this->getJson('/api/dashboard/recent-activity');
        $data = $response->json('data');

        $this->assertCount(3, $data['recent_transactions']);
        $this->assertCount(2, $data['recent_alerts']); // only open alerts

        // Check transaction structure
        $txn = $data['recent_transactions'][0];
        $this->assertArrayHasKey('type', $txn);
        $this->assertArrayHasKey('number', $txn);
        $this->assertArrayHasKey('description', $txn);
        $this->assertArrayHasKey('status', $txn);
        $this->assertEquals('transaction', $txn['type']);

        // Check alert structure
        $alert = $data['recent_alerts'][0];
        $this->assertArrayHasKey('type', $alert);
        $this->assertArrayHasKey('severity', $alert);
        $this->assertEquals('alert', $alert['type']);
    }

    public function test_recent_activity_limits_to_10_items_each(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id]);

        Transaction::factory()->count(15)->create(['account_id' => $account->id]);
        Alert::factory()->count(12)->create(['status' => 'open']);

        $response = $this->getJson('/api/dashboard/recent-activity');
        $data = $response->json('data');

        $this->assertCount(10, $data['recent_transactions']);
        $this->assertCount(10, $data['recent_alerts']);
    }

    // ─── Manager Branch Scoping Tests ───────────────────────────────────────────

    public function test_manager_dashboard_stats_scoped_to_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        // Branch 1 (Manager's branch) data
        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account1  = Account::factory()->create(['customer_id' => $customer1->id, 'status' => 'active', 'balance' => 5000]);
        Transaction::factory()->create(['account_id' => $account1->id, 'status' => 'flagged']);
        Loan::factory()->create(['customer_id' => $customer1->id, 'status' => 'pending']);

        // Other branch data
        $customer2 = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $account2  = Account::factory()->create(['customer_id' => $customer2->id, 'status' => 'active', 'balance' => 10000]);
        Transaction::factory()->create(['account_id' => $account2->id, 'status' => 'flagged']);
        Loan::factory()->create(['customer_id' => $customer2->id, 'status' => 'pending']);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(1, $data['total_customers']);
        $this->assertEquals(1, $data['total_accounts']);
        $this->assertEquals(1, $data['active_accounts']);
        $this->assertEquals(5000.00, (float) $data['branch_balance']);
        $this->assertEquals(1, $data['total_transactions']);
        $this->assertEquals(1, $data['flagged_transactions']);
        $this->assertEquals(1, $data['total_loans']);
        $this->assertEquals(1, $data['pending_loans']);
    }

    public function test_manager_chart_data_scoped_to_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account1  = Account::factory()->create(['customer_id' => $customer1->id]);
        Transaction::factory()->create([
            'account_id'       => $account1->id,
            'transaction_date' => now()->subDays(2),
            'amount'           => 1500,
            'status'           => 'completed',
        ]);

        $customer2 = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $account2  = Account::factory()->create(['customer_id' => $customer2->id]);
        Transaction::factory()->create([
            'account_id'       => $account2->id,
            'transaction_date' => now()->subDays(2),
            'amount'           => 9000,
            'status'           => 'completed',
        ]);

        $response = $this->getJson('/api/dashboard/chart-data');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['count']);
        $this->assertEquals(1500, (float) $data[0]['volume']);
    }

    public function test_manager_recent_activity_scoped_to_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        // Branch 1 transaction & alert
        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account1  = Account::factory()->create(['customer_id' => $customer1->id]);
        $t1 = Transaction::factory()->create(['account_id' => $account1->id, 'status' => 'completed']);
        $a1 = Alert::factory()->create([
            'status'         => 'open',
            'alertable_type' => Customer::class,
            'alertable_id'   => $customer1->id,
            'assigned_to'    => null,
        ]);

        // Branch 2 transaction & alert
        $customer2 = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $account2  = Account::factory()->create(['customer_id' => $customer2->id]);
        $t2 = Transaction::factory()->create(['account_id' => $account2->id, 'status' => 'completed']);
        $a2 = Alert::factory()->create([
            'status'         => 'open',
            'alertable_type' => Customer::class,
            'alertable_id'   => $customer2->id,
            'assigned_to'    => null,
        ]);

        $response = $this->getJson('/api/dashboard/recent-activity');
        $data = $response->json('data');

        $txIds = collect($data['recent_transactions'])->pluck('id')->all();
        $alertIds = collect($data['recent_alerts'])->pluck('id')->all();

        $this->assertContains($t1->id, $txIds);
        $this->assertNotContains($t2->id, $txIds);
        $this->assertContains($a1->id, $alertIds);
        $this->assertNotContains($a2->id, $alertIds);
    }

    // ─── Analyst Analytical Tests ───────────────────────────────────────────────

    public function test_analyst_can_access_dashboard_stats_with_analytical_distributions(): void
    {
        Sanctum::actingAs($this->analyst);

        Customer::factory()->create(['branch_id' => $this->branch->id, 'risk_level' => 'high']);
        Account::factory()->create(['balance' => 4000, 'status' => 'active']);
        Loan::factory()->create(['loan_type' => 'personal', 'principal_amount' => 10000, 'status' => 'active']);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_customers',
                    'total_accounts',
                    'total_deposits',
                    'average_account_balance',
                    'total_transactions',
                    'transaction_volume',
                    'total_loans',
                    'total_loan_amount',
                    'loan_default_rate',
                    'customer_growth',
                    'loan_distribution',
                    'customer_risk_distribution',
                    'branch_comparisons',
                ],
            ]);
    }

    public function test_analyst_can_access_risk_analysis(): void
    {
        Sanctum::actingAs($this->analyst);

        Customer::factory()->create(['branch_id' => $this->branch->id, 'risk_level' => 'high']);
        Loan::factory()->create(['status' => 'delinquent', 'outstanding_balance' => 8000]);

        $response = $this->getJson('/api/dashboard/risk-analysis');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer_risk' => ['total_customers', 'high_risk_count', 'medium_risk_count', 'low_risk_count'],
                    'loan_risk' => ['total_loans', 'delinquent_loans', 'defaulted_loans', 'total_exposed_balance'],
                    'transaction_risk' => ['flagged_count', 'flagged_volume', 'wire_count', 'wire_volume'],
                    'branch_risk',
                ],
            ]);
    }

    public function test_analyst_can_access_analytical_reports(): void
    {
        Sanctum::actingAs($this->analyst);

        Account::factory()->create(['balance' => 5000]);
        Loan::factory()->create(['principal_amount' => 3000, 'outstanding_balance' => 2500]);
        Transaction::factory()->create(['transaction_type' => 'deposit', 'channel' => 'online', 'amount' => 500]);

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overview' => ['total_revenue', 'total_expenses', 'net_profit', 'profit_margin', 'transaction_volume', 'total_deposits', 'total_loan_outstanding'],
                    'income_statement' => ['revenue', 'expenses', 'net_income_before_tax'],
                    'balance_sheet',
                    'transaction_analytics' => ['by_type', 'by_channel', 'total_count', 'total_volume'],
                    'loan_analytics' => ['portfolio_summary', 'status_breakdown'],
                    'risk_compliance' => ['customer_risk', 'kyc_status', 'aml_alerts'],
                    'branch_performance',
                ],
            ]);
    }

    public function test_csr_dashboard_stats_scoped_to_assigned_branch_and_daily_operations(): void
    {
        Sanctum::actingAs($this->csr);

        // Branch 1 (CSR's branch) customer, account, transaction today
        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account1  = Account::factory()->create(['customer_id' => $customer1->id, 'balance' => 3000]);
        Transaction::factory()->create([
            'account_id'       => $account1->id,
            'amount'           => 300,
            'transaction_date' => today(),
            'status'           => 'completed',
        ]);

        // Branch 2 customer & account
        $customer2 = Customer::factory()->create(['branch_id' => $this->otherBranch->id]);
        $account2  = Account::factory()->create(['customer_id' => $customer2->id, 'balance' => 9000]);
        Transaction::factory()->create([
            'account_id'       => $account2->id,
            'amount'           => 900,
            'transaction_date' => today(),
            'status'           => 'completed',
        ]);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_customers',
                    'new_customers_today',
                    'customers_served_today',
                    'total_accounts',
                    'active_accounts',
                    'total_deposits',
                    'today_transactions',
                    'pending_transactions',
                    'pending_requests',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_customers']);
        $this->assertEquals(1, $data['total_accounts']);
        $this->assertEquals(3000, (float) $data['total_deposits']);
        $this->assertEquals(1, $data['today_transactions']);
        $this->assertEquals(1, $data['customers_served_today']);
    }
}

