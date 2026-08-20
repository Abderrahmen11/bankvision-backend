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

    public function test_csr_can_submit_loan_application(): void
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

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Loan application submitted successfully.'])
            ->assertJsonPath('data.status', 'pending');

        $this->assertEquals(10000.00, (float) $response->json('data.outstanding_balance'));
        $this->assertDatabaseHas('loans', ['customer_id' => $this->customer->id, 'status' => 'pending']);
    }

    public function test_loan_creation_rejects_missing_required_fields(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/loans', [])->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'loan_type', 'principal_amount', 'interest_rate', 'term_months', 'start_date']);
    }

    public function test_loan_creation_rejects_invalid_type(): void
    {
        Sanctum::actingAs($this->csr);

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

    public function test_loan_valid_status_transitions(): void
    {
        Sanctum::actingAs($this->csr);

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
        Sanctum::actingAs($this->csr);

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
        Sanctum::actingAs($this->csr);
        $loan = Loan::factory()->create(['customer_id' => $this->customer->id, 'status' => 'completed']);

        $this->putJson("/api/loans/{$loan->id}", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Completed loans cannot be modified or transitioned to other states.');
    }

    public function test_loan_auto_completes_when_outstanding_balance_reaches_zero(): void
    {
        Sanctum::actingAs($this->csr);
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
        Sanctum::actingAs($this->csr);
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
        Sanctum::actingAs($this->csr);
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
