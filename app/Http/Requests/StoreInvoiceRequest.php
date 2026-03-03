<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'invoice_number' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.5'],
            'currency' => ['required', 'string', 'size:3'],
            'issued_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:issued_at'],
            'payment_url' => ['nullable', 'url', 'max:255'],
            'late_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'Client does not exist.',
            'invoice_number.required' => 'Invoice number is required.',
            'amount.min' => 'Invoice amount must be at least 0.5.',
            'due_at.after_or_equal' => 'Due date must be on or after the issue date.',
        ];
    }
}
