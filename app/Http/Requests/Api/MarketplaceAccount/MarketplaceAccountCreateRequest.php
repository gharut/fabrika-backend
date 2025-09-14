<?php

namespace App\Http\Requests\Api\MarketplaceAccount;

use App\Enums\Marketplace;
use App\Enums\MarketplaceAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MarketplaceAccountCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform'       => ['required', new Enum(Marketplace::class)],
            'name'           => ['required', 'string', 'max:150'],
            'api_token_enc'  => ['required', 'string'],
            'status'         => ['sometimes', new Enum(MarketplaceAccountStatus::class), 'max:50'],
            'error_message'  => ['sometimes', 'string'],
        ];
    }
}
