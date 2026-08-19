<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    /**
     * List all branches with optional search.
     * Filters: status, city
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = Branch::query()
            ->with('manager')
            ->withCount('users as total_employees')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->city, fn($q) => $q->where('city', 'like', "%{$request->city}%"))
            ->when(
                $request->search,
                fn($q) =>
                $q->where(
                    fn($q) =>
                    $q->where('branch_name', 'like', "%{$request->search}%")
                        ->orWhere('branch_code', 'like', "%{$request->search}%")
                )
            )
            ->latest()
            ->paginate(15);

        return BranchResource::collection($branches);
    }

    /**
     * Show a single branch with manager and employee/customer counts.
     */
    public function show(string $id): BranchResource
    {
        $branch = Branch::with('manager')
            ->withCount('users as total_employees')
            ->findOrFail($id);

        return BranchResource::make($branch);
    }

    /**
     * Create a new branch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_code' => ['required', 'string', 'unique:branches,branch_code'],
            'branch_name' => ['required', 'string', 'max:255'],
            'address'     => ['nullable', 'string'],
            'city'        => ['nullable', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'status'      => ['sometimes', 'in:active,inactive,under_renovation'],
            'manager_id'  => ['nullable', 'exists:users,id'],
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data'    => BranchResource::make($branch->load('manager')),
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

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully.',
            'data' => BranchResource::make($branch->fresh('manager')),
        ]);
    }

    /**
     * Delete a branch (only if no users or customers are linked).
     */
    public function destroy(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        if ($branch->users()->exists() || $branch->customers()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a branch that has assigned employees or customers.',
            ], 422);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
