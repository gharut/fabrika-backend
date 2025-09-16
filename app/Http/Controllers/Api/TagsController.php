<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tags\TagCreateRequest;
use App\Http\Requests\Api\Tags\TagUpdateRequest;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;

class TagsController extends Controller
{
    public function __construct(
        private readonly TagService $service
    ) {}

    public function get(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    public function list(): JsonResponse
    {
        $tags = $this->service->list();

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    public function store(TagCreateRequest $request): JsonResponse
    {
        $tag = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $tag,
            'message' => 'Тег успешно создан',
        ], 201);
    }

    public function update(TagUpdateRequest $request, Tag $tag): JsonResponse
    {
        $updated = $this->service->update($tag, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->service->delete($tag);

        return response()->json([
            'success' => true,
        ]);
    }
}
