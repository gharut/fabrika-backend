<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\FailedValidationTrait;
use Illuminate\Foundation\Http\FormRequest;

class PasswordResetRequest extends FormRequest
{
    use FailedValidationTrait;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ];
    }

    public function messages()
    {
        return [
            'required' => 'required',
            'confirmed' => 'required',
            'email'  => 'filled_wrong',
            'min' => 'length.min'
        ];
    }
}
