<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $csr;
    private User $auditor;
    private User $manager;
    private Branch $branch;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch   = Branch::factory()->create(['status' => 'active']);
        $this->admin    = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr      = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor  = User::factory()->create(['role' => 'auditor', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager  = User::factory()->create(['role' => 'manager', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_accounts(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->count(3)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'account_number', 'account_type', 'balance', 'status', 'currency'],
                ],
            ]);
    }

    public function test_manager_only_sees_accounts_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

        $a1 = Account::factory()->create(['customer_id' => $this->customer->id]);
        $a2 = Account::factory()->create(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($a1->id, $response->json('data.0.id'));
    }

    public function test_accounts_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);
        Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'frozen']);

        $response = $this->getJson('/api/accounts?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    public function test_accounts_can_be_filtered_by_customer_id(): void
    {
        Sanctum::actingAs($this->csr);
        $otherCustomer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Account::factory()->count(2)->create(['customer_id' => $this->customer->id]);
        Account::factory()->count(3)->create(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson("/api/accounts?customer_id={$this->customer->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_accounts_can_be_searched_by_account_number(): void
    {
        Sanctum::actingAs($this->csr);
        $target = Account::factory()->create([
            'customer_id'    => $this->customer->id,
            'account_number' => 'ACC-2026-UNIQUE99',
        ]);
        Account::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts?search=UNIQUE99');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    public function test_accounts_can_be_searched_by_customer_name(): void
    {
        Sanctum::actingAs($this->csr);
        $namedCustomer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'full_name'  => 'Zelda Hyrule',
        ]);
        Account::factory()->create(['customer_id' => $namedCustomer->id]);
        Account::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts?search=Zelda');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($namedCustomer->id, $response->json('data.0.customer.id'));
    }

    public function test_accounts_can_be_filtered_by_branch_id(): void
    {
        Sanctum::actingAs($this->admin);
        $otherBranch    = Branch::factory()->create();
        $otherCustomer  = Customer::factory()->create(['branch_id' => $otherBranch->id]);
        Account::factory()->create(['customer_id' => $this->customer->id]);
        Account::factory()->create(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson("/api/accounts?branch_id={$this->branch->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_accounts_can_be_filtered_by_balance_range(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 100]);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 500]);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 2000]);

        $response = $this->getJson('/api/accounts?balance_min=200&balance_max=1000');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(500.0, $response->json('data.0.balance'));
    }

    public function test_accounts_can_be_sorted_by_balance(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 50]);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 9000]);
        Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 300]);

        // Ascending
        $resAsc = $this->getJson('/api/accounts?sort_by=balance&sort_direction=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals(50.0, $resAsc->json('data.0.balance'));

        // Descending
        $resDesc = $this->getJson('/api/accounts?sort_by=balance&sort_direction=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals(9000.0, $resDesc->json('data.0.balance'));
    }

    public function test_accounts_pagination_defaults_to_15_per_page(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->count(20)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_accounts_supports_custom_per_page_and_page(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->count(20)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts?per_page=6&page=2');

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(6, $response->json('meta.per_page'));
    }

    public function test_account_response_includes_customer_and_branch(): void
    {
        Sanctum::actingAs($this->csr);
        Account::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['*' => ['id', 'account_number', 'customer' => ['id', 'full_name']]],
        ]);
    }



    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_single_account(): void
    {
        Sanctum::actingAs($this->csr);
        $account = Account::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson("/api/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.account_number', $account->account_number)
            ->assertJsonStructure(['data' => ['id', 'account_number', 'account_type', 'balance', 'status', 'customer']]);
    }

    public function test_show_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->csr);
        $this->getJson('/api/accounts/999999')->assertStatus(404);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function test_csr_can_open_new_account(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->postJson('/api/accounts', [
            'customer_id'  => $this->customer->id,
            'account_type' => 'savings',
            'balance'      => 1000.00,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Account opened successfully.'])
            ->assertJsonPath('data.account_type', 'savings')
            ->assertJsonPath('data.status', 'active');

        $this->assertEquals(1000.00, (float) $response->json('data.balance'));
        $this->assertDatabaseHas('accounts', ['customer_id' => $this->customer->id]);
    }

    public function test_account_opening_rejects_negative_balance(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/accounts', [
            'customer_id'  => $this->customer->id,
            'account_type' => 'savings',
            'balance'      => -500, // invalid
        ])->assertStatus(422)->assertJsonValidationErrors(['balance']);
    }

    public function test_account_creation_requires_valid_type(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/accounts', [
            'customer_id'  => $this->customer->id,
            'account_type' => 'mortgage', // invalid for account type
        ])->assertStatus(422)->assertJsonValidationErrors(['account_type']);
    }

    public function test_account_creation_requires_valid_customer(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/accounts', [
            'customer_id'  => 99999, // does not exist
            'account_type' => 'savings',
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
    }

    public function test_auditor_cannot_create_account(): void
    {
        Sanctum::actingAs($this->auditor);

        $this->postJson('/api/accounts', [
            'customer_id'  => $this->customer->id,
            'account_type' => 'savings',
        ])->assertStatus(403);
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function test_manager_can_freeze_an_account(): void
    {
        Sanctum::actingAs($this->manager);
        $account = Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'status' => 'frozen',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'frozen');
    }

    public function test_csr_cannot_freeze_or_update_account(): void
    {
        Sanctum::actingAs($this->csr);
        $account = Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'status' => 'frozen',
        ]);

        $response->assertStatus(403);
    }

    public function test_account_update_rejects_invalid_status(): void
    {
        Sanctum::actingAs($this->manager);
        $account = Account::factory()->create(['customer_id' => $this->customer->id]);

        $this->putJson("/api/accounts/{$account->id}", [
            'status' => 'suspended', // invalid enum
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function test_manager_can_close_account(): void
    {
        Sanctum::actingAs($this->manager);
        $account = Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Account closed successfully.']);

        // Soft-close: status is set to 'closed', record still exists
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'status' => 'closed']);
    }

    public function test_csr_cannot_close_account(): void
    {
        Sanctum::actingAs($this->csr);
        $account = Account::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $response = $this->deleteJson("/api/accounts/{$account->id}");

        $response->assertStatus(403);
    }

    // ─── Transactions sub-resource ───────────────────────────────────────────────

    public function test_can_list_transactions_for_an_account(): void
    {
        Sanctum::actingAs($this->csr);
        $account = Account::factory()->create(['customer_id' => $this->customer->id]);
        Transaction::factory()->count(4)->create(['account_id' => $account->id]);

        $response = $this->getJson("/api/accounts/{$account->id}/transactions");

        $response->assertStatus(200);
        $this->assertCount(4, $response->json('data'));
    }

    public function test_account_transactions_can_be_filtered_by_type(): void
    {
        Sanctum::actingAs($this->csr);
        $account = Account::factory()->create(['customer_id' => $this->customer->id, 'balance' => 10000]);
        Transaction::factory()->create(['account_id' => $account->id, 'transaction_type' => 'deposit']);
        Transaction::factory()->create(['account_id' => $account->id, 'transaction_type' => 'withdrawal']);

        $response = $this->getJson("/api/accounts/{$account->id}/transactions?type=deposit");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('deposit', $response->json('data.0.transaction_type'));
    }

    public function test_csr_only_sees_accounts_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->csr);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        $customer1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $a1 = Account::factory()->create(['customer_id' => $customer1->id]);

        $customer2 = Customer::factory()->create(['branch_id' => $otherBranch->id]);
        $a2 = Account::factory()->create(['customer_id' => $customer2->id]);

        $response = $this->getJson('/api/accounts');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($a1->id, $ids);
        $this->assertNotContains($a2->id, $ids);
    }
}

