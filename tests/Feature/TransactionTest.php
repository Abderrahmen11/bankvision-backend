<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compliance;
    private User $csr;
    private User $auditor;
    private Branch $branch;
    private Customer $customer;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch     = Branch::factory()->create(['status' => 'active']);
        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->customer   = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->account    = Account::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'active',
            'balance'     => 5000.00,
        ]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_transactions(): void
    {
        Sanctum::actingAs($this->csr);
        Transaction::factory()->count(4)->create(['account_id' => $this->account->id]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'transaction_number', 'transaction_type', 'amount', 'status'],
                ],
            ]);
    }

    public function test_transactions_can_be_filtered_by_account_id(): void
    {
        Sanctum::actingAs($this->csr);
        $otherAccount = Account::factory()->create(['customer_id' => $this->customer->id]);
        Transaction::factory()->count(2)->create(['account_id' => $this->account->id]);
        Transaction::factory()->count(3)->create(['account_id' => $otherAccount->id]);

        $response = $this->getJson("/api/transactions?account_id={$this->account->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_transactions_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->csr);
        Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed']);
        Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'flagged']);

        $response = $this->getJson('/api/transactions?status=flagged');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('flagged', $response->json('data.0.status'));
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_can_view_single_transaction(): void
    {
        Sanctum::actingAs($this->csr);
        $transaction = Transaction::factory()->create(['account_id' => $this->account->id]);

        $response = $this->getJson("/api/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.transaction_number', $transaction->transaction_number);
    }

    // ─── Store (Deposit/Withdrawal) ──────────────────────────────────────────────

    public function test_deposit_increases_account_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $initialBalance = (float) $this->account->balance;

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'amount'           => 1500.00,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.transaction_type', 'deposit')
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals($initialBalance + 1500.00, (float) $this->account->fresh()->balance);
    }

    public function test_withdrawal_decreases_account_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $initialBalance = (float) $this->account->balance;

        $response = $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'withdrawal',
            'amount'           => 1000.00,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals($initialBalance - 1000.00, (float) $this->account->fresh()->balance);
    }

    public function test_withdrawal_is_rejected_when_insufficient_funds(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'withdrawal',
            'amount'           => 99999.00, // more than balance
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient funds. Account balance cannot be negative.');
    }

    public function test_transaction_on_frozen_account_is_rejected(): void
    {
        Sanctum::actingAs($this->csr);
        $this->account->update(['status' => 'frozen']);

        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'amount'           => 500.00,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Cannot process transactions on frozen account.');
    }

    public function test_transaction_on_closed_account_is_rejected(): void
    {
        Sanctum::actingAs($this->csr);
        $this->account->update(['status' => 'closed']);

        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'amount'           => 200.00,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Cannot process transactions on closed account.');
    }

    public function test_transaction_store_rejects_invalid_payload(): void
    {
        Sanctum::actingAs($this->csr);

        // Missing required fields
        $this->postJson('/api/transactions', [])->assertStatus(422)
            ->assertJsonValidationErrors(['account_id', 'transaction_type', 'amount']);

        // Invalid transaction type
        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'crypto', // invalid
            'amount'           => 100,
        ])->assertStatus(422)->assertJsonValidationErrors(['transaction_type']);

        // Zero amount
        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'amount'           => 0, // invalid: min:0.01
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_pending_transaction_does_not_affect_balance_immediately(): void
    {
        Sanctum::actingAs($this->csr);
        $initialBalance = (float) $this->account->balance;

        $this->postJson('/api/transactions', [
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'amount'           => 500.00,
            'status'           => 'pending',
        ])->assertStatus(201)->assertJsonPath('data.status', 'pending');

        // Balance should NOT change for pending
        $this->assertEquals($initialBalance, (float) $this->account->fresh()->balance);
    }

    // ─── Approve ─────────────────────────────────────────────────────────────────

    public function test_compliance_can_approve_flagged_transaction(): void
    {
        Sanctum::actingAs($this->compliance);
        $txn = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'status'     => 'flagged',
            'amount'     => 200.00,
        ]);

        $response = $this->postJson("/api/transactions/{$txn->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Transaction approved successfully.'])
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($txn->fresh()->approved_by);
        $this->assertNotNull($txn->fresh()->approved_at);
    }

    public function test_approving_pending_deposit_adds_to_balance(): void
    {
        Sanctum::actingAs($this->compliance);
        $initialBalance = (float) $this->account->balance;

        $txn = Transaction::factory()->create([
            'account_id'       => $this->account->id,
            'status'           => 'pending',
            'transaction_type' => 'deposit',
            'amount'           => 800.00,
        ]);

        $this->postJson("/api/transactions/{$txn->id}/approve")->assertStatus(200);

        $this->assertEquals($initialBalance + 800.00, (float) $this->account->fresh()->balance);
    }

    public function test_approve_returns_404_for_already_completed_transaction(): void
    {
        Sanctum::actingAs($this->compliance);
        $txn = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'status'     => 'completed',
        ]);

        $this->postJson("/api/transactions/{$txn->id}/approve")->assertStatus(404);
    }

    public function test_csr_cannot_approve_transactions(): void
    {
        Sanctum::actingAs($this->csr);
        $txn = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'flagged']);

        $this->postJson("/api/transactions/{$txn->id}/approve")->assertStatus(403);
    }

    // ─── Flag ────────────────────────────────────────────────────────────────────

    public function test_compliance_can_flag_a_completed_transaction(): void
    {
        Sanctum::actingAs($this->compliance);
        $txn = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'status'     => 'completed',
        ]);

        $response = $this->postJson("/api/transactions/{$txn->id}/flag");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Transaction flagged for review.'])
            ->assertJsonPath('data.status', 'flagged');

        // Flagging should auto-create a compliance alert
        $this->assertDatabaseHas('alerts', [
            'alertable_type' => Transaction::class,
            'alertable_id'   => $txn->id,
            'severity'       => 'high',
            'status'         => 'open',
        ]);
    }

    public function test_flagging_already_flagged_transaction_does_not_duplicate_alert(): void
    {
        Sanctum::actingAs($this->compliance);
        $txn = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed']);

        $this->postJson("/api/transactions/{$txn->id}/flag");
        $this->postJson("/api/transactions/{$txn->id}/flag"); // second flag on already-flagged

        $alertCount = Alert::where('alertable_type', Transaction::class)
            ->where('alertable_id', $txn->id)
            ->count();

        $this->assertEquals(1, $alertCount);
    }

    public function test_auditor_cannot_flag_transactions(): void
    {
        Sanctum::actingAs($this->auditor);
        $txn = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed']);

        $this->postJson("/api/transactions/{$txn->id}/flag")->assertStatus(403);
    }
}
