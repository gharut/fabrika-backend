<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnauthorizedClientAccessException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'You do not have access to this client',
            'error' => 'client_access_denied',
            'client_id' => $request->input('client_id')
        ], 403);
    }
}