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
        $data = $request->validate([
            'temp_discount' => 'nullable|numeric|min:0|max:100',
            'starts_at'     => 'nullable|date_format:H:i',
            'ends_at'       => 'nullable|date_format:H:i|after_or_equal:starts_at',
            'status'        => 'nullable|string|in:active,paused',
        ]);

        $result = $this->service->update($id, $data);

        return response()->json(['success' => $result, 'message' => '']);
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