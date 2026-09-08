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
    private User $manager;
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
        $this->manager    = User::factory()->create(['role' => 'manager',    'status' => 'active', 'branch_id' => $this->branch->id]);
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

    public function test_compliance_officer_only_sees_compliance_and_suspicious_transactions(): void
    {
        Sanctum::actingAs($this->compliance);

        // Compliance-relevant transactions
        $t1 = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'flagged', 'amount' => 100]);
        $t2 = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed', 'amount' => 15000]);
        $t3 = Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed', 'transaction_type' => 'wire', 'amount' => 200]);

        // Regular transaction (not flagged, <10000, not wire, no alerts)
        Transaction::factory()->create(['account_id' => $this->account->id, 'status' => 'completed', 'transaction_type' => 'deposit', 'amount' => 500]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($t1->id, $ids);
        $this->assertContains($t2->id, $ids);
        $this->assertContains($t3->id, $ids);
    }

    public function test_manager_only_sees_transactions_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount  = Account::factory()->create(['customer_id' => $otherCustomer->id]);

        $t1 = Transaction::factory()->create(['account_id' => $this->account->id]);
        $t2 = Transaction::factory()->create(['account_id' => $otherAccount->id]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($t1->id, $response->json('data.0.id'));
    }

    public function test_csr_only_sees_transactions_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->csr);

        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount  = Account::factory()->create(['customer_id' => $otherCustomer->id]);

        $t1 = Transaction::factory()->create(['account_id' => $this->account->id]);
        $t2 = Transaction::factory()->create(['account_id' => $otherAccount->id]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($t1->id, $response->json('data.0.id'));
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

    public function test_transactions_can_be_searched_by_transaction_number(): void
    {
        Sanctum::actingAs($this->csr);
        $target = Transaction::factory()->create([
            'account_id'         => $this->account->id,
            'transaction_number' => 'TXN-2026-SEARCH123',
        ]);
        Transaction::factory()->create(['account_id' => $this->account->id]);

        $response = $this->getJson('/api/transactions?search=SEARCH123');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    public function test_transactions_can_be_searched_by_counterparty(): void
    {
        Sanctum::actingAs($this->csr);
        $target = Transaction::factory()->create([
            'account_id'   => $this->account->id,
            'counterparty' => 'Acme Corporation International',
        ]);
        Transaction::factory()->create(['account_id' => $this->account->id, 'counterparty' => 'Retail Store']);

        $response = $this->getJson('/api/transactions?search=Acme');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    public function test_transactions_can_be_filtered_by_type_channel_and_approved_by(): void
    {
        Sanctum::actingAs($this->admin);
        $t1 = Transaction::factory()->create([
            'account_id'       => $this->account->id,
            'transaction_type' => 'wire',
            'channel'          => 'wire',
            'approved_by'      => $this->compliance->id,
        ]);
        Transaction::factory()->create([
            'account_id'       => $this->account->id,
            'transaction_type' => 'deposit',
            'channel'          => 'branch',
            'approved_by'      => null,
        ]);

        $response = $this->getJson("/api/transactions?transaction_type=wire&channel=wire&approved_by={$this->compliance->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($t1->id, $response->json('data.0.id'));
    }

    public function test_transactions_can_be_sorted_by_amount_and_approved_at(): void
    {
        Sanctum::actingAs($this->csr);
        Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 50.00]);
        Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 5000.00]);
        Transaction::factory()->create(['account_id' => $this->account->id, 'amount' => 250.00]);

        // Ascending by amount
        $resAsc = $this->getJson('/api/transactions?sort_by=amount&sort_direction=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals(50.0, $resAsc->json('data.0.amount'));

        // Descending by amount
        $resDesc = $this->getJson('/api/transactions?sort_by=amount&sort_direction=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals(5000.0, $resDesc->json('data.0.amount'));
    }

    public function test_transactions_pagination_defaults_to_15_per_page(): void
    {
        Sanctum::actingAs($this->csr);
        Transaction::factory()->count(20)->create(['account_id' => $this->account->id]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_transactions_supports_custom_per_page_and_page(): void
    {
        Sanctum::actingAs($this->csr);
        Transaction::factory()->count(20)->create(['account_id' => $this->account->id]);

        $response = $this->getJson('/api/transactions?per_page=7&page=2');

        $response->assertStatus(200);
        $this->assertCount(7, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(7, $response->json('meta.per_page'));
    }

    public function test_transaction_response_includes_account_and_approver(): void
    {
        Sanctum::actingAs($this->csr);
        $txn = Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'approved_by' => $this->compliance->id,
        ]);

        $response = $this->getJson('/api/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'transaction_number',
                    'account' => ['id', 'account_number', 'customer'],
                    'approver',
                ],
            ],
        ]);
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

    public function test_manager_can_approve_flagged_transaction(): void
    {
        Sanctum::actingAs($this->manager);
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

    public function test_compliance_cannot_approve_transaction(): void
    {
        Sanctum::actingAs($this->compliance);
        $txn = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'status'     => 'flagged',
            'amount'     => 200.00,
        ]);

        $this->postJson("/api/transactions/{$txn->id}/approve")->assertStatus(403);
    }

    public function test_approving_pending_deposit_adds_to_balance(): void
    {
        Sanctum::actingAs($this->manager);
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
        Sanctum::actingAs($this->manager);
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
