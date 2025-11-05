<?php

namespace App\Http\Requests\Api\Client;

use App\Enums\PaymentType;
use App\Http\Requests\FailedValidationTrait;
use App\Http\Requests\OnlyAllowedKeys;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ClientUpdateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('edit-clients');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:suppliers,name,'.$this->supplier->id.',id',
            'short_name' => 'nullable|string|max:100',
            'short_address' => 'nullable|string|max:255',
            'address' => 'string',
            'website' => 'url',


            'payments' => 'sometimes|array',
            'payments.*' => [
                'array',
                new OnlyAllowedKeys(['type', 'details']),
            ],
            'payments.*.type' => ['required', new Enum(PaymentType::class)],
            'payments.*.details' => 'string',


            'contacts' => 'sometimes|array',
            'contacts.*' => [
                'array',
                new OnlyAllowedKeys(['name', 'position', 'phone', 'email']),
            ],
            'contacts.*.name' => 'required|string',
            'contacts.*.position' => 'string',
            'contacts.*.phone' => 'string',
            'contacts.*.email' => 'string',


            'tags.*' => "exists:tags,id"
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
