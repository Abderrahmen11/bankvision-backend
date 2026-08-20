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
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch  = Branch::factory()->create(['status' => 'active']);
        $this->admin   = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->analyst = User::factory()->create(['role' => 'analyst', 'status' => 'active', 'branch_id' => $this->branch->id]);
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
}
