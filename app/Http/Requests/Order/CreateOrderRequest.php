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
            'country_id' => ['required_if:type,company_formation', 'exists:countries,id'],
            'pricing_plan_id' => ['required_if:type,company_formation', 'exists:pricing_plans,id'],
            'state_id' => ['nullable', 'exists:states,id'],
            'company_name' => ['required_if:type,company_formation', 'string', 'max:255'],
            'company_type' => ['required_if:type,company_formation', 'in:LLC,LTD,CORP'],
            'promo_code' => ['nullable', 'string', 'exists:promo_codes,code'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
