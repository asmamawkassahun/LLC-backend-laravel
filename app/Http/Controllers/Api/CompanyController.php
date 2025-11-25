<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = $request->user()->companies()
            ->with(['country', 'state', 'owners', 'addresses'])
            ->latest()
            ->paginate(15);

        return response()->json(CompanyResource::collection($companies));
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        $company = $request->user()->companies()->create($request->validated());

        if ($request->has('owners')) {
            foreach ($request->owners as $ownerData) {
                $company->owners()->create($ownerData);
            }
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
        $company = $request->user()->companies()
            ->with(['country', 'state', 'owners', 'addresses'])
            ->findOrFail($id);

        return response()->json(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, $id): JsonResponse
    {
        $company = $request->user()->companies()->findOrFail($id);
        $company->update($request->validated());

        return response()->json(new CompanyResource($company->load(['country', 'state', 'owners', 'addresses'])));
    }
}
