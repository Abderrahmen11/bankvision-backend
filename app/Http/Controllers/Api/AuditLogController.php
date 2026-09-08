<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List audit logs with search, filters, sorting, and role-based scoping.
     */
    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        $logs = $this->auditLogService->getPaginatedLogs(
            $request->validated(),
            user: $request->user()
        );

        return AuditLogResource::collection($logs);
    }

    /**
     * Show a single audit log.
     */
    public function show(\Illuminate\Http\Request $request, string $id): AuditLogResource
    {
        $log = $this->auditLogService->getLogDetails($id, $request->user());

        return AuditLogResource::make($log);
    }
}
