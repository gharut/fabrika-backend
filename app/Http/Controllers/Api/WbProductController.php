<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WbProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\WbProduct\WbProductCreateRequest;
use App\Http\Requests\Api\WbProduct\WbProductUpdateRequest;
use Illuminate\Validation\ValidationException;
use App\Enums\ProductCategory;
use Illuminate\Validation\Rule;
use App\Models\WbProduct;

class WbProductController extends Controller
{
    private WbProductService $service;

    public function __construct(WbProductService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $clientIdParam = $request->query('client_id');
        $clientId = is_numeric($clientIdParam) ? (int)$clientIdParam : null;

        $productIdParam = $request->query('product_id');
        $productId = is_numeric($productIdParam) ? (int)$productIdParam : null;

        $name = $request->query('name');

        $wbProducts = $this->service->getAll($clientId, $productId, $name);

        return response()->json($wbProducts);
    }
    
    public function getAllWithSizes(Request $request): JsonResponse
    {
        $filters = $request->input('filters', []);
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc');
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $result = $this->service->getAllWithSizes($filters, $sortBy, $sortDir, $perPage, $page);
        return response()->json($result);
    }

    public function show(int $id): JsonResponse
    {
        $wbProduct = $this->service->getById($id);
        return response()->json($wbProduct);
    }

    public function store(WbProductCreateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $wbProduct = $this->service->create($data);
        
        $wbProduct->refresh();
        return response()->json($wbProduct->load(['client', 'brand']), 201);
    }

    public function update(WbProductUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $updatedProduct = $this->service->update($id, $data);

            return response()->json([
                'success' => true,
                'data' => $updatedProduct
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении товара',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}