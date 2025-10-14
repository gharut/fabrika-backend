<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\SizeDisplayType;

class LabelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organizationId = $this->input('client_id');
        
        if ($user->isSystemUser()) {
            return true;
        }

        if ($organizationId) {
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
            'product_id' => ['sometimes','integer','exists:wb_products,id'],
            'client_id' => ['sometimes','nullable','exists:clients,id'],
            'client_name' => ['sometimes', 'string', 'max:255'],
            'printer_id' => ['sometimes', 'nullable', 'integer', 'exists:printers,id'],
            'print_single_ean13' => ['sometimes', 'boolean'],
            'print_double_ean13' => ['sometimes', 'boolean'],
            'duplicate_chz' => ['sometimes', 'boolean'],
            'label_template_id' => ['sometimes', 'integer', 'exists:label_templates,id'],
            'size_display_type' => ['sometimes', 'string', Rule::in(array_column(SizeDisplayType::cases(), 'value'))],
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
        // Если client_id не указан, берем из контекста
        if (!$this->has('client_id')) {
            $ctx = app(\App\Support\ClientContext::class);
            if ($ctx->id()) {
                $this->merge(['client_id' => $ctx->id()]);
            }
        }
    }
}

