<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\ProductCategory;

class LabelUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'product_id'       => ['sometimes','integer','exists:products,id'],
            'client_id'        => ['sometimes','integer','exists:clients,id'],
            'article'          => ['sometimes','string','max:255'],
            'composition'      => ['sometimes','string'],
            'color'            => ['sometimes','string','max:100'],
            'has_chestny_znak' => ['sometimes','boolean'],
            'category'         => ['sometimes', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
        ];
    }
}

