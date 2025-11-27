<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:company_formation,marketplace_service'],
            'plan' => ['required_if:type,company_formation', 'array', 'min:1'],
            'plan.0.countryName' => ['required_if:type,company_formation', 'string', 'max:255'],
            'plan.0.pricingPlan' => ['required_if:type,company_formation', 'string', 'max:255'],
            'plan.0.basePrice' => ['required_if:type,company_formation', 'numeric', 'min:0'],
            'plan.0.yearlyPrice' => ['required_if:type,company_formation', 'numeric', 'min:0'],
            'state' => ['nullable', 'array'],
            'state.name' => ['nullable', 'string', 'max:255'],
            'state.cost' => ['nullable', 'numeric', 'min:0'],
            'company_name' => ['required_if:type,company_formation', 'string', 'max:255'],
            'company_type' => ['required_if:type,company_formation', 'in:LLC,LTD,CORP'],
            'category' => ['nullable', 'array'],
            'category.*' => ['string', 'max:255'],
            'owners' => ['nullable', 'array'],
            'owners.*.full_name' => ['required_with:owners', 'string', 'max:255'],
            'owners.*.ownership_percentage' => ['required_with:owners', 'numeric', 'min:0', 'max:100'],
            'owners.*.is_company' => ['nullable', 'boolean'],
            'owners.*.ssn_or_itin' => ['nullable', 'string', 'max:255'],
            'owners.*.email' => ['nullable', 'email', 'max:255'],
            'owners.*.phone' => ['nullable', 'string', 'max:255'],
            'owners.*.address' => ['nullable', 'array'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.type' => ['required_with:addresses', 'string', 'in:registered,mailing,business'],
            'addresses.*.street_address' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.city' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.state' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.zip_code' => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.country' => ['required_with:addresses', 'string', 'max:255'],
            'promo_code' => ['nullable', 'string', 'exists:promo_codes,code'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
