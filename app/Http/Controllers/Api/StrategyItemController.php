<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StrategyItemService;
use Illuminate\Http\Request;

class StrategyItemController extends Controller
{
    public function __construct(
        private StrategyItemService $service
    ) {}

    public function update(Request $request, int $id)
    {
        try {
            $data = $request->validate([
                'temp_discount' => 'nullable|numeric|min:0|max:100',
                'starts_at'     => 'nullable|date_format:H:i',
                'ends_at'       => 'nullable|date_format:H:i|after_or_equal:starts_at',
                'status'        => 'nullable|string|in:active,paused',
            ], [
                'temp_discount.numeric' => 'Скидка должна быть числом.',
                'temp_discount.max' => 'Скидка не может быть больше 100.',
                'temp_discount.min' => 'Скидка не может быть меньше 0.',
                'starts_at.date_format' => 'Время начала должно быть в формате ЧЧ:ММ.',
                'ends_at.date_format' => 'Время окончания должно быть в формате ЧЧ:ММ.',
                'ends_at.after_or_equal' => 'Время окончания не может быть раньше начала.',
                'status.in' => 'Недопустимое значение статуса.',
            ]);

            $result = $this->service->update($id, $data);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Данные успешно обновлены.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Изменения не внесены или запись не найдена.',
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
            ], 422);

        } catch (\Throwable $e) {
            \Log::error('Ошибка при обновлении StrategyItem', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении. Повторите попытку позже.',
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $ok = $this->service->delete($id);

        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'Элемент не найден'], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(['success' => true, 'message' => ''], 200, [], JSON_UNESCAPED_UNICODE);
    }
}