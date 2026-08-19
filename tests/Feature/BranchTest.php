<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $csr;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch  = Branch::factory()->create(['status' => 'active']);
        $this->admin   = User::factory()->create(['role' => 'admin',   'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr     = User::factory()->create(['role' => 'csr',     'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_branches(): void
    {
        Sanctum::actingAs($this->csr);
        Branch::factory()->count(3)->create();

        $response = $this->getJson('/api/branches');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'branch_code', 'branch_name', 'status', 'total_employees'],
                ],
            ]);
    }

    public function test_branches_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->csr);
        Branch::factory()->create(['status' => 'active']);
        Branch::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/branches?status=inactive');

        $response->assertStatus(200);
        foreach ($response->json('data') as $branch) {
            $this->assertEquals('inactive', $branch['status']);
        }
    }

    public function test_branches_can_be_searched_by_name(): void
    {
        Sanctum::actingAs($this->csr);
        $uniqueTerm = 'XQZFINCTR' . uniqid();
        Branch::factory()->create(['branch_name' => $uniqueTerm . ' Center', 'city' => 'Boston']);
        Branch::factory()->create(['branch_name' => 'Uptown Branch', 'city' => 'Boston']);

        $response = $this->getJson('/api/branches?search=' . $uniqueTerm);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString($uniqueTerm, $response->json('data.0.branch_name'));
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_can_view_single_branch(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->getJson("/api/branches/{$this->branch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->branch->id)
            ->assertJsonStructure(['data' => ['id', 'branch_code', 'branch_name', 'status', 'total_employees']]);
    }

    public function test_show_returns_404_for_non_existent_branch(): void
    {
        Sanctum::actingAs($this->csr);
        $this->getJson('/api/branches/999999')->assertStatus(404);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function test_admin_can_create_branch(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/branches', [
            'branch_code' => 'BR-TEST-001',
            'branch_name' => 'Test Branch',
            'city'        => 'New York',
            'status'      => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Branch created successfully.'])
            ->assertJsonPath('data.branch_code', 'BR-TEST-001');

        $this->assertDatabaseHas('branches', ['branch_code' => 'BR-TEST-001']);
    }

    public function test_branch_code_must_be_unique(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/branches', [
            'branch_code' => $this->branch->branch_code, // duplicate
            'branch_name' => 'Another Branch',
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_code']);
    }

    public function test_branch_creation_requires_code_and_name(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/branches', [])->assertStatus(422)
            ->assertJsonValidationErrors(['branch_code', 'branch_name']);
    }

    public function test_branch_creation_rejects_invalid_status(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/branches', [
            'branch_code' => 'BR-TEST-002',
            'branch_name' => 'Test Branch',
            'status'      => 'closed', // invalid enum
        ])->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_manager_cannot_create_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/branches', [
            'branch_code' => 'BR-TEST-003',
            'branch_name' => 'Unauthorized Branch',
        ])->assertStatus(403);
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function test_admin_can_update_branch_details(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/branches/{$this->branch->id}", [
            'branch_name' => 'Updated Branch Name',
            'status'      => 'under_renovation',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Branch updated successfully.'])
            ->assertJsonPath('data.branch_name', 'Updated Branch Name')
            ->assertJsonPath('data.status', 'under_renovation');
    }

    public function test_manager_cannot_update_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $this->putJson("/api/branches/{$this->branch->id}", [
            'branch_name' => 'Unauthorized Update',
        ])->assertStatus(403);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_empty_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $emptyBranch = Branch::factory()->create(['status' => 'inactive']);

        $this->deleteJson("/api/branches/{$emptyBranch->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Branch deleted successfully.']);

        $this->assertDatabaseMissing('branches', ['id' => $emptyBranch->id]);
    }

    public function test_cannot_delete_branch_with_assigned_employees(): void
    {
        Sanctum::actingAs($this->admin);

        // $this->branch already has $this->admin, $this->manager, $this->csr
        $this->deleteJson("/api/branches/{$this->branch->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a branch that has assigned employees or customers.');
    }

    public function test_cannot_delete_branch_with_assigned_customers(): void
    {
        Sanctum::actingAs($this->admin);
        $emptyBranch = Branch::factory()->create();
        Customer::factory()->create(['branch_id' => $emptyBranch->id]);

        $this->deleteJson("/api/branches/{$emptyBranch->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a branch that has assigned employees or customers.');
    }

    public function test_csr_cannot_delete_branch(): void
    {
        Sanctum::actingAs($this->csr);
        $emptyBranch = Branch::factory()->create(['status' => 'inactive']);

        $this->deleteJson("/api/branches/{$emptyBranch->id}")->assertStatus(403);
    }
}
