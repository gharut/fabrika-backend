<?php

namespace App\Http\Requests\Api\Orders;

use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use App\Rules\ProductIdExistsInProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OrderCreateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('create-orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'client' => 'required|exists:clients,id',

            'pickup' => 'required',
            'pickup.supply_date' => 'required',
            'pickup.supply_time' => 'required',


            'pickup.products' => 'required|array',
            'pickup.products.*.name' => 'required',
            'pickup.products.*.color' => 'required',
            'pickup.products.*.size' => 'required|array',
            'pickup.products.*.size.*.size' => 'sometimes',
            'pickup.products.*.size.*.qty' => 'required|numeric',

            'pickup.products.*.child' => 'sometimes|array',
            'pickup.products.*.child.*.color' => 'required',
            'pickup.products.*.child.*.size' => 'required|array',
            'pickup.products.*.child.*.size.*.size' => 'sometimes',
            'pickup.products.*.child.*.size.*.qty' => 'required|numeric',


            'pickup.supply_items' => 'sometimes|required',
            'pickup.supply_items.*.qty' => 'required',
            'pickup.supply_items.*.width' => 'required',
            'pickup.supply_items.*.length' => 'required',
            'pickup.supply_items.*.height' => 'required',
            'pickup.supply_items.*.unit' => 'required',
            'pickup.supply_items.*.weight' => 'required',

            'pickup.services' => 'required|array',
            'pickup.services.*.service_id' => 'required',
            'pickup.services.*.service_enabled' => 'required|boolean',
            'pickup.services.*.service_attribute' => '',

            'packaging' => 'required|array',
            'packaging.*.complect' => 'required|numeric',
            'packaging.*.product_id' => ['required', new ProductIdExistsInProducts()],
            'packaging.*.services' => 'required|array',
            'packaging.*.services.*.service_id' => 'required',
            'packaging.*.services.*.service_enabled' => 'required|boolean',
            'packaging.*.services.*.service_attribute' => '',

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
