<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'], // 10MB max per file
            'is_internal' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasMessage = $this->filled('message') && trim($this->input('message')) !== '';
            $hasFiles = $this->hasFile('files') && count($this->file('files')) > 0;
            
            if (!$hasMessage && !$hasFiles) {
                $validator->errors()->add('message', 'Either a message or files must be provided.');
            }
        });
    }
}
