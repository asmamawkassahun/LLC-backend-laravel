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
        $companies = Company::with(['user', 'country', 'state', 'owners', 'addresses'])
            ->latest()
            ->paginate(20);

        return response()->json(CompanyResource::collection($companies));
    }

    public function show($id): JsonResponse
    {
        $company = Company::with(['user', 'country', 'state', 'owners', 'addresses'])
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

        return response()->json(new CompanyResource($company->fresh()));
    }

    public function approve($id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->companyFormationService->completeFormation($company);

        return response()->json(new CompanyResource($company->fresh()));
    }
}
