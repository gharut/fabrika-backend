<?php

namespace App\Http\Requests\Api\Orders;

use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use App\Rules\ProductIdExistsInProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OrderUpdatePackagingRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('edit-orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'products' => 'required|array',
            'products.*.name' => 'required',
            'products.*.color' => 'required',
            'products.*.product_id' => 'sometimes|exists:products,id|nullable',
            'packaging.*.complect' => 'required|numeric',
            'products.*.size' => 'required|array',
            'products.*.size.*.size' => 'sometimes',
            'products.*.size.*.qty' => 'required|numeric',

            'products.*.child' => 'sometimes|array',
            'products.*.child.*.color' => 'required',
            'products.*.child.*.product_id' => 'sometimes|exists:products,id|nullable',
            'products.*.child.*.size' => 'required|array',
            'products.*.child.*.size.*.size' => 'sometimes',
            'products.*.child.*.size.*.qty' => 'required|numeric',

            'products.*.services' => 'required|array',
            'products.*.services.*.record_id' => 'sometimes|exists:order_services,id|nullable',
            'products.*.services.*.service_id' => 'required',
            'products.*.services.*.service_enabled' => 'required|boolean',
            'products.*.services.*.service_attribute' => '',

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
