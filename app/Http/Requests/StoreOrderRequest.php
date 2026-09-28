<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string','max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
