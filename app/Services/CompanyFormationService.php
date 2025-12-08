<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyFormationService
{
    public function initiateFormation(Order $order, array $companyData): Company
    {
        return DB::transaction(function () use ($order, $companyData) {
            $company = Company::create([
                'company_owner_ids' => [], // Will be populated after owners are created
                'order_id' => $order->id,
                'name' => $companyData['name'],
                'type' => $companyData['type'],
                'country_id' => $companyData['country_id'],
                'state_id' => $companyData['state_id'] ?? null,
                'status' => CompanyStatus::PENDING,
            ]);
            
            $ownerIds = [];
            if (isset($companyData['owners'])) {
                foreach ($companyData['owners'] as $ownerData) {
                    $owner = $company->owners()->create($ownerData);
                    $ownerIds[] = $owner->id;
                }
                // Update company with owner IDs
                $company->update(['company_owner_ids' => $ownerIds]);
            }
            
            if (isset($companyData['addresses'])) {
                foreach ($companyData['addresses'] as $addressData) {
                    $company->addresses()->create($addressData);
                }
            }
            
            $order->update([
                'company_id' => $company->id,
                'status' => OrderStatus::PROCESSING,
            ]);
            
            return $company;
        });
    }
    
    public function processFormation(Company $company): void
    {
        DB::transaction(function () use ($company) {
            $company->update(['status' => CompanyStatus::PROCESSING]);
            
            // Simulate formation process
            // In real implementation, this would integrate with external services
            Log::info("Processing company formation for company ID: {$company->id}");
            
            // This would typically be done via a queue job
            // For now, we'll just mark it as processing
        });
    }
    
    public function generateEIN(Company $company): ?string
    {
        // In real implementation, this would integrate with IRS API or service
        // For now, return a placeholder
        $ein = '12-' . str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        
        // Store EIN in services table if service exists
        if ($company->service) {
            $company->service->update(['ein' => $ein]);
        }
        
        return $ein;
    }
    
    public function completeFormation(Company $company, array $data = []): void
    {
        DB::transaction(function () use ($company, $data) {
            $company->update([
                'status' => CompanyStatus::FORMED,
                'registration_number' => $data['registration_number'] ?? null,
                'formed_at' => now(),
            ]);
            
            // Update EIN in services table if provided and service exists
            if (isset($data['ein']) && $company->service) {
                $company->service->update(['ein' => $data['ein']]);
            }
            
            if ($company->order) {
                $company->order->update([
                    'status' => OrderStatus::COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        });
    }
}

