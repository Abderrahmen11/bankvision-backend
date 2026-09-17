<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $csr;
    private User $compliance;
    private User $analyst;
    private User $auditor;
    private Branch $branch1;
    private Branch $branch2;
    private Customer $customer1;
    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->branch1    = Branch::factory()->create(['status' => 'active']);
        $this->branch2    = Branch::factory()->create(['status' => 'active']);

        $this->admin      = User::factory()->create(['role' => 'admin',      'status' => 'active', 'branch_id' => $this->branch1->id]);
        $this->manager    = User::factory()->create(['role' => 'manager',    'status' => 'active', 'branch_id' => $this->branch1->id]);
        $this->csr        = User::factory()->create(['role' => 'csr',        'status' => 'active', 'branch_id' => $this->branch1->id]);
        $this->compliance = User::factory()->create(['role' => 'compliance', 'status' => 'active', 'branch_id' => $this->branch1->id]);
        $this->analyst    = User::factory()->create(['role' => 'analyst',    'status' => 'active', 'branch_id' => $this->branch1->id]);
        $this->auditor    = User::factory()->create(['role' => 'auditor',    'status' => 'active', 'branch_id' => $this->branch1->id]);

        $this->customer1  = Customer::factory()->create([
            'branch_id'  => $this->branch1->id,
            'kyc_status' => 'pending',
        ]);
        $this->customer2  = Customer::factory()->create([
            'branch_id'  => $this->branch2->id,
            'kyc_status' => 'pending',
        ]);
    }

    public function test_compliance_officer_can_upload_document_and_verify_customer(): void
    {
        Sanctum::actingAs($this->compliance);

        $file = UploadedFile::fake()->create('passport_eleanor.pdf', 1500, 'application/pdf');

        $response = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'passport',
            'document_number' => 'P987654321',
            'issuing_country' => 'United States',
            'expiry_date'     => '2030-12-31',
            'file'            => $file,
            'attestation'     => true,
            'notes'           => 'Verified via official DMV and State Dept portal.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document_type', 'passport')
            ->assertJsonPath('data.document_number', 'P987654321')
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.file_name', 'passport_eleanor.pdf')
            ->assertJsonPath('customer.kyc_status', 'verified');

        $docId = $response->json('data.id');
        $doc = KycDocument::findOrFail($docId);

        $this->assertEquals('passport', $doc->document_type);
        $this->assertEquals($this->customer1->id, $doc->customer_id);
        $this->assertEquals($this->compliance->id, $doc->uploaded_by);
        $this->assertEquals('verified', $doc->status);

        // Verify physical file was saved on private disk
        Storage::disk('private')->assertExists($doc->file_path);

        // Verify customer KYC status transitioned to verified
        $this->customer1->refresh();
        $this->assertEquals('verified', $this->customer1->kyc_status);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'kyc.document.uploaded',
            'table_name' => 'customers',
            'record_id'  => $this->customer1->id,
            'user_id'    => $this->compliance->id,
        ]);

        $audit = AuditLog::where('action', 'kyc.document.uploaded')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertEquals($doc->id, $audit->new_values['document_id']);
        $this->assertEquals('passport', $audit->new_values['document_type']);
    }

    public function test_upload_validation_failures(): void
    {
        Sanctum::actingAs($this->compliance);

        // 1. Missing attestation
        $file = UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg');
        $res = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => '12345',
            'file'            => $file,
            'attestation'     => false,
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['attestation']);

        // 2. Invalid mime type
        $badFile = UploadedFile::fake()->create('script.sh', 10, 'application/x-sh');
        $res = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => '12345',
            'file'            => $badFile,
            'attestation'     => true,
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['file']);

        // 3. File exceeding 10MB (11MB = 11264 KB)
        $hugeFile = UploadedFile::fake()->create('huge.pdf', 11500, 'application/pdf');
        $res = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => '12345',
            'file'            => $hugeFile,
            'attestation'     => true,
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_csr_cannot_upload_document(): void
    {
        Sanctum::actingAs($this->csr);

        $file = UploadedFile::fake()->create('id.png', 500, 'image/png');
        $response = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => 'CSR-TEST',
            'file'            => $file,
            'attestation'     => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_analyst_and_auditor_cannot_upload_document(): void
    {
        $file = UploadedFile::fake()->create('id.png', 500, 'image/png');

        Sanctum::actingAs($this->analyst);
        $res1 = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => 'ANALYST-TEST',
            'file'            => $file,
            'attestation'     => true,
        ]);
        $res1->assertStatus(403);

        Sanctum::actingAs($this->auditor);
        $res2 = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => 'AUDITOR-TEST',
            'file'            => $file,
            'attestation'     => true,
        ]);
        $res2->assertStatus(403);
    }

    public function test_manager_can_upload_for_own_branch_customer_but_not_other_branch(): void
    {
        Sanctum::actingAs($this->manager);

        // Own branch customer (branch1)
        $file1 = UploadedFile::fake()->create('doc1.png', 400, 'image/png');
        $res1 = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'drivers_license',
            'document_number' => 'MGR-BRANCH1',
            'file'            => $file1,
            'attestation'     => true,
        ]);
        $res1->assertStatus(201);

        // Different branch customer (branch2)
        $file2 = UploadedFile::fake()->create('doc2.png', 400, 'image/png');
        $res2 = $this->postJson("/api/customers/{$this->customer2->id}/kyc-documents", [
            'document_type'   => 'drivers_license',
            'document_number' => 'MGR-BRANCH2',
            'file'            => $file2,
            'attestation'     => true,
        ]);
        $res2->assertStatus(403);
    }

    public function test_authorized_users_can_list_and_download_kyc_document(): void
    {
        // Upload a document as admin
        Sanctum::actingAs($this->admin);
        $file = UploadedFile::fake()->create('test_contract.pdf', 300, 'application/pdf');
        $res = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'utility_bill',
            'document_number' => 'UTIL-90210',
            'file'            => $file,
            'attestation'     => true,
        ]);
        $res->assertStatus(201);
        $docId = $res->json('data.id');

        // CSR in same branch can list
        Sanctum::actingAs($this->csr);
        $listRes = $this->getJson("/api/customers/{$this->customer1->id}/kyc-documents");
        $listRes->assertStatus(200)
            ->assertJsonPath('data.0.document_number', 'UTIL-90210');

        // CSR in same branch can download
        $dlRes = $this->get("/api/kyc-documents/{$docId}/download");
        $dlRes->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename=test_contract.pdf');

        // Manager / CSR for another branch cannot view or download
        $otherCsr = User::factory()->create(['role' => 'csr', 'status' => 'active', 'branch_id' => $this->branch2->id]);
        Sanctum::actingAs($otherCsr);

        $otherListRes = $this->getJson("/api/customers/{$this->customer1->id}/kyc-documents");
        $otherListRes->assertStatus(403);

        $otherDlRes = $this->get("/api/kyc-documents/{$docId}/download");
        $otherDlRes->assertStatus(403);
    }

    public function test_only_admin_can_delete_kyc_document(): void
    {
        Sanctum::actingAs($this->compliance);
        $file = UploadedFile::fake()->create('delete_me.pdf', 200, 'application/pdf');
        $res = $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'tax_certificate',
            'document_number' => 'TAX-DEL',
            'file'            => $file,
            'attestation'     => true,
        ]);
        $docId = $res->json('data.id');
        $doc = KycDocument::findOrFail($docId);

        // Compliance attempts delete -> 403
        $delRes1 = $this->deleteJson("/api/kyc-documents/{$docId}");
        $delRes1->assertStatus(403);

        // CSR attempts delete -> 403
        Sanctum::actingAs($this->csr);
        $delRes2 = $this->deleteJson("/api/kyc-documents/{$docId}");
        $delRes2->assertStatus(403);

        // Admin deletes -> 200 and file removed from disk
        Sanctum::actingAs($this->admin);
        $delRes3 = $this->deleteJson("/api/kyc-documents/{$docId}");
        $delRes3->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('kyc_documents', ['id' => $docId]);
        Storage::disk('private')->assertMissing($doc->file_path);
    }

    public function test_customer_list_includes_document_count(): void
    {
        Sanctum::actingAs($this->admin);

        // Create 2 documents for customer1
        $file1 = UploadedFile::fake()->create('doc1.png', 100, 'image/png');
        $file2 = UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf');

        $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'passport',
            'document_number' => 'P1',
            'file'            => $file1,
            'attestation'     => true,
        ]);
        $this->postJson("/api/customers/{$this->customer1->id}/kyc-documents", [
            'document_type'   => 'national_id',
            'document_number' => 'N1',
            'file'            => $file2,
            'attestation'     => true,
        ]);

        $response = $this->getJson('/api/customers');
        $response->assertStatus(200);

        $cust = collect($response->json('data'))->firstWhere('id', $this->customer1->id);
        $this->assertNotNull($cust);
        $this->assertEquals(2, $cust['document_count']);
    }
}
