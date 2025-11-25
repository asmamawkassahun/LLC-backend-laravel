<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
        ];
    }
}
