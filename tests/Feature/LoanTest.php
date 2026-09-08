<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $csr;
    private User $auditor;
    private Branch $branch;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch   = Branch::factory()->create(['status' => 'active']);
        $this->admin    = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager  = User::factory()->create(['role' => 'manager', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr      = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor  = User::factory()->create(['role' => 'auditor', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_loans(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->count(3)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/loans');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'loan_number', 'loan_type', 'principal_amount', 'status', 'outstanding_balance'],
                ],
            ]);
    }

    public function test_manager_only_sees_loans_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

        $l1 = Loan::factory()->create(['customer_id' => $this->customer->id]);
        $l2 = Loan::factory()->create(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson('/api/loans');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($l1->id, $response->json('data.0.id'));
    }

    public function test_csr_only_sees_loans_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->csr);

        $otherBranch   = Branch::factory()->create(['status' => 'active']);
        $otherCustomer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

        $l1 = Loan::factory()->create(['customer_id' => $this->customer->id]);
        $l2 = Loan::factory()->create(['customer_id' => $otherCustomer->id]);

        $response = $this->getJson('/api/loans');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($l1->id, $response->json('data.0.id'));
    }

    public function test_loans_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);
        Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $response = $this->getJson('/api/loans?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_loans_can_be_searched_by_loan_number(): void
    {
        Sanctum::actingAs($this->csr);
        $target = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'loan_number' => 'LN-2026-SEARCH999',
        ]);
        Loan::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/loans?search=SEARCH999');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    public function test_loans_can_be_searched_by_customer_name_and_number(): void
    {
        Sanctum::actingAs($this->csr);
        $customCustomer = Customer::factory()->create([
            'branch_id'       => $this->branch->id,
            'full_name'       => 'Alexander Hamilton',
            'customer_number' => 'CUST-2026-77777',
        ]);
        $target = Loan::factory()->create(['customer_id' => $customCustomer->id]);
        Loan::factory()->create(['customer_id' => $this->customer->id]);

        // Search by customer name
        $resName = $this->getJson('/api/customers?search=Hamilton');
        $resName->assertStatus(200);

        $resLoan = $this->getJson('/api/loans?search=Hamilton');
        $resLoan->assertStatus(200);
        $this->assertCount(1, $resLoan->json('data'));
        $this->assertEquals($target->id, $resLoan->json('data.0.id'));

        // Search by customer number
        $resCustNum = $this->getJson('/api/loans?search=77777');
        $resCustNum->assertStatus(200);
        $this->assertCount(1, $resCustNum->json('data'));
        $this->assertEquals($target->id, $resCustNum->json('data.0.id'));
    }

    public function test_loans_can_be_filtered_by_type_term_and_amount_ranges(): void
    {
        Sanctum::actingAs($this->admin);
        $l1 = Loan::factory()->create([
            'customer_id'         => $this->customer->id,
            'loan_type'           => 'mortgage',
            'term_months'         => 360,
            'principal_amount'    => 250000,
            'outstanding_balance' => 240000,
            'interest_rate'       => 4.5,
        ]);
        Loan::factory()->create([
            'customer_id'         => $this->customer->id,
            'loan_type'           => 'personal',
            'term_months'         => 12,
            'principal_amount'    => 5000,
            'outstanding_balance' => 3000,
            'interest_rate'       => 8.0,
        ]);

        $response = $this->getJson('/api/loans?' . http_build_query([
            'loan_type'               => 'mortgage',
            'term_months'             => 360,
            'principal_amount_min'    => 200000,
            'principal_amount_max'    => 300000,
            'outstanding_balance_min' => 200000,
            'interest_rate_max'       => 5.0,
        ]));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($l1->id, $response->json('data.0.id'));
    }

    public function test_loans_can_be_sorted_by_principal_and_outstanding_balance(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->create(['customer_id' => $this->customer->id, 'principal_amount' => 1000]);
        Loan::factory()->create(['customer_id' => $this->customer->id, 'principal_amount' => 50000]);
        Loan::factory()->create(['customer_id' => $this->customer->id, 'principal_amount' => 10000]);

        // Ascending by principal
        $resAsc = $this->getJson('/api/loans?sort_by=principal_amount&sort_direction=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals(1000.0, $resAsc->json('data.0.principal_amount'));

        // Descending by principal
        $resDesc = $this->getJson('/api/loans?sort_by=principal_amount&sort_direction=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals(50000.0, $resDesc->json('data.0.principal_amount'));
    }

    public function test_loans_pagination_defaults_to_15_per_page(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->count(20)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/loans');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_loans_supports_custom_per_page_and_page(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->count(20)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/loans?per_page=8&page=2');

        $response->assertStatus(200);
        $this->assertCount(8, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(8, $response->json('meta.per_page'));
    }

    public function test_loan_response_includes_customer(): void
    {
        Sanctum::actingAs($this->csr);
        Loan::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/loans');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'loan_number',
                    'customer' => ['id', 'full_name'],
                ],
            ],
        ]);
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_can_view_single_loan(): void
    {
        Sanctum::actingAs($this->csr);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson("/api/loans/{$loan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $loan->id)
            ->assertJsonStructure(['data' => ['id', 'loan_number', 'loan_type', 'status', 'customer']]);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function test_manager_can_submit_loan_application(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/loans', [
            'customer_id'      => $this->customer->id,
            'loan_type'        => 'personal',
            'principal_amount' => 10000,
            'interest_rate'    => 5.5,
            'term_months'      => 24,
            'start_date'       => now()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Loan application submitted successfully.'])
            ->assertJsonPath('data.status', 'pending');

        $this->assertEquals(10000.00, (float) $response->json('data.outstanding_balance'));
        $this->assertDatabaseHas('loans', ['customer_id' => $this->customer->id, 'status' => 'pending']);
    }

    public function test_csr_cannot_submit_loan_application(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->postJson('/api/loans', [
            'customer_id'      => $this->customer->id,
            'loan_type'        => 'personal',
            'principal_amount' => 10000,
            'interest_rate'    => 5.5,
            'term_months'      => 24,
            'start_date'       => now()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_loan_creation_rejects_missing_required_fields(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/loans', [])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'loan_type', 'principal_amount', 'interest_rate', 'term_months', 'start_date']);
    }

    public function test_loan_creation_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/loans', [
            'customer_id'      => $this->customer->id,
            'loan_type'        => 'payday', // invalid
            'principal_amount' => 5000,
            'interest_rate'    => 3.0,
            'term_months'      => 12,
            'start_date'       => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['loan_type']);
    }

    // ─── Approve ─────────────────────────────────────────────────────────────────

    public function test_manager_can_approve_pending_loan(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);

        $response = $this->postJson("/api/loans/{$loan->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Loan approved and activated successfully.'])
            ->assertJsonPath('data.status', 'active');
    }

    public function test_approving_non_pending_loan_returns_404(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);

        $this->postJson("/api/loans/{$loan->id}/approve")->assertStatus(404);
    }

    public function test_csr_cannot_approve_loan(): void
    {
        Sanctum::actingAs($this->csr);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);

        $this->postJson("/api/loans/{$loan->id}/approve")->assertStatus(403);
    }

    // ─── Update / Status Lifecycle ────────────────────────────────────────────────

    public function test_csr_cannot_update_loan(): void
    {
        Sanctum::actingAs($this->csr);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);

        $this->putJson("/api/loans/{$loan->id}", ['status' => 'active'])->assertStatus(403);
    }

    public function test_loan_valid_status_transitions(): void
    {
        Sanctum::actingAs($this->manager);

        // pending → active
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);
        $this->putJson("/api/loans/{$loan->id}", ['status' => 'active'])->assertStatus(200);

        // active → delinquent
        $loan->refresh();
        $this->putJson("/api/loans/{$loan->id}", ['status' => 'delinquent'])->assertStatus(200);

        // delinquent → defaulted
        $loan->refresh();
        $this->putJson("/api/loans/{$loan->id}", ['status' => 'defaulted'])->assertStatus(200);

        // defaulted → completed
        $loan->refresh();
        $this->putJson("/api/loans/{$loan->id}", ['status' => 'completed'])->assertStatus(200);
    }

    public function test_loan_invalid_status_transitions_are_rejected(): void
    {
        Sanctum::actingAs($this->manager);

        // Cannot go from pending → defaulted directly
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'pending']);
        $this->putJson("/api/loans/{$loan->id}", ['status' => 'defaulted'])
            ->assertStatus(422)
            ->assertJsonPath('message', "Invalid status transition from 'pending' to 'defaulted'.");

        // Cannot go from active → pending
        $loan2 = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'active']);
        $this->putJson("/api/loans/{$loan2->id}", ['status' => 'pending'])
            ->assertStatus(422);
    }

    public function test_completed_loan_cannot_be_modified(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'completed']);

        $this->putJson("/api/loans/{$loan->id}", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Completed loans cannot be modified or transitioned to other states.');
    }

    public function test_loan_auto_completes_when_outstanding_balance_reaches_zero(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create([
            'customer_id'         => $this->customer->id,
            'status'              => 'active',
            'outstanding_balance' => 500.00,
        ]);

        $response = $this->putJson("/api/loans/{$loan->id}", [
            'outstanding_balance' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals(0.00, (float) $response->json('data.outstanding_balance'));
    }

    public function test_delinquent_loan_generates_compliance_alert(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'active',
        ]);

        $this->putJson("/api/loans/{$loan->id}", ['status' => 'delinquent'])->assertStatus(200);

        $this->assertDatabaseHas('alerts', [
            'alertable_type' => Loan::class,
            'alertable_id'   => $loan->id,
            'severity'       => 'medium',
            'status'         => 'open',
        ]);
    }

    public function test_defaulted_loan_generates_high_severity_alert(): void
    {
        Sanctum::actingAs($this->manager);
        $loan = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'delinquent',
        ]);

        $this->putJson("/api/loans/{$loan->id}", ['status' => 'defaulted'])->assertStatus(200);

        $this->assertDatabaseHas('alerts', [
            'alertable_type' => Loan::class,
            'alertable_id'   => $loan->id,
            'severity'       => 'high',
            'status'         => 'open',
        ]);
    }
}
