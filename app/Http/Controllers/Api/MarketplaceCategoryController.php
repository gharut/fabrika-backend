<?php

namespace App\Http\Controllers\Api;

use App\Models\MarketplaceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search');

        $query = MarketplaceCategory::query()
            ->select('id', 'name', 'marketplace_code', 'parent_id')
            ->with(['parent' => function ($query) {
                $query->select('id', 'name');
            }])
            ->orderBy('name');

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }
}