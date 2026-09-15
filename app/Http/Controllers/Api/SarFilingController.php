<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SarFiling\StoreSarFilingRequest;
use App\Http\Resources\SarFilingResource;
use App\Services\SarFilingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SarFilingController extends Controller
{
    public function __construct(
        protected SarFilingService $sarFilingService
    ) {}

    /**
     * List SAR filings with search and status filters (paginated).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filings = $this->sarFilingService->getPaginatedFilings(
            $request->only(['search', 'status', 'per_page', 'page']),
            caller: $request->user()
        );

        return SarFilingResource::collection($filings);
    }

    /**
     * Record a new Suspicious Activity Report.
     */
    public function store(StoreSarFilingRequest $request): JsonResponse
    {
        $filing = $this->sarFilingService->createFiling(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Suspicious Activity Report {$filing->reference} recorded.",
            'data'    => SarFilingResource::make($filing->load('user')),
        ], 201);
    }
}
