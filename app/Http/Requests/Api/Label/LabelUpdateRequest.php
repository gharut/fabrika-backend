<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['sometimes','integer','exists:wb_products,id'],
            'client_name' => ['sometimes', 'string', 'max:255'],
            'printer_id' => ['sometimes', 'nullable', 'integer', 'exists:printers,id'],
            'print_single_ean13' => ['sometimes', 'boolean'],
            'print_double_ean13' => ['sometimes', 'boolean'],
            'duplicate_chz' => ['sometimes', 'boolean'],
        ];
    }
}

