<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $csr;
    private User $auditor;
    private User $compliance;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch     = Branch::factory()->create(['status' => 'active']);
        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager    = User::factory()->create(['role' => 'manager',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // ─── Index ──────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_customers(): void
    {
        Sanctum::actingAs($this->csr);
        Customer::factory()->count(5)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'full_name', 'email', 'customer_number', 'customer_type', 'kyc_status'],
                ],
                'links', 'meta',
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_compliance_officer_can_view_all_customers(): void
    {
        Sanctum::actingAs($this->compliance);

        $otherBranch = Branch::factory()->create(['status' => 'active']);

        // Customers in primary branch and other branch
        $c1 = Customer::factory()->create(['branch_id' => $this->branch->id, 'kyc_status' => 'pending', 'risk_level' => 'low']);
        $c2 = Customer::factory()->create(['branch_id' => $this->branch->id, 'kyc_status' => 'verified', 'risk_level' => 'high']);
        $c3 = Customer::factory()->create(['branch_id' => $otherBranch->id, 'kyc_status' => 'verified', 'risk_level' => 'low']);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($c1->id, $ids);
        $this->assertContains($c2->id, $ids);
        $this->assertContains($c3->id, $ids);
    }

    public function test_manager_only_sees_customers_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        $c1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $c2 = Customer::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($c1->id, $response->json('data.0.id'));
    }

    public function test_customers_can_be_filtered_by_search(): void
    {
        Sanctum::actingAs($this->csr);
        Customer::factory()->create(['branch_id' => $this->branch->id, 'full_name' => 'Alice Wonderland']);
        Customer::factory()->create(['branch_id' => $this->branch->id, 'full_name' => 'Bob Smith']);

        $response = $this->getJson('/api/customers?search=Alice');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alice Wonderland', $response->json('data.0.full_name'));
    }

    public function test_customers_can_be_filtered_by_type_and_kyc_status(): void
    {
        Sanctum::actingAs($this->csr);
        Customer::factory()->create(['branch_id' => $this->branch->id, 'customer_type' => 'premium', 'kyc_status' => 'verified']);
        Customer::factory()->create(['branch_id' => $this->branch->id, 'customer_type' => 'regular', 'kyc_status' => 'pending']);

        $response = $this->getJson('/api/customers?type=premium&kyc_status=verified');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('premium', $response->json('data.0.customer_type'));
    }

    public function test_customers_can_be_filtered_by_branch_id(): void
    {
        Sanctum::actingAs($this->admin);
        $otherBranch = Branch::factory()->create();
        Customer::factory()->create(['branch_id' => $this->branch->id, 'full_name' => 'Branch One Cust']);
        Customer::factory()->create(['branch_id' => $otherBranch->id, 'full_name' => 'Branch Two Cust']);

        $response = $this->getJson("/api/customers?branch_id={$this->branch->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Branch One Cust', $response->json('data.0.full_name'));
    }

    public function test_customers_can_be_searched_by_phone_and_customer_number(): void
    {
        Sanctum::actingAs($this->csr);
        $cust = Customer::factory()->create([
            'branch_id'       => $this->branch->id,
            'phone'           => '+1-999-888-7777',
            'customer_number' => 'CUST-2026-99999',
        ]);
        Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Search by phone
        $resPhone = $this->getJson('/api/customers?search=888-7777');
        $resPhone->assertStatus(200);
        $this->assertCount(1, $resPhone->json('data'));
        $this->assertEquals($cust->id, $resPhone->json('data.0.id'));

        // Search by customer number
        $resNum = $this->getJson('/api/customers?search=CUST-2026-99999');
        $resNum->assertStatus(200);
        $this->assertCount(1, $resNum->json('data'));
        $this->assertEquals($cust->id, $resNum->json('data.0.id'));
    }

    public function test_customers_can_be_sorted_by_registration_date(): void
    {
        Sanctum::actingAs($this->csr);
        $custOld = Customer::factory()->create([
            'branch_id'         => $this->branch->id,
            'full_name'         => 'Old Customer',
            'registration_date' => '2025-01-01',
        ]);
        $custNew = Customer::factory()->create([
            'branch_id'         => $this->branch->id,
            'full_name'         => 'New Customer',
            'registration_date' => '2026-06-01',
        ]);

        // Ascending
        $resAsc = $this->getJson('/api/customers?sort_by=registration_date&sort_direction=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals('Old Customer', $resAsc->json('data.0.full_name'));

        // Descending
        $resDesc = $this->getJson('/api/customers?sort_by=registration_date&sort_direction=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals('New Customer', $resDesc->json('data.0.full_name'));
    }

    public function test_customers_pagination_and_custom_per_page(): void
    {
        Sanctum::actingAs($this->csr);
        Customer::factory()->count(20)->create(['branch_id' => $this->branch->id]);

        // Default 15 per page
        $resDefault = $this->getJson('/api/customers');
        $resDefault->assertStatus(200);
        $this->assertCount(15, $resDefault->json('data'));
        $this->assertEquals(15, $resDefault->json('meta.per_page'));
        $this->assertEquals(20, $resDefault->json('meta.total'));

        // Custom per_page = 5
        $resCustom = $this->getJson('/api/customers?per_page=5&page=2');
        $resCustom->assertStatus(200);
        $this->assertCount(5, $resCustom->json('data'));
        $this->assertEquals(2, $resCustom->json('meta.current_page'));
        $this->assertEquals(5, $resCustom->json('meta.per_page'));
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_single_customer(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.full_name', $customer->full_name)
            ->assertJsonStructure([
                'data' => ['id', 'full_name', 'email', 'phone', 'branch', 'accounts_count', 'loans_count'],
            ]);
    }

    public function test_show_returns_404_for_non_existent_customer(): void
    {
        Sanctum::actingAs($this->csr);
        $this->getJson('/api/customers/999999')->assertStatus(404);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function test_csr_can_create_a_customer(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->postJson('/api/customers', [
            'full_name'     => 'Jane Doe',
            'email'         => 'jane.doe@example.com',
            'phone'         => '+1-555-0100',
            'customer_type' => 'regular',
            'branch_id'     => $this->branch->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Customer created successfully.'])
            ->assertJsonPath('data.full_name', 'Jane Doe')
            ->assertJsonPath('data.kyc_status', 'pending'); // default

        $this->assertDatabaseHas('customers', ['email' => 'jane.doe@example.com']);
    }

    public function test_customer_email_must_be_unique(): void
    {
        Sanctum::actingAs($this->csr);
        Customer::factory()->create(['branch_id' => $this->branch->id, 'email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/customers', [
            'full_name'     => 'Duplicate',
            'email'         => 'duplicate@example.com',
            'phone'         => '+1-555-0101',
            'customer_type' => 'regular',
            'branch_id'     => $this->branch->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_creation_requires_valid_fields(): void
    {
        Sanctum::actingAs($this->csr);

        // Missing required fields
        $this->postJson('/api/customers', [])->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'email', 'phone', 'customer_type', 'branch_id']);

        // Invalid customer_type enum
        $this->postJson('/api/customers', [
            'full_name'     => 'Test User',
            'email'         => 'test@example.com',
            'phone'         => '555-0000',
            'customer_type' => 'vip', // invalid
            'branch_id'     => $this->branch->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['customer_type']);

        // Invalid branch_id FK
        $this->postJson('/api/customers', [
            'full_name'     => 'Test User',
            'email'         => 'test@example.com',
            'phone'         => '555-0000',
            'customer_type' => 'regular',
            'branch_id'     => 99999,
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_auditor_cannot_create_customer(): void
    {
        Sanctum::actingAs($this->auditor);

        $this->postJson('/api/customers', [
            'full_name'     => 'Unauthorized',
            'email'         => 'unauth@example.com',
            'phone'         => '555-0000',
            'customer_type' => 'regular',
            'branch_id'     => $this->branch->id,
        ])->assertStatus(403);
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function test_csr_can_update_customer_contact_details(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'phone'   => '555-9999',
            'address' => '456 New St',
            'city'    => 'New City',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Customer updated successfully.'])
            ->assertJsonPath('data.phone', '555-9999')
            ->assertJsonPath('data.city', 'New City');
    }

    public function test_csr_cannot_update_kyc_or_risk_level(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->putJson("/api/customers/{$customer->id}", [
            'kyc_status' => 'verified',
        ])->assertStatus(403);
    }

    public function test_manager_can_update_customer_details_and_kyc(): void
    {
        Sanctum::actingAs($this->manager);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'full_name'  => 'Updated Name',
            'kyc_status' => 'verified',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Customer updated successfully.'])
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.kyc_status', 'verified');
    }

    public function test_compliance_can_update_kyc_status_only(): void
    {
        Sanctum::actingAs($this->compliance);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        // Compliance updating KYC passes
        $response = $this->putJson("/api/customers/{$customer->id}", [
            'kyc_status' => 'verified',
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.kyc_status', 'verified');

        // Compliance attempting to update personal details gets 403
        $this->putJson("/api/customers/{$customer->id}", [
            'full_name' => 'Illegal Change',
        ])->assertStatus(403);
    }

    public function test_customer_update_rejects_invalid_kyc_status(): void
    {
        Sanctum::actingAs($this->manager);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->putJson("/api/customers/{$customer->id}", [
            'kyc_status' => 'approved', // invalid enum value
        ])->assertStatus(422)->assertJsonValidationErrors(['kyc_status']);
    }

    public function test_customer_email_update_ignores_own_uniqueness(): void
    {
        Sanctum::actingAs($this->manager);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'email' => 'same@example.com']);

        // Updating with the same email should pass
        $this->putJson("/api/customers/{$customer->id}", [
            'email' => 'same@example.com',
        ])->assertStatus(200);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_customer_with_no_funds_or_loans(): void
    {
        Sanctum::actingAs($this->admin);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Customer deleted successfully.']);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_customer_with_active_funded_accounts(): void
    {
        Sanctum::actingAs($this->admin);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Account::factory()->create(['customer_id' => $customer->id, 'status' => 'active', 'balance' => 1000]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a customer with active accounts holding funds or outstanding loans.');
    }

    public function test_cannot_delete_customer_with_outstanding_loans(): void
    {
        Sanctum::actingAs($this->admin);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Loan::factory()->create(['customer_id' => $customer->id, 'status' => 'active', 'outstanding_balance' => 5000]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a customer with active accounts holding funds or outstanding loans.');
    }

    public function test_csr_cannot_delete_customer(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/customers/{$customer->id}")->assertStatus(403);
    }

    // ─── Sub-resources ───────────────────────────────────────────────────────────

    public function test_can_list_accounts_for_a_customer(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Account::factory()->count(3)->create(['customer_id' => $customer->id]);

        $response = $this->getJson("/api/customers/{$customer->id}/accounts");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_list_loans_for_a_customer(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Loan::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = $this->getJson("/api/customers/{$customer->id}/loans");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_csr_only_sees_customers_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->csr);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        $c1 = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $c2 = Customer::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($c1->id, $ids);
        $this->assertNotContains($c2->id, $ids);
    }

    public function test_can_list_transactions_for_a_customer(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $account = Account::factory()->create(['customer_id' => $customer->id, 'balance' => 5000]);
        Transaction::factory()->count(3)->create(['account_id' => $account->id]);

        $response = $this->getJson("/api/customers/{$customer->id}/transactions");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_customer_includes_total_balance(): void
    {
        Sanctum::actingAs($this->admin);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        Account::factory()->create(['customer_id' => $customer->id, 'balance' => 1500]);
        Account::factory()->create(['customer_id' => $customer->id, 'balance' => 2500]);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_balance', 4000);
    }
}

