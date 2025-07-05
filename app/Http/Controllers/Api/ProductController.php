<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private ProductService $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $clientIdParam = $request->query('client_id', null);
        $clientId = is_numeric($clientIdParam) ? (int)$clientIdParam : null;

        $products = $this->service->getAll($clientId);
        return response()->json($products);
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->service->getById($id);
        return response()->json($product);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name'       => 'required|string|max:255',
            'parent_id'  => 'nullable|exists:products,id',
            'qty'        => 'required|integer|min:0',
            'color'      => 'nullable|string|max:100',
            'size'       => 'nullable|array',
            'size.*'     => 'string',
            'complect'   => 'nullable|integer',
            'delivered'  => 'nullable|boolean',
            'client_id'  => 'required|exists:clients,id',
        ]);
        $data['order_id'] = 1;
        $product = $this->service->create($data);
        return response()->json($product, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validate($request, [
            'name'       => 'sometimes|required|string|max:255',
            'parent_id'  => 'sometimes|nullable|exists:products,id',
            'qty'        => 'sometimes|required|integer|min:0',
            'color'      => 'sometimes|nullable|string|max:100',
            'size'       => 'sometimes|nullable|array',
            'size.*'     => 'string',
            'complect'   => 'sometimes|nullable|integer',
            'delivered'  => 'sometimes|boolean',
            'client_id'  => 'required|exists:clients,id',
        ]);
        $data['order_id'] = 1;
        $product = $this->service->update($id, $data);
        return response()->json($product);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}
