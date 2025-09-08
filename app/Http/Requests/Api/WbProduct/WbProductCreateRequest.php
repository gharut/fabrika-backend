<?php

namespace App\Http\Requests\Api\WbProduct;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WbProductCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $clientId = $this->input('client_id');
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
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:100',
            'composition' => 'nullable|string|max:100',
            'client_id' => 'nullable|exists:clients,id',
            'brand_id' => 'nullable|exists:brands,id',
            'has_chestny_znak' => 'nullable|boolean',
            'article' => 'required|string|max:255',
            'vendor_code' => 'nullable|string|max:255',
            'category' => ['required', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
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