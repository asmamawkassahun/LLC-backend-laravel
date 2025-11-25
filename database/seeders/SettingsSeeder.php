<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'Privatily', 'type' => 'string', 'description' => 'Site name'],
            ['key' => 'support_email', 'value' => 'support@privatily.com', 'type' => 'string', 'description' => 'Support email address'],
            ['key' => 'default_commission_rate', 'value' => '10.00', 'type' => 'decimal', 'description' => 'Default affiliate commission rate'],
            ['key' => 'order_processing_time_hours', 'value' => '24', 'type' => 'integer', 'description' => 'Order processing time in hours'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
