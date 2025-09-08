<?php

namespace App\Http\Requests\Api\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
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

