<?php

namespace App\Http\Requests\Api\ProductSize;

use Illuminate\Foundation\Http\FormRequest;

class ProductSizeCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:wb_products,id'],
            'value' => ['required', 'string', 'max:255'],
            'barcode' => ['required', 'string', 'max:255'],
        ];
    }
}
