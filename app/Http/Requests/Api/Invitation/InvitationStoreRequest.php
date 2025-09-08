<?php

namespace App\Http\Requests\Api\Invitation;

use Illuminate\Foundation\Http\FormRequest;

class InvitationStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // проверим роль в middleware/policy

    public function rules(): array
    {
        return [
            'email' => ['required','email','max:191'],
            'role_id' => ['required','integer','exists:roles,id'],
            'meta' => ['nullable','array'],
        ];
    }
}