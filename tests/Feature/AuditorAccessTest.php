<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditorAccessTest extends TestCase
{
    use RefreshDatabase;

    private User   $auditor;
    private User   $admin;
    private User   $csr;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch  = Branch::factory()->create(['status' => 'active']);
        $this->admin   = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor = User::factory()->create(['role' => 'auditor', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr     = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // --- Audit Log Access -------------------------------------------------------

    public function test_auditor_can_list_all_audit_logs(): void
    {
        Sanctum::actingAs($this->auditor);
        AuditLog::factory()->count(5)->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
        // Observers add system rows during setUp — assert the seeded logs are visible.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $seeded = AuditLog::where('user_id', $this->admin->id)->where('action', 'login')->pluck('id')->all();
        $this->assertGreaterThanOrEqual(5, count($ids));
        foreach ($seeded as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_auditor_sees_all_audit_logs_without_restriction(): void
    {
        Sanctum::actingAs($this->auditor);

        AuditLog::factory()->create(['table_name' => 'customers',    'action' => 'update']);
        AuditLog::factory()->create(['table_name' => 'transactions', 'action' => 'flag']);
        AuditLog::factory()->create(['table_name' => 'users',        'action' => 'login']);
        AuditLog::factory()->create(['table_name' => 'branches',     'action' => 'update']);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
        // Observers add system rows — assert all four seeded logs are visible.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $seeded = AuditLog::whereIn('action', ['update', 'flag', 'login'])
            ->whereIn('table_name', ['customers', 'transactions', 'users', 'branches'])
            ->pluck('id')->all();
        $this->assertGreaterThanOrEqual(4, count($ids));
        foreach ($seeded as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_auditor_can_filter_audit_logs_by_ip_address(): void
    {
        Sanctum::actingAs($this->auditor);

        AuditLog::factory()->create(['ip_address' => '10.0.0.5']);
        AuditLog::factory()->create(['ip_address' => '192.168.1.1']);

        $response = $this->getJson('/api/audit-logs?ip_address=10.0.0');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('10.0.0', $response->json('data.0.ip_address'));
    }

    public function test_auditor_can_filter_audit_logs_by_role(): void
    {
        Sanctum::actingAs($this->auditor);

        $adminLog = AuditLog::factory()->create(['user_id' => $this->admin->id]);
        $csrLog   = AuditLog::factory()->create(['user_id' => $this->csr->id]);

        $response = $this->getJson('/api/audit-logs?role=admin');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($adminLog->id, $ids);
        $this->assertNotContains($csrLog->id, $ids);
    }

    public function test_auditor_can_filter_audit_logs_by_record_id(): void
    {
        Sanctum::actingAs($this->auditor);

        AuditLog::factory()->create(['record_id' => 42, 'table_name' => 'customers']);
        AuditLog::factory()->create(['record_id' => 99, 'table_name' => 'transactions']);

        $response = $this->getJson('/api/audit-logs?record_id=42');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(42, $response->json('data.0.record_id'));
    }

    public function test_auditor_can_sort_audit_logs_by_ip_address(): void
    {
        Sanctum::actingAs($this->auditor);
        AuditLog::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $this->getJson('/api/audit-logs?sort_by=ip_address&sort_direction=asc')
            ->assertStatus(200);
    }

    public function test_auditor_can_view_single_audit_log(): void
    {
        Sanctum::actingAs($this->auditor);
        $log = AuditLog::factory()->create(['user_id' => $this->admin->id]);

        $this->getJson("/api/audit-logs/{$log->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $log->id);
    }

    // --- Audit Dashboard Stats --------------------------------------------------

    public function test_auditor_can_access_audit_stats_endpoint(): void
    {
        Sanctum::actingAs($this->auditor);
        AuditLog::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/dashboard/audit-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_audit_logs',
                    'today_audit_logs',
                    'this_week_audit_logs',
                    'audit_by_action',
                    'audit_by_resource',
                    'audit_by_role',
                    'flagged_transactions',
                    'flagged_volume',
                    'wire_volume',
                    'open_alerts',
                    'high_risk_customers',
                    'delinquent_loans',
                    'defaulted_loans',
                    'recent_audit_timeline',
                ],
            ]);
    }

    public function test_admin_can_access_audit_stats_endpoint(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/dashboard/audit-stats')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_csr_cannot_access_audit_stats(): void
    {
        Sanctum::actingAs($this->csr);

        $this->getJson('/api/dashboard/audit-stats')->assertStatus(403);
    }

    public function test_auditor_can_access_audit_report_endpoint(): void
    {
        Sanctum::actingAs($this->auditor);

        $this->getJson('/api/dashboard/audit-report')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_activity_summary',
                    'destructive_actions',
                    'high_risk_customers',
                    'flagged_transactions',
                    'at_risk_loans',
                ],
            ]);
    }

    public function test_csr_cannot_access_audit_report(): void
    {
        Sanctum::actingAs($this->csr);

        $this->getJson('/api/dashboard/audit-report')->assertStatus(403);
    }

    // --- Listing Endpoints (Auditor Read-Only Bank-Wide) ------------------------

    public function test_auditor_can_list_all_customers_bank_wide(): void
    {
        Sanctum::actingAs($this->auditor);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        Customer::factory()->count(3)->create(['branch_id' => $this->branch->id]);
        Customer::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_auditor_can_list_all_transactions_bank_wide(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id]);
        Transaction::factory()->count(2)->create(['account_id' => $account->id, 'status' => 'flagged']);
        Transaction::factory()->count(3)->create(['account_id' => $account->id, 'status' => 'completed']);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_auditor_can_list_all_users_bank_wide(): void
    {
        Sanctum::actingAs($this->auditor);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        User::factory()->count(2)->create(['branch_id' => $this->branch->id]);
        User::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(4, $response->json('meta.total'));
    }

    public function test_auditor_can_view_transaction_details(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer    = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account     = Account::factory()->create(['customer_id' => $customer->id]);
        $transaction = Transaction::factory()->create(['account_id' => $account->id]);

        $this->getJson("/api/transactions/{$transaction->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $transaction->id);
    }

    // --- Read-Only Enforcement --------------------------------------------------

    public function test_auditor_cannot_create_transaction(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id, 'status' => 'active']);

        $this->postJson('/api/transactions', [
            'account_id'       => $account->id,
            'transaction_type' => 'deposit',
            'amount'           => 1000,
        ])->assertStatus(403);
    }

    public function test_auditor_cannot_create_customer(): void
    {
        Sanctum::actingAs($this->auditor);

        $this->postJson('/api/customers', [
            'full_name'     => 'Test Customer',
            'email'         => 'test@example.com',
            'phone'         => '555-0000',
            'customer_type' => 'individual',
        ])->assertStatus(403);
    }

    public function test_auditor_cannot_approve_transaction(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer    = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account     = Account::factory()->create(['customer_id' => $customer->id]);
        $transaction = Transaction::factory()->create(['account_id' => $account->id, 'status' => 'pending']);

        $this->postJson("/api/transactions/{$transaction->id}/approve")
            ->assertStatus(403);
    }

    public function test_auditor_cannot_resolve_alert(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $alert    = Alert::factory()->create([
            'alertable_type' => Customer::class,
            'alertable_id'   => $customer->id,
            'status'         => 'open',
        ]);

        $this->postJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(403);
    }

    public function test_auditor_cannot_delete_customer(): void
    {
        Sanctum::actingAs($this->auditor);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(403);
    }

    public function test_auditor_cannot_create_user(): void
    {
        Sanctum::actingAs($this->auditor);

        $this->postJson('/api/users', [
            'name'      => 'New User',
            'email'     => 'newuser@bank.com',
            'password'  => 'secret123',
            'role'      => 'csr',
            'branch_id' => $this->branch->id,
        ])->assertStatus(403);
    }
}
