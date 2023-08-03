<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

class OnlyAllowedKeys implements ValidationRule
{
    protected $allowedKeys;

    public function __construct(array $allowedKeys)
    {
        $this->allowedKeys = $allowedKeys;
    }

    public function passes($attribute, $value)
    {

        $extraKeys = array_diff(array_keys($value), $this->allowedKeys);
        if (!empty($extraKeys)) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return 'The :attribute may only contain the allowed keys.';
    }

    public function validate($attribute, $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }
}
