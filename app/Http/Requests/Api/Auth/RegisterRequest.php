<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\FailedValidationTrait;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users|max:255',
            'password' => 'required|min:10',
        ];
    }

    public function messages()
    {
        return [
            'required' => 'required',
            'required_if' => 'required',
            'email'  => 'filled_wrong',
            'min' => 'length.min'
        ];
    }
}
