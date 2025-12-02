<?php

namespace Database\Seeders;

use App\Models\registeredAgentAddress;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegisteredAgentAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addresses = [
            [
                'address' => '123 Business Street, Suite 100',
                'city' => 'Wilmington',
                'state' => 'Delaware',
                'postal_code' => '19801',
                'country' => 'United States',
                'is_active' => true,
            ],
            [
                'address' => '456 Corporate Boulevard, Floor 5',
                'city' => 'Carson City',
                'state' => 'Nevada',
                'postal_code' => '89701',
                'country' => 'United States',
                'is_active' => false,
            ],
            [
                'address' => '789 Enterprise Avenue, Office 200',
                'city' => 'Cheyenne',
                'state' => 'Wyoming',
                'postal_code' => '82001',
                'country' => 'United States',
                'is_active' => false,
            ],
        ];

        foreach ($addresses as $address) {
            registeredAgentAddress::updateOrCreate(
                [
                    'address' => $address['address'],
                    'city' => $address['city'],
                    'state' => $address['state'],
                ],
                $address
            );
        }
    }
}
