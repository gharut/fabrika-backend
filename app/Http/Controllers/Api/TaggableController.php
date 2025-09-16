<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TagService;
use App\Support\TaggableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaggableController extends Controller
{
    public function __construct(
        private readonly TagService $service
    ) {}

    public function list(string $alias, int $id): JsonResponse
    {
        $class = TaggableResolver::resolve($alias);
        $model = $class::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $model->tags()->orderBy('name')->get(),
        ]);
    }

    public function attach(Request $request, string $alias, int $id): JsonResponse
    {
        $tagIds = $request->input('tag_ids', []);

        $class = TaggableResolver::resolve($alias);
        $model = $class::findOrFail($id);

        $this->service->attachToModel($model, $tagIds);

        return response()->json(['success' => true]);
    }

    public function detach(Request $request, string $alias, int $id): JsonResponse
    {
        $tagIds = $request->input('tag_ids', []);

        $class = TaggableResolver::resolve($alias);
        $model = $class::findOrFail($id);

        $this->service->detachFromModel($model, $tagIds);

        return response()->json(['success' => true]);
    }
}
