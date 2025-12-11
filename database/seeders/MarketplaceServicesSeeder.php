<?php

namespace Database\Seeders;

use App\Models\MarketplaceService;
use Illuminate\Database\Seeder;

class MarketplaceServicesSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'EIN Acquisition',
                'description' => 'Get your Employer Identification Number (EIN) for your US company. This is required for tax purposes and to open a business bank account.',
                'requirements' => [
                    'Company formation documents',
                    'Business name and address',
                    'Social Security Number (SSN) or Individual Taxpayer Identification Number (ITIN)',
                    'Business type and structure',
                ],
                'price' => 99.00,
            ],
            [
                'name' => 'ITIN Application',
                'description' => 'Apply for Individual Taxpayer Identification Number (ITIN). Required for individuals who need a US taxpayer identification number but are not eligible for an SSN.',
                'requirements' => [
                    'Completed W-7 form',
                    'Valid passport or other identification documents',
                    'Proof of foreign status',
                    'Tax return or other supporting documents',
                ],
                'price' => 149.00,
            ],
            [
                'name' => 'UK Limited Company Formation',
                'description' => 'Form a UK Limited Company (Ltd) with Companies House. Includes company registration, certificate of incorporation, and all necessary documentation.',
                'requirements' => [
                    'Company name (must be unique)',
                    'Registered office address in the UK',
                    'At least one director (can be non-UK resident)',
                    'At least one shareholder',
                    'Company secretary (optional)',
                ],
                'price' => 199.00,
            ],
            [
                'name' => 'Business Bank Account Setup Consultation',
                'description' => 'Expert consultation to help you set up a business bank account for your US company. Includes guidance on required documents and bank selection.',
                'requirements' => [
                    'EIN number',
                    'Company formation documents',
                    'Business address',
                    'Owner identification documents',
                ],
                'price' => 79.00,
            ],
            [
                'name' => 'Annual Report Filing (US)',
                'description' => 'File your company\'s annual report with the state. Required to maintain good standing and avoid penalties.',
                'requirements' => [
                    'Company registration number',
                    'Current business address',
                    'Registered agent information',
                    'Officer and director information',
                ],
                'price' => 129.00,
            ],
            [
                'name' => 'Registered Agent Service',
                'description' => 'Professional registered agent service for your US company. Ensures compliance with state requirements and receives important legal documents.',
                'requirements' => [
                    'Company formation documents',
                    'Business address',
                    'Contact information',
                ],
                'price' => 149.00,
            ],
            [
                'name' => 'Tax Consultation',
                'description' => 'One-on-one consultation with a tax professional to understand your tax obligations and optimize your tax strategy.',
                'requirements' => [
                    'Company information',
                    'Previous tax returns (if applicable)',
                    'Business activity details',
                ],
                'price' => 199.00,
            ],
            [
                'name' => 'Accounting Setup Consultation',
                'description' => 'Professional consultation to set up your accounting system and bookkeeping processes for your new company.',
                'requirements' => [
                    'Company formation documents',
                    'Business bank account information',
                    'Expected business activities',
                ],
                'price' => 159.00,
            ],
        ];

        foreach ($services as $service) {
            MarketplaceService::updateOrCreate(
                ['name' => $service['name']],
                array_merge($service, [
                    'is_active' => true,
                ])
            );
        }
    }
}
