<?php

namespace App\Http\Requests\Api\WbProduct;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WbProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $clientId = $this->input('client_id');
        
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($clientId) {
            $hasAccess = $user->clients()->where('clients.id', $clientId)->exists();
            
            if (!$hasAccess) {
                throw new \App\Exceptions\UnauthorizedClientAccessException();
            }
            
            return true;
        }
        
        return $user !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'color' => 'sometimes|nullable|string|max:100',
            'composition' => 'sometimes|nullable|string|max:100',
            // 'client_id' => 'sometimes|nullable|exists:clients,id',
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'has_chestny_znak' => 'sometimes|nullable|boolean',
            'article' => 'sometimes|required|string|max:255',
            'vendor_code' => 'sometimes|nullable|string|max:255',
            'category' => ['sometimes', 'required', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'The selected client does not exist.',
        ];
    }

    protected function prepareForValidation()
    {
        if (!$this->has('client_id')) {
            $ctx = app(\App\Support\ClientContext::class);
            if ($ctx->id()) {
                $this->merge(['client_id' => $ctx->id()]);
            }
        }
    }
}