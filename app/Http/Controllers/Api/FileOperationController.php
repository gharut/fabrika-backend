<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileOperation;
use Illuminate\Http\Request;

class FileOperationController extends Controller
{
    public function index(Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);

        $operations = FileOperation::orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($operations);
    }
}
