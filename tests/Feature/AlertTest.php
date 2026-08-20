<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compliance;
    private User $csr;
    private User $analyst;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch     = Branch::factory()->create(['status' => 'active']);
        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->analyst    = User::factory()->create(['role' => 'analyst',    'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // ─── Index ───────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_alerts(): void
    {
        Sanctum::actingAs($this->compliance);
        Alert::factory()->count(4)->create();

        $response = $this->getJson('/api/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'alert_number', 'alert_type', 'severity', 'status'],
                ],
            ]);
    }

    public function test_alerts_can_be_filtered_by_severity(): void
    {
        Sanctum::actingAs($this->compliance);
        Alert::factory()->create(['severity' => 'high']);
        Alert::factory()->create(['severity' => 'low']);

        $response = $this->getJson('/api/alerts?severity=high');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('high', $response->json('data.0.severity'));
    }

    public function test_alerts_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->compliance);
        Alert::factory()->create(['status' => 'open']);
        Alert::factory()->create(['status' => 'resolved']);

        $response = $this->getJson('/api/alerts?status=open');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('open', $response->json('data.0.status'));
    }

    public function test_alerts_can_be_filtered_by_assigned_to(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['assigned_to' => $this->compliance->id]);
        Alert::factory()->create(['assigned_to' => null]);

        $response = $this->getJson("/api/alerts?assigned_to={$this->compliance->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ─── Show ────────────────────────────────────────────────────────────────────

    public function test_can_view_single_alert(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create();

        $response = $this->getJson("/api/alerts/{$alert->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $alert->id)
            ->assertJsonPath('data.alert_number', $alert->alert_number);
    }

    public function test_show_returns_404_for_non_existent_alert(): void
    {
        Sanctum::actingAs($this->compliance);
        $this->getJson('/api/alerts/999999')->assertStatus(404);
    }

    // ─── Resolve ─────────────────────────────────────────────────────────────────

    public function test_compliance_can_resolve_open_alert(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'open']);

        $response = $this->postJson("/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Alert resolved successfully.'])
            ->assertJsonPath('data.status', 'resolved');

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_compliance_can_resolve_in_progress_alert(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'in-progress']);

        $this->postJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');
    }

    public function test_resolving_already_resolved_alert_returns_404(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'resolved']);

        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(404);
    }

    public function test_csr_cannot_resolve_alert(): void
    {
        Sanctum::actingAs($this->csr);
        $alert = Alert::factory()->create(['status' => 'open']);

        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(403);
    }

    public function test_analyst_cannot_resolve_alert(): void
    {
        Sanctum::actingAs($this->analyst);
        $alert = Alert::factory()->create(['status' => 'open']);

        $this->postJson("/api/alerts/{$alert->id}/resolve")->assertStatus(403);
    }

    // ─── Assign ──────────────────────────────────────────────────────────────────

    public function test_compliance_can_assign_alert_to_staff(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'open', 'assigned_to' => null]);

        $response = $this->postJson("/api/alerts/{$alert->id}/assign", [
            'user_id' => $this->compliance->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Alert assigned successfully.'])
            ->assertJsonPath('data.status', 'in-progress');

        $this->assertEquals($this->compliance->id, $alert->fresh()->assigned_to);
    }

    public function test_assigning_alert_requires_valid_user_id(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'open']);

        $this->postJson("/api/alerts/{$alert->id}/assign", [
            'user_id' => 99999, // does not exist
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id']);

        $this->postJson("/api/alerts/{$alert->id}/assign", [])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_assigning_already_in_progress_alert_preserves_status(): void
    {
        Sanctum::actingAs($this->compliance);
        $alert = Alert::factory()->create(['status' => 'in-progress']);

        $this->postJson("/api/alerts/{$alert->id}/assign", [
            'user_id' => $this->compliance->id,
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'in-progress'); // stays in-progress, not re-opened
    }

    public function test_csr_cannot_assign_alert(): void
    {
        Sanctum::actingAs($this->csr);
        $alert = Alert::factory()->create(['status' => 'open']);

        $this->postJson("/api/alerts/{$alert->id}/assign", [
            'user_id' => $this->csr->id,
        ])->assertStatus(403);
    }
}
