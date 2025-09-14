<?php

namespace App\Http\Requests\Api\MarketplaceAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MarketplaceAccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform'       => ['sometimes', new Enum(Marketplace::class)],
            'name'           => ['sometimes', 'string', 'max:150'],
            'api_token_enc'  => ['sometimes', 'string'],
            'status'         => ['sometimes', new Enum(MarketplaceAccountStatus::class), 'max:50'],
            'error_message'  => ['sometimes', 'string', 'nullable'],
            'last_checked_at'=> ['sometimes', 'date'],
        ];
    }
}
