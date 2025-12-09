<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class AdminCountryController extends Controller
{
    public function index(): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(CountryResource::collection($countries));
    }
}

