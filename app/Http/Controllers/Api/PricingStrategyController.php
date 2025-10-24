<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingStrategy;
use App\Services\PricingStrategyService;
use App\Services\StrategyItemService;
use Illuminate\Http\Request;

class PricingStrategyController extends Controller
{
    public function __construct(
        private PricingStrategyService $service,
        private StrategyItemService $itemService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|in:time_discount',
            'status'          => 'nullable|string|in:draft,active,paused',
            'order_by_field'  => 'nullable|string|max:64',
            'order_direction' => 'nullable|string|in:asc,desc',
        ]);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $strategy = $this->service->create($data);

        return response()->json($strategy, 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'type'            => 'nullable|string|in:time_discount',
            'status'          => 'nullable|string|in:draft,active,paused',
            'order_by_field'  => 'nullable|string|max:64',
            'order_direction' => 'nullable|string|in:asc,desc',
        ]);

        $data['updated_by'] = auth()->id();

        $strategy = $this->service->update($id, $data);

        if (! $strategy) {
            return response()->json(['message' => 'Стратегия не найдена'], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($strategy, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function index(Request $request)
    {
        $q = PricingStrategy::query()
            ->withCount('items')
            ->orderBy('id','desc');

        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return $q->paginate($request->integer('perPage', 20));
    }

    public function show(int $id)
    {
        $strategy = PricingStrategy::withCount('items')->findOrFail($id);
        return response()->json($strategy, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function run(Request $request, int $id)
    {
        $data = $request->validate([
            'wbToken' => 'nullable|string',
        ]);

        $wbToken = $data['wbToken'];

        $res = $this->service->run($id, $wbToken);
        $code = !empty($res['error']) ? 422 : 200;

        return response()->json($res, $code, [], JSON_UNESCAPED_UNICODE);
    }

    public function addItems(Request $request, int $id)
    {
        $data = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.model_id'      => 'required|integer|min:1',
        ]);

        $count = $this->itemService->bulkCreate($id, $data['items']);
        return response()->json(['added' => $count], 201, [], JSON_UNESCAPED_UNICODE);
    }

    private function formatTime($value)
    {
        return $value ? substr($value, 0, 5) : null;
    }

    public function items(Request $request, int $id)
    {
        $perPage = $request->integer('perPage', 50);
        $list = $this->itemService->listByStrategy($id, $perPage);

        $list->getCollection()->transform(function ($item) {
            $product = $item->wbProduct;

            return [
                'id'             => $item->id,
                'status'         => $item->status,
                'discount'       => $item->discount,
                'temp_discount'  => $item->temp_discount,
                'starts_at'      => $this->formatTime($item->starts_at),
                'ends_at'        => $this->formatTime($item->ends_at),
                'total_qty'      => $item->total_qty,
                'product' => $product ? [
                    'id'          => $product->id,
                    'article'     => $product->article,
                    'vendor_code' => $product->vendor_code,
                    'name'        => $product->name,
                    'color'       => $product->color,
                    'image'       => $product->image,
                ] : null,
            ];
        });

        return $list;
    }

    public function availableProducts(Request $request, int $id)
    {
        $perPage = $request->integer('perPage', 50);

        if ($id == null || $id <= 0) {
            return [];
        }

        return $this->itemService->getAvailableProducts($id, $perPage);
    }

    public function destroy(int $id)
    {
        $ok = $this->service->delete($id);

        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'Стратегия не найдена'], 404, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(['success' => true, 'message' => ''], 200, [], JSON_UNESCAPED_UNICODE);
    }
}