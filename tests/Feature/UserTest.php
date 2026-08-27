<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
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

    public function test_authenticated_user_can_list_users(): void
    {
        Sanctum::actingAs($this->admin);
        User::factory()->count(3)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'status', 'branch'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_manager_only_sees_employees_in_assigned_branch(): void
    {
        Sanctum::actingAs($this->manager);

        $otherBranch = Branch::factory()->create(['status' => 'active']);
        $u1 = User::factory()->create(['role' => 'csr', 'branch_id' => $this->branch->id]);
        $u2 = User::factory()->create(['role' => 'csr', 'branch_id' => $otherBranch->id]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($u1->id, $ids);
        $this->assertNotContains($u2->id, $ids);
    }

    public function test_users_can_be_searched_by_name_email_and_phone(): void
    {
        Sanctum::actingAs($this->admin);
        $target = User::factory()->create([
            'name'      => 'Unique Searchable Person',
            'email'     => 'searchable.target@bankvision.com',
            'phone'     => '+1-555-444-3333',
            'branch_id' => $this->branch->id,
        ]);
        User::factory()->create(['branch_id' => $this->branch->id]);

        // Search by name
        $resName = $this->getJson('/api/users?search=Searchable');
        $resName->assertStatus(200);
        $this->assertCount(1, $resName->json('data'));
        $this->assertEquals($target->id, $resName->json('data.0.id'));

        // Search by email
        $resEmail = $this->getJson('/api/users?search=searchable.target');
        $resEmail->assertStatus(200);
        $this->assertCount(1, $resEmail->json('data'));
        $this->assertEquals($target->id, $resEmail->json('data.0.id'));

        // Search by phone
        $resPhone = $this->getJson('/api/users?search=444-3333');
        $resPhone->assertStatus(200);
        $this->assertCount(1, $resPhone->json('data'));
        $this->assertEquals($target->id, $resPhone->json('data.0.id'));
    }

    public function test_users_can_be_filtered_by_role_status_and_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $otherBranch = Branch::factory()->create();
        $target = User::factory()->create([
            'role'      => 'compliance',
            'status'    => 'active',
            'branch_id' => $otherBranch->id,
        ]);
        User::factory()->create([
            'role'      => 'analyst',
            'status'    => 'suspended',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->getJson("/api/users?role=compliance&status=active&branch_id={$otherBranch->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    public function test_users_can_be_sorted_by_name_and_role(): void
    {
        Sanctum::actingAs($this->admin);
        User::factory()->create(['name' => 'Aaron Adams', 'role' => 'auditor', 'branch_id' => $this->branch->id]);
        User::factory()->create(['name' => 'Zachary Zane', 'role' => 'manager', 'branch_id' => $this->branch->id]);

        // Ascending by name
        $resAsc = $this->getJson('/api/users?sort_by=name&sort_direction=asc');
        $resAsc->assertStatus(200);
        $this->assertEquals('Aaron Adams', $resAsc->json('data.0.name'));

        // Descending by name
        $resDesc = $this->getJson('/api/users?sort_by=name&sort_direction=desc');
        $resDesc->assertStatus(200);
        $this->assertEquals('Zachary Zane', $resDesc->json('data.0.name'));
    }

    public function test_users_pagination_defaults_to_15_per_page(): void
    {
        Sanctum::actingAs($this->admin);
        User::factory()->count(20)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.per_page'));
    }

    public function test_users_supports_custom_per_page_and_page(): void
    {
        Sanctum::actingAs($this->admin);
        User::factory()->count(20)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/users?per_page=5&page=2');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_can_view_single_user_with_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'status', 'branch' => ['id', 'branch_name']],
            ]);
    }

    public function test_show_returns_404_for_non_existent_user(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/users/999999')->assertStatus(404);
    }

    // ─── Store ───────────────────────────────────────────────────────────────────

    public function test_admin_can_create_a_new_user(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'name'      => 'New Bank Employee',
            'email'     => 'new.employee@bankvision.com',
            'password'  => 'SecurePassword123!',
            'role'      => 'analyst',
            'branch_id' => $this->branch->id,
            'phone'     => '+1-555-0199',
            'status'    => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'User created successfully.'])
            ->assertJsonPath('data.name', 'New Bank Employee')
            ->assertJsonPath('data.role', 'analyst');

        $this->assertDatabaseHas('users', ['email' => 'new.employee@bankvision.com']);
    }

    public function test_user_creation_requires_unique_email_and_valid_role(): void
    {
        Sanctum::actingAs($this->admin);

        // Duplicate email
        $this->postJson('/api/users', [
            'name'     => 'Duplicate Person',
            'email'    => $this->admin->email,
            'password' => 'SecurePass123!',
            'role'     => 'csr',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);

        // Invalid role
        $this->postJson('/api/users', [
            'name'     => 'Invalid Role Person',
            'email'    => 'valid.email@bankvision.com',
            'password' => 'SecurePass123!',
            'role'     => 'superhero',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        Sanctum::actingAs($this->csr);

        $this->postJson('/api/users', [
            'name'     => 'Unauthorized Create',
            'email'    => 'unauth@bankvision.com',
            'password' => 'Pass12345!',
            'role'     => 'csr',
        ])->assertStatus(403);
    }

    // ─── Update ──────────────────────────────────────────────────────────────────

    public function test_admin_can_update_user(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->create(['branch_id' => $this->branch->id, 'role' => 'csr']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name'   => 'Promoted User',
            'role'   => 'manager',
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'User updated successfully.'])
            ->assertJsonPath('data.name', 'Promoted User')
            ->assertJsonPath('data.role', 'manager');
    }

    public function test_non_admin_cannot_update_user(): void
    {
        Sanctum::actingAs($this->csr);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Unauthorized Edit',
        ])->assertStatus(403);
    }

    // ─── Destroy ─────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_another_user(): void
    {
        Sanctum::actingAs($this->admin);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        Sanctum::actingAs($this->csr);
        $user = User::factory()->create(['branch_id' => $this->branch->id]);

        $this->deleteJson("/api/users/{$user->id}")->assertStatus(403);
    }
}
