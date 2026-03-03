<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $availablePlans = implode(',', array_keys(config('recoverflow.plans', [])));

        return [
            'plan' => ['required', 'string', "in:{$availablePlans}"],
            'success_url' => ['nullable', 'url', 'max:255'],
            'cancel_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.in' => 'Selected plan is not available for checkout.',
            'success_url.url' => 'Success URL must be a valid URL.',
            'cancel_url.url' => 'Cancel URL must be a valid URL.',
        ];
    }
}
