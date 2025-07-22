<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WbProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Enums\ProductCategory;
use Illuminate\Validation\Rule;

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

    public function show(int $id): JsonResponse
    {
        $wbProduct = $this->service->getById($id);
        return response()->json($wbProduct);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name'       => 'required|string|max:255',
            'color'      => 'nullable|string|max:100',
            'composition'      => 'nullable|string|max:100',
            'client_id'  => 'required|exists:clients,id',
            'has_chestny_znak' => ['required','boolean'],
            'article'          => 'required|string|max:255',
            'color'            => 'required|string|max:100',
            'category'         => ['required', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
        ]);
        $wbProduct = $this->service->create($data);
        return response()->json($wbProduct, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validate($request, [
            'name'       => 'sometimes|required|string|max:255',
            'color'      => 'sometimes|nullable|string|max:100',
            'client_id'  => 'required|exists:clients,id',
            'article'          => 'sometimes|string|max:255',
            'composition'      => 'sometimes|string',
            'has_chestny_znak' => ['sometimes','boolean'],
            'color'            => 'sometimes|string|max:100',
            'category'         => ['sometimes', Rule::in(array_map(fn($c) => $c->value, ProductCategory::cases()))],
        ]);
        $wbProduct = $this->service->update($id, $data);
        return response()->json($wbProduct);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}
