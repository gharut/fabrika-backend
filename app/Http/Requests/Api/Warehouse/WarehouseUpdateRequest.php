<?php

namespace App\Http\Requests\Api\Warehouse;

use App\Enums\WarehouseShippingTypes;
use App\Http\Requests\FailedValidationTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class WarehouseUpdateRequest extends FormRequest
{
    use FailedValidationTrait;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('warehouses')->ignore($this->id)],
            'shipping_type' => ['required', new Enum(WarehouseShippingTypes::class)],
            'consumable_id' => ['required', 'exists:consumables,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],

            'prices' => ['required', 'array', 'min:1'],
            'prices.*.min_quantity' => ['required', 'integer', 'min:0'],
            'prices.*.max_quantity' => ['required', 'integer', 'gt:prices.*.min_quantity'],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.cost_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
