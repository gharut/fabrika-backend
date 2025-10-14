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
        $organizationId = $this->input('client_id');
        
        if ($user->isSystemUser()) {
            return true;
        }

        if ($organizationId) {
            $hasAccess = $user->clients()->where('clients.id', $organizationId)->exists();
            
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
            'brand_id' => 'sometimes|nullable|exists:brands,id',
            'has_chestny_znak' => 'sometimes|nullable|boolean',
            'article' => 'sometimes|required|string|max:255',
            'vendor_code' => 'sometimes|nullable|string|max:255',
            'category_id' => 'sometimes|nullable|exists:marketplace_categories,id',
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