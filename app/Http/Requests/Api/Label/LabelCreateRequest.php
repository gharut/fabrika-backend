<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\ProductCategory;

class LabelCreateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'product_id'       => ['required','integer','exists:products,id'],
            'client_id'        => ['required','integer','exists:clients,id'],
            'article'          => ['required','string','max:255'],
            'composition'      => ['required','string'],
            'color'            => ['required','string','max:100'],
            'has_chestny_znak' => ['required','boolean'],
            'category'         => ['required', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
        ];
    }
}