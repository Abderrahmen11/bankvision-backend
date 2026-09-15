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

class ComprehensiveBackendAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $csr;
    private User $compliance;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'branch_name' => 'HQ Main Branch',
            'status'      => 'active',
        ]);

        $this->admin = User::factory()->create([
            'role'      => 'admin',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
            'password'  => bcrypt('password123'),
        ]);

        $this->manager = User::factory()->create([
            'role'      => 'manager',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);

        $this->csr = User::factory()->create([
            'role'      => 'csr',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);

        $this->compliance = User::factory()->create([
            'role'      => 'compliance',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Authentication & Sanctum
    |--------------------------------------------------------------------------
    */
    public function test_user_can_login_and_receive_sanctum_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => $this->admin->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $suspendedUser = User::factory()->create([
            'status'   => 'suspended',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => $suspendedUser->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_access_profile_and_logout(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('user.email', $this->admin->email);

        $this->postJson('/api/logout')
            ->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Permissions & Middleware
    |--------------------------------------------------------------------------
    */
    public function test_csr_cannot_delete_customer(): void
    {
        Sanctum::actingAs($this->csr);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_delete_unfunded_customer(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Accounts & Balance Integrity
    |--------------------------------------------------------------------------
    */
    public function test_account_creation_enforces_non_negative_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Attempt negative balance
        $response = $this->postJson('/api/accounts', [
            'customer_id'  => $customer->id,
            'account_type' => 'savings',
            'balance'      => -500,
        ]);

        $response->assertStatus(422);

        // Valid positive balance
        $validResponse = $this->postJson('/api/accounts', [
            'customer_id'  => $customer->id,
            'account_type' => 'checking',
            'balance'      => 1000.50,
        ]);

        $validResponse->assertStatus(201)
            ->assertJsonPath('data.balance', '1000.50');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Transactions & Atomicity (Deposit, Withdrawal, Negative Balance Protection)
    |--------------------------------------------------------------------------
    */
    public function test_deposit_increases_account_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $account = Account::factory()->create([
            'balance' => 500.00,
            'status'  => 'active',
        ]);

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $account->id,
            'transaction_type' => 'deposit',
            'amount'           => 250.00,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(750.00, (float) $account->fresh()->balance);
    }

    public function test_withdrawal_decreases_account_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $account = Account::factory()->create([
            'balance' => 1000.00,
            'status'  => 'active',
        ]);

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $account->id,
            'transaction_type' => 'withdrawal',
            'amount'           => 300.00,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(700.00, (float) $account->fresh()->balance);
    }

    public function test_overdraw_is_strictly_prevented(): void
    {
        Sanctum::actingAs($this->csr);

        $account = Account::factory()->create([
            'balance' => 100.00,
            'status'  => 'active',
        ]);

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $account->id,
            'transaction_type' => 'withdrawal',
            'amount'           => 500.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient funds. Account balance cannot be negative.');

        $this->assertEquals(100.00, (float) $account->fresh()->balance);
    }

    public function test_transaction_on_frozen_account_is_rejected(): void
    {
        Sanctum::actingAs($this->csr);

        $account = Account::factory()->create([
            'balance' => 1000.00,
            'status'  => 'frozen',
        ]);

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $account->id,
            'transaction_type' => 'deposit',
            'amount'           => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot process transactions on frozen account.');
    }

    public function test_flagging_transaction_creates_compliance_alert(): void
    {
        Sanctum::actingAs($this->compliance);

        $transaction = Transaction::factory()->create([
            'status' => 'completed',
        ]);

        $response = $this->postJson("/api/transactions/{$transaction->id}/flag");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'flagged');

        $this->assertDatabaseHas('alerts', [
            'alertable_type' => Transaction::class,
            'alertable_id'   => $transaction->id,
            'alert_type'     => 'suspicious_transaction',
            'severity'       => 'high',
            'status'         => 'open',
        ]);
    }

    public function test_approving_flagged_transaction_completes_it(): void
    {
        Sanctum::actingAs($this->manager);

        $transaction = Transaction::factory()->create([
            'status' => 'flagged',
        ]);

        $response = $this->postJson("/api/transactions/{$transaction->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals('completed', $transaction->fresh()->status);
        $this->assertEquals($this->manager->id, $transaction->fresh()->approved_by);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Loans & Lifecycle Transitions
    |--------------------------------------------------------------------------
    */
    public function test_loan_lifecycle_transitions_and_completion(): void
    {
        Sanctum::actingAs($this->manager);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // 1. Submit loan
        $createResponse = $this->postJson('/api/loans', [
            'customer_id'      => $customer->id,
            'loan_type'        => 'personal',
            'principal_amount' => 10000,
            'interest_rate'    => 5.5,
            'term_months'      => 24,
            'start_date'       => '2026-01-01',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $loanId = $createResponse->json('data.id');

        // 2. Approve loan (pending -> active)
        $this->postJson("/api/loans/{$loanId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        // 3. Transition to delinquent (active -> delinquent)
        $this->putJson("/api/loans/{$loanId}", [
            'status' => 'delinquent',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'delinquent');

        // 4. Transition to defaulted (delinquent -> defaulted)
        $this->putJson("/api/loans/{$loanId}", [
            'status' => 'defaulted',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'defaulted');

        // 5. Pay off loan to 0 balance -> auto completed
        $this->putJson("/api/loans/{$loanId}", [
            'outstanding_balance' => 0,
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'completed');

        // 6. Prohibit invalid mutation on completed loan
        $this->putJson("/api/loans/{$loanId}", [
            'status' => 'active',
        ])->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Alert Assignment & Resolution
    |--------------------------------------------------------------------------
    */
    public function test_alert_assignment_and_resolution(): void
    {
        Sanctum::actingAs($this->compliance);

        $alert = Alert::factory()->create([
            'status'      => 'open',
            'assigned_to' => null,
        ]);

        // Assign to compliance user -> status becomes in-progress
        $this->postJson("/api/alerts/{$alert->id}/assign", [
            'user_id' => $this->compliance->id,
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'in-progress');

        // Resolve in-progress alert
        $this->postJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Security & OWASP Hardening
    |--------------------------------------------------------------------------
    */
    public function test_security_headers_are_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/branches');

        // Unauthenticated or not, security headers middleware attaches them to API responses
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_sensitive_user_data_is_never_exposed_in_api(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('user'));
    }

    public function test_sql_injection_payload_in_search_is_safely_handled(): void
    {
        Sanctum::actingAs($this->csr);

        $payload = "' OR '1'='1' -- ";
        $response = $this->getJson("/api/customers?search=" . urlencode($payload));

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        // Execute 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => 'attacker@bankvision.com',
                'password' => 'wrongpass',
            ]);
        }

        // 6th attempt should be throttled (HTTP 429 Too Many Requests)
        $rateLimitedResponse = $this->postJson('/api/login', [
            'email'    => 'attacker@bankvision.com',
            'password' => 'wrongpass',
        ]);

        $rateLimitedResponse->assertStatus(429);
    }
}
