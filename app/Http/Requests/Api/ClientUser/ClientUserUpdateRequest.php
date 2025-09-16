<?php

namespace App\Http\Requests\Api\ClientUser;

use Illuminate\Foundation\Http\FormRequest;

class ClientUserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'exists:roles,id'],
        ];
    }
}