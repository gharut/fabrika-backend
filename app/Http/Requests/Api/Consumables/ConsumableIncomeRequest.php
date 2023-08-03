<?php

namespace App\Http\Requests\Api\Consumables;

use App\Enums\PriceThreshold;
use App\Enums\TagType;
use App\Enums\Unit;
use App\Http\Requests\FailedValidationTrait;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class ConsumableIncomeRequest extends FormRequest
{
    use FailedValidationTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('income-consumables');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new In([0, 1, 2, 3, 4])],
            'consumable_id' => 'required|exists:consumables,id',

            'supplier_id' => [
                'required_if:type,1',
                function ($attribute, $value, $fail) {
                    if ($this->input('type') == 1 && !Supplier::where('id', $value)->exists()) {
                        $fail('The selected supplier does not exist.');
                    }
                },
            ],
            'operation_id' => '',
            'qty' => 'required|numeric',
            'price_per_unit' => 'required_if:type,1|nullable|numeric',
            'delivery_status' => ['required_if:type,1','nullable', 'boolean'],
            'payment_status' => ['required_if:type,1','nullable', 'boolean'],
            'delivery_date' => [''],
            'payment_date' => [''],
            'details' => ['required_if:type,2']
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'error_required',
            'string' => 'error_string',
            'unique' => 'error_unique',
            'exists' => 'error_not_exsits',
        ];
    }
}
