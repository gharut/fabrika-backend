<?php

namespace App\Http\Requests\Api\MarketplaceAccount;

use App\Enums\Marketplace;
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
            'client_id'      => ['required', 'exists:clients,id'],
            'platform'       => ['required', new Enum(Marketplace::class)],
            'name'           => ['required', 'string', 'max:150'],
            'api_token_enc'  => ['required', 'string'],
            'status'         => ['sometimes', 'string', 'max:50'],
            'error_message'  => ['sometimes', 'string'],
        ];
    }
}
