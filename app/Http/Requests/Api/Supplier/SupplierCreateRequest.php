<?php

namespace App\Http\Requests\Api\Supplier;

use App\Enums\PaymentType;
use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Http\Requests\OnlyAllowedKeys;

class SupplierCreateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('create-suppliers');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:suppliers',
            'address' => 'string',
            'website' => 'url',


            'payments' => 'sometimes|array',
            'payments.*' => [
                'array',
                new OnlyAllowedKeys(['type', 'details']),
            ],
            'payments.*.type' => ['required', new Enum(PaymentType::class)],
            'payments.*.details' => '',


            'contacts' => 'sometimes|array',
            'contacts.*' => [
                'array',
                new OnlyAllowedKeys(['name', 'position', 'phone', 'email']),
            ],
            'contacts.*.name' => 'required|string',
            'contacts.*.position' => '',
            'contacts.*.phone' => '',
            'contacts.*.email' => '',


            'tags.*' => "exists:tags,id"
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Это поле обязательно для заполнения.',
            'string' => 'Значение должно быть строкой.',
            'unique' => 'Это значение уже занято.',
            'exists' => 'Выбранное значение некорректно.',
            'in' => 'Выбранное значение недопустимо.',
        ];
    }
}
