<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// TO DO
class LabelTemplateController extends Controller
{
    /**
     * Получить список шаблонов
     */
    public function index(Request $request): JsonResponse
    {
        $query = LabelTemplate::query();

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Получить один шаблон
     */
    public function show(int $id): JsonResponse
    {
        $template = LabelTemplate::findOrFail($id);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Создать шаблон
     */
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

    /**
     * Обновить шаблон
     */
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

    /**
     * Удалить шаблон
     */
    public function destroy(int $id): JsonResponse
    {
        $template = LabelTemplate::findOrFail($id);
        $template->delete();

        return response()->json([], 204);
    }
}
