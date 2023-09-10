<?php

namespace App\Http\Requests\Api\Orders;

use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use App\Rules\ProductIdExistsInProducts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\In;

class OrderUpdateStatusRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('update-orders-status');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'attribute' => ['required', new In(['delivery_status', 'payment_status'])],
            'value' => 'required|numeric',

        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'error_required',
            'string' => 'error_string',
            'unique'  => 'error_unique',
            'exist'  => 'error_exist',
            'in'  => 'wrong_attribute',
        ];
    }
}
