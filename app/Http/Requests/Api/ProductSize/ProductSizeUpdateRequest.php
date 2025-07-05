<?php

namespace App\Http\Requests\Api\ProductSize;

use Illuminate\Foundation\Http\FormRequest;

class ProductSizeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'exists:products,id'],
            'value' => ['sometimes', 'string', 'max:255'],
            'barcode' => ['sometimes', 'string', 'max:255'],
        ];
    }
}