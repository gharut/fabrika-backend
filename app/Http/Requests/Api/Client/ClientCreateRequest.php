<?php

namespace App\Http\Requests\Api\Client;

use App\Enums\ClientTypes;
use App\Enums\PaymentType;
use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Http\Requests\OnlyAllowedKeys;

class ClientCreateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('create-clients');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:clients',
            'type' => ['required', new Enum(ClientTypes::class)],
            'phone' => '',
            'email' => '',
            'details' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'error_required',
            'string' => 'error_string',
            'unique'  => 'error_unique',
            'exist'  => 'error_exist',
            'in'  => 'fdfdf',
        ];
    }
}
