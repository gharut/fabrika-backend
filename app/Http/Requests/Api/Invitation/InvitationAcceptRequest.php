<?php

namespace App\Http\Requests\Api\Invitation;

use Illuminate\Foundation\Http\FormRequest;

class InvitationAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required','string','size:64'],
            'name' => ['nullable','string','max:100'],
            'password' => ['nullable','string','min:8','max:100'],
        ];
    }
}
