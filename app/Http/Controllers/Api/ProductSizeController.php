<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductSize\ProductSizeCreateRequest;
use App\Http\Requests\Api\ProductSize\ProductSizeUpdateRequest;
use App\Services\ProductSizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSizeController extends Controller
{
    public function __construct(private ProductSizeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'sometimes|integer|exists:products,id',
            'per_page'   => 'sometimes|integer|min:1',
        ]);

        $productId = $data['product_id'] ?? null;
        $perPage   = $data['per_page']   ?? 15;

        $paginated = $this->service->getAll($productId, $perPage);

        return response()->json($paginated);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->getOne($id));
    }

    public function store(ProductSizeCreateRequest $request): JsonResponse
    {
        $size = $this->service->create($request->validated());
        return response()->json($size, 201);
    }

    public function update(int $id, ProductSizeUpdateRequest $request): JsonResponse
    {
        $size = $this->service->update($id, $request->validated());
        return response()->json([
            'success' => true,
            'data'    => $size,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}
