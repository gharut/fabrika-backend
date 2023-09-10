<?php

namespace App\Http\Requests\Api\Orders;

use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use App\Rules\ProductIdExistsInProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class OrderUpdatePickupRequest extends FormRequest
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
            'supply_date' => 'required',
            'supply_time' => 'required',

            'supply_items' => 'sometimes|array',
            'supply_items.*.qty' => 'required',
            'supply_items.*.width' => 'required',
            'supply_items.*.length' => 'required',
            'supply_items.*.height' => 'required',
            'supply_items.*.unit' => 'required',
            'supply_items.*.weight' => 'required',

            'services' => 'required|array',
            'services.*.service_id' => 'required',
            'services.*.service_enabled' => 'required|boolean',
            'services.*.service_attribute' => '',

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
