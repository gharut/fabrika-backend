<?php

namespace App\Http\Requests\Api\Roles;

use App\Http\Requests\FailedValidationTrait;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RoleUpdateRequest extends FormRequest
{
    use FailedValidationTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @param User $user
     * @return bool
     */
    public function authorize(User $user): bool
    {
        return Auth::user()->can('edit-roles');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
//        unique:tags,name,'.$this->tag->id.',id',
        return [
            'name' => 'required|string|unique:roles,name,'.$this->role->id.',id',
            'visible_name' => 'required|string|unique:roles,visible_name,'.$this->role->id.',id',
            'permissions.*' => "exists:permissions,id"
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
