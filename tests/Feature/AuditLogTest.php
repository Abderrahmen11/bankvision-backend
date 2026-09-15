<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $compliance;
    private User $auditor;
    private User $csr;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch     = Branch::factory()->create(['status' => 'active']);
        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch->id]);
    }

    // ─── Index & Authorization ──────────────────────────────────────────────────

    public function test_admin_can_list_all_audit_logs(): void
    {
        Sanctum::actingAs($this->admin);
        AuditLog::factory()->count(5)->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'action', 'table_name', 'record_id', 'ip_address', 'created_at'],
                ],
                'links', 'meta',
            ]);
        // Model observers add their own rows during setUp — assert the seeded
        // logs are all visible instead of an exact global count.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $seeded = AuditLog::where('user_id', $this->admin->id)->where('action', 'login')->pluck('id')->all();
        $this->assertGreaterThanOrEqual(5, count($ids));
        foreach ($seeded as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_auditor_can_list_all_audit_logs(): void
    {
        Sanctum::actingAs($this->auditor);
        AuditLog::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
        // Observers add system rows during setUp — assert the seeded logs are visible.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $seeded = AuditLog::where('user_id', $this->admin->id)->where('action', 'login')->pluck('id')->all();
        $this->assertGreaterThanOrEqual(3, count($ids));
        foreach ($seeded as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_csr_cannot_access_audit_logs(): void
    {
        Sanctum::actingAs($this->csr);
        $this->getJson('/api/audit-logs')->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')->assertStatus(401);
    }

    // ─── Compliance Officer Scoping ─────────────────────────────────────────────

    public function test_compliance_officer_only_sees_compliance_relevant_audit_logs(): void
    {
        Sanctum::actingAs($this->compliance);

        // Compliance-relevant logs (banking tables or compliance actions)
        $l1 = AuditLog::factory()->create(['table_name' => 'customers', 'action' => 'update']);
        $l2 = AuditLog::factory()->create(['table_name' => 'transactions', 'action' => 'flag']);
        $l3 = AuditLog::factory()->create(['table_name' => 'alerts', 'action' => 'approve']);

        // Internal user management log (non-compliance action)
        $l4 = AuditLog::factory()->create(['table_name' => 'users', 'action' => 'login']);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
        // Observers add their own rows — scope assertions to seeded relevance:
        // the compliance-relevant rows must be visible, the non-relevant
        // users/login row must stay hidden.
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($l1->id, $ids);
        $this->assertContains($l2->id, $ids);
        $this->assertContains($l3->id, $ids);
        $this->assertNotContains($l4->id, $ids);
    }

    // ─── Search, Filter, Sort, Pagination ───────────────────────────────────────

    public function test_audit_logs_can_be_filtered_by_action_and_table_name(): void
    {
        Sanctum::actingAs($this->admin);
        AuditLog::factory()->create(['action' => 'approve', 'table_name' => 'transactions']);
        AuditLog::factory()->create(['action' => 'update', 'table_name' => 'customers']);

        $response = $this->getJson('/api/audit-logs?action=approve&table_name=transactions');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('approve', $response->json('data.0.action'));
        $this->assertEquals('transactions', $response->json('data.0.table_name'));
    }

    public function test_audit_logs_can_be_searched(): void
    {
        Sanctum::actingAs($this->admin);
        AuditLog::factory()->create(['action' => 'special_export_action', 'table_name' => 'customers']);
        AuditLog::factory()->create(['action' => 'view', 'table_name' => 'accounts']);

        $response = $this->getJson('/api/audit-logs?search=special_export');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('special_export_action', $response->json('data.0.action'));
    }

    public function test_audit_logs_supports_pagination_and_sorting(): void
    {
        Sanctum::actingAs($this->admin);
        AuditLog::factory()->count(20)->create();

        $response = $this->getJson('/api/audit-logs?per_page=5&page=2&sort_by=created_at&sort_direction=desc');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    // ─── Show ───────────────────────────────────────────────────────────────────

    public function test_can_view_single_audit_log(): void
    {
        Sanctum::actingAs($this->admin);
        $log = AuditLog::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->getJson("/api/audit-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.action', $log->action);
    }

    public function test_show_returns_404_for_non_existent_audit_log(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/audit-logs/999999')->assertStatus(404);
    }
}
