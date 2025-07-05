<?php

namespace App\Http\Requests\Api\Printer;

use Illuminate\Foundation\Http\FormRequest;

class PrinterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'labels_count' => ['required', 'integer', 'min:0'],
            'warning_threshold' => ['required', 'integer', 'min:0'],
        ];
    }
}
