<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Brand\BrandCreateRequest;
use App\Http\Requests\Api\Brand\BrandUpdateRequest;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()->with(['client' => function ($query) {
            $query->select('id', 'name');
        }]);

        $data = $query->orderBy('name', 'desc')->get();
        return response()->json($data);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json($brand->load([
            'client' => function ($query) {
                $query->select('id', 'name');
            },
            'creator' => function ($query) {
                $query->select('id', 'name'); 
            },
            'editor' => function ($query) {
                $query->select('id', 'name');
            }
        ]));
    }

    public function store(BrandCreateRequest $request): JsonResponse
    {
        $brand = $this->service->create($request->validated());
        return response()->json($brand, 201);
    }

    public function update(BrandUpdateRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->service->update($brand, $request->validated());
        return response()->json($brand);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->service->delete($brand);
        return response()->json(null, 204);
    }
}
