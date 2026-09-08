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

class AuthorizationRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $compliance;
    private User $analyst;
    private User $csr;
    private User $auditor;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['status' => 'active']);

        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager    = User::factory()->create(['role' => 'manager',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->analyst    = User::factory()->create(['role' => 'analyst',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    public function test_only_admin_can_create_and_update_branches(): void
    {
        // CSR fails
        Sanctum::actingAs($this->csr);
        $this->postJson('/api/branches', [
            'branch_code' => 'BR999',
            'branch_name' => 'Test Branch',
        ])->assertStatus(403);

        // Manager fails
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/branches', [
            'branch_code' => 'BR999',
            'branch_name' => 'Test Branch',
        ])->assertStatus(403);

        // Admin succeeds
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/branches', [
            'branch_code' => 'BR999',
            'branch_name' => 'Admin Created Branch',
            'city'        => 'New York',
            'status'      => 'active',
        ])->assertStatus(201);
    }

    public function test_only_admin_can_delete_branches_and_customers(): void
    {
        $branchToDelete = Branch::factory()->create(['status' => 'inactive']);
        $customerToDelete = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Manager cannot delete branch
        Sanctum::actingAs($this->manager);
        $this->deleteJson("/api/branches/{$branchToDelete->id}")->assertStatus(403);

        // CSR cannot delete customer
        Sanctum::actingAs($this->csr);
        $this->deleteJson("/api/customers/{$customerToDelete->id}")->assertStatus(403);

        // Admin can delete both
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/branches/{$branchToDelete->id}")->assertStatus(200);
        $this->deleteJson("/api/customers/{$customerToDelete->id}")->assertStatus(200);
    }

    public function test_transaction_approval_permissions(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account  = Account::factory()->create(['customer_id' => $customer->id]);
        $flaggedTxn = Transaction::factory()->create([
            'account_id' => $account->id,
            'status'     => 'flagged',
            'amount'     => 500,
        ]);

        // CSR cannot approve
        Sanctum::actingAs($this->csr);
        $this->postJson("/api/transactions/{$flaggedTxn->id}/approve")->assertStatus(403);

        // Analyst cannot approve
        Sanctum::actingAs($this->analyst);
        $this->postJson("/api/transactions/{$flaggedTxn->id}/approve")->assertStatus(403);

        // Auditor cannot approve
        Sanctum::actingAs($this->auditor);
        $this->postJson("/api/transactions/{$flaggedTxn->id}/approve")->assertStatus(403);

        // Compliance cannot approve transactions (per role matrix)
        Sanctum::actingAs($this->compliance);
        $this->postJson("/api/transactions/{$flaggedTxn->id}/approve")->assertStatus(403);

        // Manager can approve in their branch
        Sanctum::actingAs($this->manager);
        $this->postJson("/api/transactions/{$flaggedTxn->id}/approve")->assertStatus(200);
    }

    public function test_loan_approval_permissions(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $pendingLoan = Loan::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'pending',
        ]);

        // CSR cannot approve loan
        Sanctum::actingAs($this->csr);
        $this->postJson("/api/loans/{$pendingLoan->id}/approve")->assertStatus(403);

        // Compliance cannot approve loan (manager/admin only)
        Sanctum::actingAs($this->compliance);
        $this->postJson("/api/loans/{$pendingLoan->id}/approve")->assertStatus(403);

        // Manager can approve loan in their branch
        Sanctum::actingAs($this->manager);
        $this->postJson("/api/loans/{$pendingLoan->id}/approve")->assertStatus(200);
    }

    public function test_alert_resolution_permissions(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $alert = Alert::factory()->create([
            'alertable_type' => Customer::class,
            'alertable_id'   => $customer->id,
            'status'         => 'open',
        ]);

        // CSR cannot resolve alert
        Sanctum::actingAs($this->csr);
        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(403);

        // Analyst cannot resolve alert
        Sanctum::actingAs($this->analyst);
        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(403);

        // Compliance can resolve alert
        Sanctum::actingAs($this->compliance);
        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(200);
    }

    public function test_csr_restricted_endpoints(): void
    {
        Sanctum::actingAs($this->csr);

        // CSR cannot view staff list
        $this->getJson('/api/users')->assertStatus(403);

        // CSR cannot view performance reports
        $this->getJson('/api/reports')->assertStatus(403);
        $this->getJson('/api/dashboard/reports')->assertStatus(403);

        // CSR cannot view audit logs
        $this->getJson('/api/audit-logs')->assertStatus(403);

        // CSR cannot create loans
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->postJson('/api/loans', [
            'customer_id'      => $customer->id,
            'loan_type'        => 'personal',
            'principal_amount' => 5000,
            'interest_rate'    => 5.0,
            'term_months'      => 12,
            'start_date'       => now()->toDateString(),
        ])->assertStatus(403);

        // CSR cannot close accounts
        $account = Account::factory()->create(['customer_id' => $customer->id]);
        $this->deleteJson("/api/accounts/{$account->id}")->assertStatus(403);
    }

    public function test_branch_manager_scoping(): void
    {
        Sanctum::actingAs($this->manager);

        // Manager can view audit logs for their branch
        $this->getJson('/api/audit-logs')->assertStatus(200);

        // Manager cannot access data from another branch
        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount  = Account::factory()->create(['customer_id' => $otherCustomer->id]);

        $this->getJson("/api/branches/{$otherBranch->id}")->assertStatus(403);
        $this->getJson("/api/customers/{$otherCustomer->id}")->assertStatus(403);
        $this->getJson("/api/accounts/{$otherAccount->id}")->assertStatus(403);
    }

    public function test_all_authenticated_roles_can_read_dashboard_stats(): void
    {
        $roles = [$this->admin, $this->manager, $this->compliance, $this->analyst, $this->csr, $this->auditor];

        foreach ($roles as $user) {
            Sanctum::actingAs($user);
            $response = $this->getJson('/api/dashboard/stats');
            $response->assertStatus(200);
        }
    }
}
