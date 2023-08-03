<?php

namespace App\Http\Requests\Api\Profile;

use App\Http\Requests\FailedValidationTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProfileUpdateRequest extends FormRequest
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
            'email' => 'required|email|unique:users,email,'.\auth("sanctum")->user()->id,
            'change_password' => 'required|boolean',
            'password' => 'required_if:change_password,true|nullable|min:6',
            'password_repeat' => 'required_if:change_password,on|same:password',
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
