<?php

namespace App\Http\Requests\Api\Printer;

use Illuminate\Foundation\Http\FormRequest;

class PrinterCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'labels_count' => ['required', 'integer', 'min:0'],
            'capacity' => ['required', 'integer', 'min:0'],
            'warning_threshold' => ['required', 'integer', 'min:0'],
        ];
    }
}
