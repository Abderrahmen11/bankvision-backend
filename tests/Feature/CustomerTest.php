<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
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
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch  = Branch::factory()->create(['status' => 'active']);
        $this->admin   = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr     = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor = User::factory()->create(['role' => 'auditor', 'status' => 'active', 'branch_id' => $this->branch->id]);
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

    public function test_csr_can_update_customer_details(): void
    {
        Sanctum::actingAs($this->csr);
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

    public function test_customer_update_rejects_invalid_kyc_status(): void
    {
        Sanctum::actingAs($this->csr);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $this->putJson("/api/customers/{$customer->id}", [
            'kyc_status' => 'approved', // invalid enum value
        ])->assertStatus(422)->assertJsonValidationErrors(['kyc_status']);
    }

    public function test_customer_email_update_ignores_own_uniqueness(): void
    {
        Sanctum::actingAs($this->csr);
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
}
