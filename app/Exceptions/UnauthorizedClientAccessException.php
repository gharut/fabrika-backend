<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnauthorizedClientAccessException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'У вас нет доступа к этой организации.',
            'error' => 'client_access_denied',
            'client_id' => $request->input('client_id')
        ], 403);
    }
}