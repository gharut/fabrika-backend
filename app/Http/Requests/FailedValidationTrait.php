<?php
namespace App\Http\Requests;

use Illuminate\Http\JsonResponse;

trait FailedValidationTrait {
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = new JsonResponse([
            'success' => false,
            'data' => [
                'message' => 'The given data is invalid',
                'errors' => $validator->errors()->toArray()
            ],
        ], 400);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
