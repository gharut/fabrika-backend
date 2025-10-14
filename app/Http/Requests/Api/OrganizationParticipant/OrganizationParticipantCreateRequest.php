<?php

namespace App\Http\Requests\Api\OrganizationParticipant;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationParticipantCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'exists:clients,id'],
            'model_id'   => ['required'],
            'model_type'   => ['required'],
            'role_id'      => ['required', 'exists:roles,id'],
        ];
    }
}