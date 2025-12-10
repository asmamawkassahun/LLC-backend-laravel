<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Query companies through orders since companies no longer have user_id
        $companyIds = $request->user()->orders()
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique();

        $companies = Company::whereIn('id', $companyIds)
            ->with(['country', 'state', 'owners', 'addresses'])
            ->latest()
            ->paginate(15);

        return response()->json(CompanyResource::collection($companies));
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        // Create company without user relationship
        $company = Company::create($request->validated());

        $ownerIds = [];
        if ($request->has('owners')) {
            foreach ($request->owners as $ownerData) {
                $owner = $company->owners()->create($ownerData);
                $ownerIds[] = $owner->id;
            }
            // Update company with owner IDs
            $company->update(['company_owner_ids' => $ownerIds]);
        }

        if ($request->has('addresses')) {
            foreach ($request->addresses as $addressData) {
                $company->addresses()->create($addressData);
            }
        }

        return response()->json(new CompanyResource($company->load(['country', 'state', 'owners', 'addresses'])), 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        // Verify company belongs to user through orders
        $companyIds = $request->user()->orders()
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique();

        $company = Company::whereIn('id', $companyIds)
            ->with(['country', 'state', 'owners', 'addresses'])
            ->findOrFail($id);

        return response()->json(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, $id): JsonResponse
    {
        // Verify company belongs to user through orders
        $companyIds = $request->user()->orders()
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique();

        $company = Company::whereIn('id', $companyIds)->findOrFail($id);
        $company->update($request->validated());

        // Update owner IDs if owners were updated
        if ($request->has('owners')) {
            $company->owners()->delete();
            $ownerIds = [];
            foreach ($request->owners as $ownerData) {
                $owner = $company->owners()->create($ownerData);
                $ownerIds[] = $owner->id;
            }
            $company->update(['company_owner_ids' => $ownerIds]);
        }

        return response()->json(new CompanyResource($company->load(['country', 'state', 'owners', 'addresses'])));
    }

    public function setAsPrimary(Request $request, $id): JsonResponse
    {
        // Verify company belongs to user through orders
        $companyIds = $request->user()->orders()
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->unique();

        $company = Company::whereIn('id', $companyIds)->findOrFail($id);

        // Set all other companies for this user to is_primary = false
        Company::whereIn('id', $companyIds)
            ->where('id', '!=', $id)
            ->update(['is_primary' => false]);

        // Set this company as primary
        $company->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Company set as primary successfully',
            'company' => new CompanyResource($company->load(['country', 'state', 'owners', 'addresses'])),
        ]);
    }
}
