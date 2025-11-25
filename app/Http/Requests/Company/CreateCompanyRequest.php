<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:LLC,LTD,CORP'],
            'country_id' => ['required', 'exists:countries,id'],
            'state_id' => ['nullable', 'exists:states,id'],
            'owners' => ['required', 'array', 'min:1'],
            'owners.*.full_name' => ['required', 'string', 'max:255'],
            'owners.*.ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'addresses' => ['nullable', 'array'],
        ];
    }
}
