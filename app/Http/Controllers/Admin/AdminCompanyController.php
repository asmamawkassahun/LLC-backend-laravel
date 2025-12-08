<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyFormationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanyController extends Controller
{
    public function __construct(protected CompanyFormationService $companyFormationService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100
        
        $query = Company::with(['country', 'state', 'owners', 'addresses', 'service']);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $companies = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => CompanyResource::collection($companies->items()),
            'current_page' => $companies->currentPage(),
            'last_page' => $companies->lastPage(),
            'per_page' => $companies->perPage(),
            'total' => $companies->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $company = Company::with(['user', 'country', 'state', 'owners', 'addresses', 'service'])
            ->findOrFail($id);

        return response()->json(new CompanyResource($company));
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,processing,formed,rejected',
        ]);

        $company = Company::findOrFail($id);
        $company->update(['status' => \App\Enums\CompanyStatus::from($request->status)]);

        if ($request->status === 'formed') {
            $this->companyFormationService->completeFormation($company, $request->only(['registration_number', 'ein']));
        }

        return response()->json(new CompanyResource($company->fresh()->load('service')));
    }

    public function approve($id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->companyFormationService->completeFormation($company);

        return response()->json(new CompanyResource($company->fresh()->load('service')));
    }
}
