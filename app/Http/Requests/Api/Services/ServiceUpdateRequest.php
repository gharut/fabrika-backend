<?php

namespace App\Http\Requests\Api\Services;

use App\Enums\PaymentType;
use App\Enums\ServiceApplyTo;
use App\Enums\ServiceAttributeTypes;
use App\Enums\ServiceReportTypes;
use App\Enums\ServiceSteps;
use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use App\Http\Requests\OnlyAllowedKeys;
use Illuminate\Validation\Rules\In;

class ServiceUpdateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('create-services');
    }



    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:services,name,'.$this->service->id.',id,deleted_at,NULL',
            'use_consumable' => 'nullable|boolean',
            'step' => ['required', new Enum(ServiceSteps::class)],
            'apply_to' => ['required', new Enum(ServiceApplyTo::class)],
            'multiple_products' => 'nullable|numeric',
            'count_label' => 'required_if:apply_to,PRODUCT_COUNT',
            'report_type' => ['required', new Enum(ServiceReportTypes::class)],
            'price' => 'required|numeric',

            'attributes' => 'sometimes|array',
            'attributes.*.name' => 'required',
            'attributes.*.attribute_type' => ['required', new Enum(ServiceAttributeTypes::class)],
            'attributes.*.allow_multiselect' => 'nullable|boolean',
            'attributes.*.apply_to_price_type' => 'nullable|numeric|min:0|max:3',
            'attributes.*.attribute_data' => 'sometimes|array',
            'attributes.*.attribute_data.*' => [
                'array',
                new OnlyAllowedKeys(['value', 'price']),
            ],
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
        ];
    }
}
