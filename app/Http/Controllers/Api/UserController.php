<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
