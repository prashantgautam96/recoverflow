<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkInvoicePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.date' => 'Paid at must be a valid date.',
        ];
    }
}
