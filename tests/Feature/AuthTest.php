<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $activeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'branch_name' => 'Main Financial Branch',
            'status'      => 'active',
        ]);

        $this->activeUser = User::factory()->create([
            'email'     => 'staff@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'role'      => 'csr',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'staff@bankvision.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'phone',
                    'branch',
                ],
            ]);

        $this->assertNotNull($response->json('token'));
        $this->assertEquals('staff@bankvision.com', $response->json('user.email'));
        $this->assertNotNull($this->activeUser->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'staff@bankvision.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Incorrect password.',
            ]);
    }

    public function test_login_fails_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'unknown@bankvision.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No account found with this email address.',
            ]);
    }

    public function test_inactive_and_suspended_users_cannot_login(): void
    {
        $suspendedUser = User::factory()->create([
            'email'     => 'suspended@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'status'    => 'suspended',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'suspended@bankvision.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account is currently suspended. Please contact your system administrator.',
            ]);

        $pendingUser = User::factory()->create([
            'email'     => 'pending@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'status'    => 'pending',
            'branch_id' => $this->branch->id,
        ]);

        $pendingResponse = $this->postJson('/api/login', [
            'email'    => 'pending@bankvision.com',
            'password' => 'Secret123!',
        ]);

        $pendingResponse->assertStatus(403);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        Sanctum::actingAs($this->activeUser);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('user.id', $this->activeUser->id)
            ->assertJsonPath('user.email', $this->activeUser->email)
            ->assertJsonPath('user.branch.id', $this->branch->id);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        Sanctum::actingAs($this->activeUser);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
    }

    public function test_unauthenticated_request_to_protected_routes_returns_401(): void
    {
        $this->getJson('/api/user')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        $this->getJson('/api/customers')
            ->assertStatus(401);

        $this->getJson('/api/accounts')
            ->assertStatus(401);

        $this->getJson('/api/dashboard/stats')
            ->assertStatus(401);
    }
}
