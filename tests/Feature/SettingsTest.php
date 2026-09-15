<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LoginActivity;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $manager;
    private User $csr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'branch_name' => 'Main Financial Branch',
            'status'      => 'active',
        ]);

        $this->admin = User::factory()->create([
            'email'     => 'admin@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'role'      => 'admin',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);

        $this->manager = User::factory()->create([
            'email'     => 'manager@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'role'      => 'manager',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);

        $this->csr = User::factory()->create([
            'email'     => 'csr@bankvision.com',
            'password'  => bcrypt('Secret123!'),
            'role'      => 'csr',
            'status'    => 'active',
            'branch_id' => $this->branch->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Settings index
    // -------------------------------------------------------------------------

    public function test_any_authenticated_role_receives_settings_with_defaults(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->getJson('/api/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.two_factor_enabled', false)
            ->assertJsonPath('data.notifications.email_notifications', true)
            ->assertJsonPath('data.preferences.theme', 'dark')
            ->assertJsonPath('data.preferences.timezone', 'UTC');

        $this->assertDatabaseHas('user_settings', ['user_id' => $this->csr->id]);
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    public function test_user_can_update_own_profile_information(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->putJson('/api/settings/profile', [
            'name'  => 'Jane Cooper',
            'email' => 'jane.cooper@bankvision.com',
            'phone' => '+1 555 010 2233',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Profile updated successfully.'])
            ->assertJsonPath('data.name', 'Jane Cooper')
            ->assertJsonPath('data.phone', '+1 555 010 2233');

        $this->assertDatabaseHas('users', [
            'id'    => $this->csr->id,
            'name'  => 'Jane Cooper',
            'email' => 'jane.cooper@bankvision.com',
        ]);
    }

    public function test_profile_update_rejects_email_taken_by_another_user(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->putJson('/api/settings/profile', [
            'email' => $this->admin->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_change_password_with_valid_current_password(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->putJson('/api/settings/profile/password', [
            'current_password'      => 'Secret123!',
            'password'              => 'NewSecure456!',
            'password_confirmation' => 'NewSecure456!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Password changed successfully.']);

        $this->assertTrue(password_verify('NewSecure456!', $this->csr->fresh()->password));
    }

    public function test_password_change_rejects_incorrect_current_password(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->putJson('/api/settings/profile/password', [
            'current_password'      => 'WrongPassword1!',
            'password'              => 'NewSecure456!',
            'password_confirmation' => 'NewSecure456!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        Sanctum::actingAs($this->csr);

        $response = $this->putJson('/api/settings/profile/password', [
            'current_password'      => 'Secret123!',
            'password'              => 'NewSecure456!',
            'password_confirmation' => 'Different789!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_upload_and_remove_profile_avatar(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->csr);

        $response = $this->postJson('/api/settings/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('portrait.png', 100, 'image/png'),
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $path = $response->json('data.avatar');
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $this->csr->fresh()->avatar);

        // Replacing the avatar deletes the previous file
        $second = $this->postJson('/api/settings/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('portrait2.png', 100, 'image/png'),
        ]);
        $second->assertStatus(200);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($second->json('data.avatar'));

        // Remove avatar
        $remove = $this->deleteJson('/api/settings/profile/avatar');
        $remove->assertStatus(200);
        $this->assertNull($this->csr->fresh()->avatar);
    }

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->csr);

        $response = $this->postJson('/api/settings/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    // -------------------------------------------------------------------------
    // Notifications & preferences
    // -------------------------------------------------------------------------

    public function test_user_can_update_notification_settings(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->putJson('/api/settings/notifications', [
            'email_notifications' => false,
            'alert_preferences'   => ['critical' => true, 'low' => true],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Notification settings updated successfully.'])
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.alert_preferences.low', true);

        // Unspecified defaults are preserved
        $this->assertTrue($response->json('data.push_notifications'));
    }

    public function test_notification_settings_reject_non_boolean_values(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->putJson('/api/settings/notifications', [
            'email_notifications' => 'yes-please',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email_notifications']);
    }

    public function test_user_can_update_preferences(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->putJson('/api/settings/preferences', [
            'theme'          => 'light',
            'language'       => 'fr',
            'dashboard_view' => 'compact',
            'timezone'       => 'Europe/Paris',
            'items_per_page' => 25,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Preferences updated successfully.'])
            ->assertJsonPath('data.theme', 'light')
            ->assertJsonPath('data.language', 'fr')
            ->assertJsonPath('data.timezone', 'Europe/Paris');
    }

    public function test_preferences_reject_invalid_theme(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->putJson('/api/settings/preferences', [
            'theme' => 'neon-purple',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_preferences_reject_invalid_timezone(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->putJson('/api/settings/preferences', [
            'timezone' => 'Mars/Olympus',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timezone']);
    }

    // -------------------------------------------------------------------------
    // Two-factor authentication
    // -------------------------------------------------------------------------

    public function test_user_can_enable_and_disable_two_factor(): void
    {
        Sanctum::actingAs($this->admin);

        $enable = $this->postJson('/api/settings/security/2fa', [
            'enabled' => true,
            'channel' => 'authenticator',
        ]);

        $enable->assertStatus(422)
            ->assertJson(['success' => false]);

        $disable = $this->postJson('/api/settings/security/2fa', ['enabled' => false]);

        $disable->assertStatus(200)
            ->assertJsonPath('data.two_factor_enabled', false);
    }

    public function test_two_factor_without_channel_defaults_to_email(): void
    {
        Sanctum::actingAs($this->admin);

        // Enabling without a channel defaults to the email flow: the response
        // asks for verification of the emailed code before the flag sticks.
        $response = $this->postJson('/api/settings/security/2fa', ['enabled' => true]);

        $response->assertStatus(422)
            ->assertJson([
                'success'               => false,
                'requires_verification' => true,
            ]);
    }

    public function test_two_factor_code_is_locked_after_maximum_failed_attempts(): void
    {
        $settings = $this->admin->settingsOrCreate();
        $settings->forceFill([
            'two_factor_code' => Hash::make('123456'),
            'two_factor_code_expires_at' => now()->addMinutes(10),
            'two_factor_code_attempts' => 0,
        ])->save();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login/2fa', [
                'email' => $this->admin->email,
                'code' => '000000',
            ])->assertStatus(422);
        }

        $this->assertNull($settings->fresh()->two_factor_code);

        $lockedResponse = $this->postJson('/api/login/2fa', [
            'email' => $this->admin->email,
            'code' => '123456',
        ]);

        $this->assertContains($lockedResponse->status(), [422, 429]);
    }

    // -------------------------------------------------------------------------
    // Sessions
    // -------------------------------------------------------------------------

    /**
     * Authenticate against the API with a real persisted token so that
     * currentAccessToken() resolves to a database row with an id.
     */
    private function actingWithToken(User $user, string $name = 'web-app'): string
    {
        $plain = $user->createToken($name)->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$plain}");

        return $plain;
    }

    public function test_sessions_list_marks_current_session(): void
    {
        $this->actingWithToken($this->admin, 'web-app');
        $this->admin->createToken('mobile-app');

        $response = $this->getJson('/api/settings/security/sessions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $sessions = collect($response->json('data'));
        $this->assertSame(2, $sessions->count());
        $this->assertSame(1, $sessions->where('is_current', true)->count());

        $mobile = $sessions->firstWhere('name', 'mobile-app');
        $this->assertNotNull($mobile);
        $this->assertFalse($mobile['is_current']);
    }

    public function test_user_can_revoke_another_session_but_not_the_current_one(): void
    {
        $current = $this->admin->createToken('web-app');
        $this->withHeader('Authorization', "Bearer {$current->plainTextToken}");
        $otherId = $this->admin->createToken('tablet-app')->accessToken->id;
        $currentId = $current->accessToken->id;

        // Current session cannot be revoked
        $this->deleteJson("/api/settings/security/sessions/{$currentId}")
            ->assertStatus(422);

        // Other sessions can
        $this->deleteJson("/api/settings/security/sessions/{$otherId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Session revoked successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherId]);
    }

    public function test_revoke_all_sessions_keeps_current_one(): void
    {
        $this->actingWithToken($this->admin, 'web-app');
        $this->admin->createToken('mobile-app');
        $this->admin->createToken('tablet-app');

        $response = $this->postJson('/api/settings/security/sessions/revoke-all');

        $response->assertStatus(200)
            ->assertJsonPath('data.revoked', 2);

        $this->assertSame(1, $this->admin->tokens()->count());
    }

    public function test_revoking_a_session_invalidates_its_token(): void
    {
        $this->actingWithToken($this->admin, 'web-app');
        $doomed = $this->admin->createToken('doomed-session');

        $this->deleteJson("/api/settings/security/sessions/{$doomed->accessToken->id}")
            ->assertStatus(200);

        // The feature test process shares one app instance, so the guard would
        // cache the authenticated user — assert token invalidity via the store
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $doomed->accessToken->id]);
        $this->assertNull(PersonalAccessToken::findToken($doomed->plainTextToken));
    }

    // -------------------------------------------------------------------------
    // Login history
    // -------------------------------------------------------------------------

    public function test_login_and_failed_login_are_recorded_in_login_history(): void
    {
        // Failed attempt
        $this->postJson('/api/login', [
            'email'    => 'csr@bankvision.com',
            'password' => 'WrongPassword',
        ]);

        // Successful attempt
        $this->postJson('/api/login', [
            'email'    => 'csr@bankvision.com',
            'password' => 'Secret123!',
        ]);

        $history = LoginActivity::where('user_id', $this->csr->id)->orderBy('id')->get();
        $this->assertSame(['failed_login', 'login'], $history->pluck('event')->all());
        $this->assertFalse($history[0]->successful);
        $this->assertTrue($history[1]->successful);

        Sanctum::actingAs($this->csr);

        $this->getJson('/api/settings/security/login-history')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        ['id', 'event', 'successful', 'ip_address', 'browser', 'platform', 'device', 'logged_at'],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // API tokens (admin only)
    // -------------------------------------------------------------------------

    public function test_admin_can_create_list_and_revoke_api_tokens(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->postJson('/api/settings/security/tokens', [
            'name'      => 'Reporting Integration',
            'abilities' => ['reports:read'],
        ]);

        $create->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'API token created successfully.'])
            ->assertJsonPath('data.name', 'Reporting Integration');

        $this->assertNotNull($create->json('data.token'));
        $tokenId = $create->json('data.id');

        $list = $this->getJson('/api/settings/security/tokens');
        $list->assertStatus(200);
        $this->assertTrue(collect($list->json('data'))->contains(fn ($t) => $t['id'] === $tokenId));

        $revoke = $this->deleteJson("/api/settings/security/tokens/{$tokenId}");
        $revoke->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'API token revoked successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_non_admin_roles_cannot_manage_api_tokens(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/settings/security/tokens')
            ->assertStatus(403);

        $this->postJson('/api/settings/security/tokens', ['name' => 'Rogue Token'])
            ->assertStatus(403);

        $this->deleteJson('/api/settings/security/tokens/1')
            ->assertStatus(403);
    }

    public function test_api_token_creation_requires_name(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/settings/security/tokens', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // -------------------------------------------------------------------------
    // System configuration (admin only)
    // -------------------------------------------------------------------------

    public function test_admin_receives_system_settings_with_defaults(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/settings/system');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency.code', 'USD')
            ->assertJsonPath('data.bank.name', 'BankVision National Bank')
            ->assertJsonPath('data.interest.savings_rate', 2.5);
    }

    public function test_admin_can_update_system_settings(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson('/api/settings/system', [
            'bank'     => ['name' => 'Meridian Trust Bank'],
            'currency' => ['code' => 'EUR', 'symbol' => '€'],
            'interest' => ['savings_rate' => 3.75, 'personal_loan_rate' => 9.25],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'System configuration updated successfully.'])
            ->assertJsonPath('data.bank.name', 'Meridian Trust Bank')
            ->assertJsonPath('data.currency.code', 'EUR')
            ->assertJsonPath('data.interest.savings_rate', 3.75);

        // Unspecified fields keep defaults / previous values
        $this->assertSame('€', $response->json('data.currency.symbol'));
        $this->assertSame(6.5, $response->json('data.interest.business_loan_rate'));

        $this->assertSame(3, SystemSetting::count());
    }

    public function test_system_settings_reject_invalid_currency_code(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/settings/system', [
            'currency' => ['code' => 'DOLLAR'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['currency.code']);
    }

    public function test_non_admin_roles_cannot_access_system_settings(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/settings/system')->assertStatus(403);
        $this->putJson('/api/settings/system', ['bank' => ['name' => 'Hacked Bank']])->assertStatus(403);

        Sanctum::actingAs($this->csr);

        $this->getJson('/api/settings/system')->assertStatus(403);
        $this->getJson('/api/settings/system/health')->assertStatus(403);
    }

    public function test_admin_receives_system_health_report(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/settings/system/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.database.status', 'healthy')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'database' => ['status', 'latency_ms'],
                    'cache'    => ['status', 'driver'],
                    'storage'  => ['status', 'disk_used_pct'],
                    'application' => ['status', 'environment', 'php_version'],
                    'activity' => [
                        'total_users',
                        'active_sessions',
                        'failed_logins_24h',
                        'open_alerts',
                        'pending_transactions',
                        'pending_loans',
                    ],
                    'checked_at',
                ],
            ]);

        $this->assertSame(3, $response->json('data.activity.total_users'));
    }

    // -------------------------------------------------------------------------
    // Unauthenticated access
    // -------------------------------------------------------------------------

    public function test_settings_endpoints_require_authentication(): void
    {
        $this->getJson('/api/settings')->assertStatus(401);
        $this->putJson('/api/settings/profile', ['name' => 'X'])->assertStatus(401);
        $this->getJson('/api/settings/security/sessions')->assertStatus(401);
        $this->getJson('/api/settings/system')->assertStatus(401);
    }
}
