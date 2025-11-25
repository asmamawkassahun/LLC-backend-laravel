<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:draft,pending_payment,paid,processing,completed,cancelled,refunded'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
