<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Label\LabelCreateRequest;
use App\Http\Requests\Api\Label\LabelUpdateRequest;
use App\Services\LabelService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LabelController extends Controller
{
    public function __construct(private LabelService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->getAll());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->getOne($id));
    }

    public function store(LabelCreateRequest $request): JsonResponse
    {
        $label = $this->service->create($request->validated());
        return response()->json($label, 201);
    }

    public function update(int $id, LabelUpdateRequest $request): JsonResponse
    {
        $label = $this->service->update($id, $request->validated());
        return response()->json(['success'=>true,'data'=>$label]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }
}
