<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class LabelTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LabelTemplate::query();

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $template = LabelTemplate::findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    // TO DO: исправить данные которые передаем, добавить валидацию
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'content'  => 'required|json',
            'is_system'=> 'boolean',
        ]);

        $template = LabelTemplate::create([
            ...$validated,
            'user_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'data' => $template,
        ], 201);
    }

    // TO DO: добавить проверки по пользователю, чтобы нельзя было редачить чужие и системные
    public function update(Request $request, int $id): JsonResponse
    {
        $template = LabelTemplate::findOrFail($id);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'content'  => 'sometimes|json',
            'is_system'=> 'boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'data' => $template,
        ]);
    }

    // TO DO: добавить проверки по пользователю, чтобы нельзя было удалять чужие и системные
    public function destroy(int $id): JsonResponse
    {
        $template = LabelTemplate::findOrFail($id);
        $template->delete();

        return response()->json([], 204);
    }
}
