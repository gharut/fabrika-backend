<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelCreateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required','integer','exists:wb_products,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'print_single_ean13' => ['nullable', 'boolean'],
            'print_double_ean13' => ['nullable', 'boolean'],
            'duplicate_chz' => ['nullable', 'boolean'],
        ];
    }
}