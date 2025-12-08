<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->keyBy('key');

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.type' => 'sometimes|string|in:string,integer,boolean,decimal,json',
            'settings.*.description' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $setting) {
            Setting::set(
                $setting['key'],
                $setting['value'],
                $setting['type'] ?? 'string',
                $setting['description'] ?? null
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function get($key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return response()->json($setting);
    }

    public function set(Request $request, $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
            'type' => 'sometimes|string|in:string,integer,boolean,decimal,json',
            'description' => 'nullable|string',
        ]);

        Setting::set(
            $key,
            $validated['value'],
            $validated['type'] ?? 'string',
            $validated['description'] ?? null
        );

        return response()->json(['message' => 'Setting updated successfully']);
    }
}
