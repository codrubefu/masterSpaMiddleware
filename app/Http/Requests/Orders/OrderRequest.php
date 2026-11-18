<?php

namespace App\Http\Requests\Orders;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Common rules for WooCommerce order webhooks.
 * Always answers with JSON (WooCommerce doesn't send Accept: application/json,
 * so the default would be a redirect).
 */
abstract class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is checked by the api.secret middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'total' => ['required', 'numeric', 'min:0'],
            'billing' => ['required', 'array'],
            'billing.first_name' => ['required', 'string'],
            'billing.last_name' => ['required', 'string'],
            'billing.email' => ['required', 'email'],
            'billing.phone' => ['required', 'string'],
            'meta_data' => ['sometimes', 'array'],
            'meta_data.*.key' => ['required_with:meta_data', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Invalid order data.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
