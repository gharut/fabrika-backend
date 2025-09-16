<?php

namespace App\Http\Requests\Api\ClientUser;

use Illuminate\Foundation\Http\FormRequest;

class ClientUserCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'user_id'   => ['required', 'exists:users,id'],
            'role'      => ['required', 'exists:roles,id'],
        ];
    }
}