<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePdfFromFormDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'array', 'min:1'],
            'plan.0.countryName' => ['required', 'string', 'max:255'],
            'plan.0.pricingPlan' => ['required', 'string', 'max:255'],
            'plan.0.basePrice' => ['nullable', 'numeric', 'min:0'],
            'plan.0.yearlyPrice' => ['nullable', 'numeric', 'min:0'],
            'companyName' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:LLC,LTD,CORP'],
            'category' => ['nullable', 'array'],
            'category.*' => ['string', 'max:255'],
            'state' => ['nullable', 'array'],
            'state.name' => ['nullable', 'string', 'max:255'],
            'state.cost' => ['nullable', 'numeric', 'min:0'],
            'owners' => ['required', 'array', 'min:1'],
            'owners.*.fullName' => ['required', 'string', 'max:255'],
            'owners.*.ownershipPercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'owners.*.isCompany' => ['nullable', 'boolean'],
            'address' => ['required', 'array'],
            'address.streetAddress' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.state' => ['required', 'string', 'max:255'],
            'address.zipCode' => ['required', 'string', 'max:255'],
            'address.country' => ['required', 'string', 'max:255'],
        ];
    }
}

