<?php

namespace App\Http\Requests\Api\Consumables;

use App\Enums\PriceThreshold;
use App\Enums\TagType;
use App\Enums\Unit;
use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;

class ConsumableUpdateRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('edit-consumables');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'size' => 'required',
            'unit' => ['required', new Enum(Unit::class)],
            'price' => 'required|numeric|min:0',
            'tags.*' => 'required|exists:tags,id',
            'price_threshold' => 'required_with:price_threshold_type|numeric|min:0',
            'price_threshold_type' => ['required_with:price_threshold_type', new Enum(PriceThreshold::class)],
            'qty_threshold' => 'numeric|min:0'
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
