<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\EligibleRelationshipManagersRequest;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * List all users with search, filters, sorting, and pagination.
     */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $users = $this->userService->getPaginatedUsers(
            $request->validated(),
            user: $request->user()
        );

        return UserResource::collection($users);
    }

    /**
     * Return active CSR / Manager users for a given branch (for RM assignment).
     * Admin: any branch. Manager & CSR: own branch only.
     */
    public function eligibleRelationshipManagers(EligibleRelationshipManagersRequest $request): JsonResponse
    {
        $branchId       = (int) $request->validated()['branch_id'];
        $requestingUser = $request->user();

        // Branch-scoped roles may only query their own branch
        $branchScopedRoles = ['manager', 'csr'];
        if (
            in_array($requestingUser->role, $branchScopedRoles, true)
            && (int) $requestingUser->branch_id !== $branchId
        ) {
            throw new AccessDeniedHttpException('You may only query eligible managers for your own branch.');
        }

        $managers = $this->userService->getEligibleRelationshipManagers($branchId);

        return response()->json(['data' => $managers]);
    }

    /**
     * Show a single user with branch.
     */
    public function show(Request $request, string $id): UserResource
    {
        $user = $this->userService->getUserDetails($id, $request->user());

        return UserResource::make($user);
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data'    => UserResource::make($user),
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $updated = $this->userService->updateUser($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => UserResource::make($updated),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->userService->deleteUser($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}
