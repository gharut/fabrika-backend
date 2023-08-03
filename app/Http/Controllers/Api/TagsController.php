<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tags\TagCreateRequest;
use App\Http\Requests\Api\Tags\TagUpdateRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;


class TagsController extends Controller
{

    public function get(Tag $tag): JsonResponse
    {
        $tag->load(['consumables','suppliers']);
        return response()->json([
            'success' => true,
            'data' => $tag,
        ]);
    }

    public function list(): JsonResponse
    {
        $tags = Tag::all()->keyBy('id');;

        return response()->json($tags);
    }

    public function listWithCounts(): JsonResponse
    {
        $tags = Tag::query()->withCount(['consumables', 'suppliers'])->get();
        return response()->json($tags);
    }

    public function listSuppliers(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag->load("suppliers")
        ]);
    }

    public function listConsumables(Tag $tag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tag->load("consumables")
        ]);
    }

    public function store(TagCreateRequest $request)
    {
        $tag = new Tag();
        $tag->fill($request->only(['name', 'type']));
        $saved = $tag->save();
        if($saved) {
            $tag->loadCount(['consumables', 'suppliers']);
        }

        return response()->json([
            'success' => $saved,
            'data' => $saved ? $tag : [],
        ]);
    }

    public function update(TagUpdateRequest $request, Tag $tag) {
        $tag->fill($request->only(['name', 'slug', 'type']));
        $saved = $tag->save();
        return response()->json([
            'success' => $saved,
            'data' => $saved ? $tag->loadCount(['consumables', 'suppliers']) : [],
        ]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->consumables()->detach();
        $tag->suppliers()->detach();

        return response()->json([
            'success' => $tag->delete()
        ]);
    }

}
