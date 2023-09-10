<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ProductIdExistsInProducts implements Rule
{
    public function passes($attribute, $value)
    {
        // Assuming $value is the product_id and $data is the entire form data.
        $data = request()->all();

        // Check if the product_id exists as an index in pickup.products array.
        return isset($data['pickup']['products'][$value]);
    }

    public function message()
    {
        return 'The selected product_id is invalid.';
    }
}
