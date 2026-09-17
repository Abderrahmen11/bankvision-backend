<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KycDocument\StoreKycDocumentRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\KycDocumentResource;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycDocumentController extends Controller
{
    /**
     * List all KYC documents for a customer.
     * GET /api/customers/{customer}/kyc-documents
     */
    public function index(Request $request, string|int $customer): AnonymousResourceCollection
    {
        $customerModel = Customer::findOrFail($customer);
        Gate::authorize('viewAny', [KycDocument::class, $customerModel]);

        $documents = KycDocument::where('customer_id', $customerModel->id)
            ->with(['uploadedBy', 'customer'])
            ->latest('uploaded_at')
            ->get();

        return KycDocumentResource::collection($documents);
    }

    /**
     * Upload and verify a new KYC document.
     * POST /api/customers/{customer}/kyc-documents
     */
    public function store(StoreKycDocumentRequest $request, string|int $customer): JsonResponse
    {
        $customerModel = Customer::findOrFail($customer);
        Gate::authorize('create', [KycDocument::class, $customerModel]);

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $storedFileName = "{$uuid}.{$ext}";
        $relativeDirectory = "kyc/{$customerModel->id}";
        $filePath = "{$relativeDirectory}/{$storedFileName}";

        // Persist file to the private disk: storage/app/private/kyc/{customer_id}/{uuid}.{ext}
        Storage::disk('private')->putFileAs($relativeDirectory, $file, $storedFileName);

        $user = $request->user();

        try {
            $document = DB::transaction(function () use ($request, $customerModel, $user, $filePath, $file) {
                $doc = KycDocument::create([
                    'customer_id'     => $customerModel->id,
                    'document_type'   => $request->input('document_type'),
                    'document_number' => $request->input('document_number'),
                    'issuing_country' => $request->input('issuing_country'),
                    'expiry_date'     => $request->input('expiry_date'),
                    'file_path'       => $filePath,
                    'file_name'       => $file->getClientOriginalName(),
                    'file_size'       => $file->getSize(),
                    'mime_type'       => $file->getClientMimeType() ?: $file->getMimeType(),
                    'uploaded_by'     => $user?->id,
                    'uploaded_at'     => now(),
                    'verified_at'     => now(),
                    'status'          => 'verified',
                    'notes'           => $request->input('notes'),
                ]);

                // Update customer status to verified
                $customerModel->update(['kyc_status' => 'verified']);

                // Record audit log entry
                AuditService::log(
                    user: $user,
                    action: 'kyc.document.uploaded',
                    tableName: 'customers',
                    recordId: $customerModel->id,
                    oldValues: [],
                    newValues: [
                        'document_id'   => $doc->id,
                        'document_type' => $doc->document_type,
                    ],
                );

                return $doc;
            });
        } catch (\Throwable $e) {
            // Cleanup uploaded file if transaction fails
            Storage::disk('private')->delete($filePath);
            throw $e;
        }

        return response()->json([
            'success'  => true,
            'message'  => 'KYC document uploaded and verified successfully.',
            'data'     => KycDocumentResource::make($document->load('uploadedBy')),
            'customer' => CustomerResource::make(
                $customerModel->fresh(['branch', 'relationshipManager'])->loadCount('kycDocuments as document_count')
            ),
        ], 201);
    }

    /**
     * Get single KYC document metadata.
     * GET /api/kyc-documents/{id}
     */
    public function show(Request $request, string|int $id): KycDocumentResource
    {
        $document = KycDocument::with(['uploadedBy', 'customer'])->findOrFail($id);
        Gate::authorize('view', $document);

        return KycDocumentResource::make($document);
    }

    /**
     * Stream download the KYC document file with authentication check.
     * GET /api/kyc-documents/{id}/download
     */
    public function download(Request $request, string|int $id): StreamedResponse
    {
        $document = KycDocument::with('customer')->findOrFail($id);
        Gate::authorize('download', $document);

        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'KYC document file not found on private storage.');
        }

        return Storage::disk('private')->download(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => $document->mime_type,
            ]
        );
    }

    /**
     * Delete a KYC document (Admin only).
     * DELETE /api/kyc-documents/{id}
     */
    public function destroy(Request $request, string|int $id): JsonResponse
    {
        $document = KycDocument::with('customer')->findOrFail($id);
        Gate::authorize('delete', $document);

        // Delete physical file from private disk
        Storage::disk('private')->delete($document->file_path);

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'KYC document deleted successfully.',
        ]);
    }
}
