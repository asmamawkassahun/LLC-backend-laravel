<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyFormationService;
use App\Services\OrderPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\CompanyStatus;

class AdminCompanyController extends Controller
{
    public function __construct(
        protected CompanyFormationService $companyFormationService,
        protected OrderPdfService $orderPdfService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100); // Limit between 1 and 100
        
        $query = Company::with(['country', 'state', 'owners', 'addresses'])->where('status', CompanyStatus::FORMED->value);
        
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

    public function uploadFile(Request $request, $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        $company = Company::findOrFail($id);
        
        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('companies/' . $company->id, $fileName, 'public');

        return response()->json([
            'message' => 'File uploaded successfully',
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_url' => asset('storage/' . $filePath),
        ]);
    }

    public function downloadSummary($id): Response
    {
        $company = Company::with(['order', 'order.country', 'order.pricingPlan', 'order.company', 'order.company.owners', 'order.company.addresses', 'order.state', 'order.payments'])
            ->findOrFail($id);

        if (!$company->order) {
            return response()->json([
                'message' => 'Company does not have an associated order'
            ], 404);
        }

        try {
            $pdf = $this->orderPdfService->generateOrderSummaryPdf($company->order);
            
            $filename = 'company-summary-' . $company->name . '-' . $company->id . '.pdf';
            
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
