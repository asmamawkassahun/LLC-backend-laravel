<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@bekurtechnologies.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
