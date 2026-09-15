<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\SarFiling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SarFilingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $compliance;
    private User $manager;
    private User $analyst;
    private User $auditor;
    private User $csr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['status' => 'active']);

        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager    = User::factory()->create(['role' => 'manager',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->analyst    = User::factory()->create(['role' => 'analyst',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name'   => 'Apex Global Logistics LLC',
            'customer_number' => 'CUST-0012',
            'category'        => 'Rapid Wire Movement / Pass-through Account',
            'amount'          => 145000,
            'status'          => 'under_review',
            'narrative'       => 'Inbound international wire immediately divided into 6 domestic transfers.',
            'action_taken'    => 'FinCEN BSA Form 111 Transmitted',
        ], $overrides);
    }

    // ─── Index ──────────────────────────────────────────────────────────────────

    public function test_aml_dashboard_roles_can_list_sar_filings(): void
    {
        Sanctum::actingAs($this->auditor);
        SarFiling::factory()->count(3)->create();

        $response = $this->getJson('/api/sar-filings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'reference', 'customer_name', 'category', 'amount', 'status', 'narrative', 'date'],
                ],
                'links', 'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_csr_cannot_list_sar_filings(): void
    {
        Sanctum::actingAs($this->csr);

        $this->getJson('/api/sar-filings')->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_list_sar_filings(): void
    {
        $this->getJson('/api/sar-filings')->assertStatus(401);
    }

    public function test_sar_filings_can_be_searched_and_filtered(): void
    {
        Sanctum::actingAs($this->admin);
        SarFiling::factory()->create(['customer_name' => 'Apex Global Logistics LLC', 'status' => 'filed']);
        SarFiling::factory()->create(['customer_name' => 'Oakhaven Timber Ltd', 'status' => 'draft']);

        $search = $this->getJson('/api/sar-filings?search=Apex');
        $search->assertStatus(200);
        $this->assertCount(1, $search->json('data'));
        $this->assertEquals('Apex Global Logistics LLC', $search->json('data.0.customer_name'));

        $status = $this->getJson('/api/sar-filings?status=draft');
        $status->assertStatus(200);
        $this->assertCount(1, $status->json('data'));
        $this->assertEquals('draft', $status->json('data.0.status'));
    }

    // ─── Store ──────────────────────────────────────────────────────────────────

    public function test_compliance_officer_can_file_sar(): void
    {
        Sanctum::actingAs($this->compliance);

        $response = $this->postJson('/api/sar-filings', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer_name', 'Apex Global Logistics LLC')
            ->assertJsonPath('data.status', 'under_review');

        $this->assertDatabaseHas('sar_filings', [
            'customer_name' => 'Apex Global Logistics LLC',
            'user_id'       => $this->compliance->id,
        ]);
        $this->assertStringStartsWith('SAR-' . date('Y') . '-', $response->json('data.reference'));
    }

    public function test_manager_and_admin_can_file_sar(): void
    {
        Sanctum::actingAs($this->manager);
        $this->postJson('/api/sar-filings', $this->validPayload())->assertStatus(201);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/sar-filings', $this->validPayload())->assertStatus(201);
    }

    public function test_analyst_and_csr_cannot_file_sar(): void
    {
        Sanctum::actingAs($this->analyst);
        $this->postJson('/api/sar-filings', $this->validPayload())->assertStatus(403);

        Sanctum::actingAs($this->csr);
        $this->postJson('/api/sar-filings', $this->validPayload())->assertStatus(403);
    }

    public function test_sar_filing_requires_valid_payload(): void
    {
        Sanctum::actingAs($this->compliance);

        $this->postJson('/api/sar-filings', $this->validPayload([
            'customer_name' => '',
            'amount'        => -5,
            'narrative'     => 'short',
            'category'      => 'Made-up Category',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'amount', 'narrative', 'category']);
    }

    public function test_sar_filing_status_must_be_sanctioned(): void
    {
        Sanctum::actingAs($this->compliance);

        $this->postJson('/api/sar-filings', $this->validPayload(['status' => 'closed']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_sar_alert_reference_must_match_reported_customer(): void
    {
        Sanctum::actingAs($this->compliance);
        $customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_number' => 'CUST-REAL',
        ]);
        $alert = Alert::factory()->create([
            'alertable_type' => Customer::class,
            'alertable_id' => $customer->id,
        ]);

        $this->postJson('/api/sar-filings', $this->validPayload([
            'customer_number' => 'CUST-OTHER',
            'alert_id' => $alert->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['alert_id']);
    }

    public function test_manager_sar_registry_is_scoped_to_their_branch(): void
    {
        $branch2 = Branch::factory()->create(['status' => 'active']);
        $custBranch1 = Customer::factory()->create(['branch_id' => $this->branch->id, 'customer_number' => 'CUST-B1']);
        $custBranch2 = Customer::factory()->create(['branch_id' => $branch2->id, 'customer_number' => 'CUST-B2']);

        // Filing 1: Against customer in manager's branch
        SarFiling::factory()->create([
            'customer_number' => $custBranch1->customer_number,
            'customer_name'   => $custBranch1->full_name,
            'user_id'         => $this->compliance->id,
        ]);

        // Filing 2: Against customer in a different branch
        SarFiling::factory()->create([
            'customer_number' => $custBranch2->customer_number,
            'customer_name'   => $custBranch2->full_name,
            'user_id'         => $this->compliance->id,
        ]);

        // Filing 3: Filed by this manager personally without customer_number
        SarFiling::factory()->create([
            'customer_number' => null,
            'user_id'         => $this->manager->id,
        ]);

        // Manager accesses SAR registry
        Sanctum::actingAs($this->manager);
        $response = $this->getJson('/api/sar-filings');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // Compliance officer accesses SAR registry — gets all 3 bank-wide
        Sanctum::actingAs($this->compliance);
        $responseCompliance = $this->getJson('/api/sar-filings');
        $responseCompliance->assertStatus(200);
        $this->assertCount(3, $responseCompliance->json('data'));
    }

    public function test_sar_filing_resource_returns_string_amount(): void
    {
        Sanctum::actingAs($this->compliance);
        $filing = SarFiling::factory()->create(['amount' => '12500.50']);

        $response = $this->getJson('/api/sar-filings');

        $response->assertStatus(200);
        $first = collect($response->json('data'))->firstWhere('id', $filing->id);
        $this->assertNotNull($first);
        $this->assertSame('12500.50', $first['amount']);
    }
}
