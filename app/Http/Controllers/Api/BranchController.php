<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(
        protected BranchService $branchService
    ) {}

    /**
     * List all branches with search and filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = $this->branchService->getPaginatedBranches(
            $request->only(['search', 'status', 'city'])
        );

        return BranchResource::collection($branches);
    }

    /**
     * Show a single branch with manager.
     */
    public function show(string $id): BranchResource
    {
        $branch = $this->branchService->getBranchDetails($id);

        return BranchResource::make($branch);
    }

    /**
     * Create a new branch.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branchService->createBranch($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data'    => BranchResource::make($branch),
        ], 201);
    }

    /**
     * Update branch details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'branch_name' => ['sometimes', 'string', 'max:255'],
            'address'     => ['nullable', 'string'],
            'city'        => ['nullable', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'status'      => ['sometimes', 'in:active,inactive,under_renovation'],
            'manager_id'  => ['nullable', 'exists:users,id'],
        ]);

        $updated = $this->branchService->updateBranch($branch, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully.',
            'data'    => BranchResource::make($updated),
        ]);
    }

    /**
     * Delete a branch (only if no users or customers are linked).
     */
    public function destroy(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $this->branchService->deleteBranch($branch);

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
