<?php

namespace App\Http\Requests\Api\Promo;

use Illuminate\Foundation\Http\FormRequest;

class PromoStartRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'externalId' => ['nullable','string','max:100'],
            'wbToken'    => ['required','string','max:1000'],
            'apiToken'   => ['required','string','max:1000'],
            'products'   => ['required','array','min:1'],
            'products.*.article'  => ['required','string','max:50'],
            'products.*.discount' => ['required','integer','between:0,99'],
        ];
    }
}
