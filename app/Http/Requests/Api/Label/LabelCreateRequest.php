<?php

namespace App\Http\Requests\Api\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\SizeDisplayType;

class LabelCreateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required','integer','exists:wb_products,id'],
            'client_id' => ['nullable','exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'label_template_id' => ['nullable', 'integer', 'exists:label_templates,id'],
            'print_single_ean13' => ['nullable', 'boolean'],
            'print_double_ean13' => ['nullable', 'boolean'],
            'duplicate_chz' => ['nullable', 'boolean'],
            'size_display_type' => ['nullable', 'string', Rule::in(array_column(SizeDisplayType::cases(), 'value'))],
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