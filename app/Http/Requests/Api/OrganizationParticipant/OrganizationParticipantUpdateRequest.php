<?php

namespace App\Http\Requests\Api\OrganizationParticipant;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationParticipantUpdateRequest extends FormRequest
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