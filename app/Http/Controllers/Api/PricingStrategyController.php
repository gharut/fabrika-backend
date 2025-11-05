<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingStrategy;
use App\Services\PricingStrategyService;
use App\Services\StrategyItemService;
use App\Traits\StrategyValidationTrait;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PricingStrategyController extends Controller
{
    use StrategyValidationTrait;

    public function __construct(
        private PricingStrategyService $service,
        private StrategyItemService $itemService
    ) {}

    public function store(Request $request)
    {
        try {
            $data = $request->validate(
                $this->getValidationRules('store'),
                $this->getValidationMessages()
            );

            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            $strategy = $this->service->create($data);

            return response()->json([
                'data' => $strategy,
                'success' => true,
            ], 201, [], JSON_UNESCAPED_UNICODE);

        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Exception $e) {
            return $this->errorResponse('Ошибка при создании стратегии: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $data = $request->validate(
                $this->getValidationRules('update'),
                $this->getValidationMessages()
            );

            $data['updated_by'] = auth()->id();

            $strategy = $this->service->update($id, $data);

            if (!$strategy) {
                return $this->errorResponse('Стратегия не найдена', 404);
            }

            return response()->json([
                'data' => $strategy,
                'success' => true,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Exception $e) {
            return $this->errorResponse('Ошибка при обновлении стратегии: ' . $e->getMessage(), 500);
        }
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
        try {
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Некорреектное значение Id'
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            $strategy = PricingStrategy::withCount('items')->find($id);

            if (!$strategy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Стратегия не найдена'
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json([
                'data' => $strategy,
                'success' => true,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении стратегии'
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function addItems(Request $request, int $id)
    {
        $data = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.model_id' => 'required|integer|min:1',
        ]);

        $result = $this->itemService->bulkCreate($id, $data['items']);
        $status = $result->success ? 201 : 400;

        return response()->json([
            'success' => $result->success,
            'created' => $result->created,
            'message' => $result->message,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }

    public function updateItemsTime(Request $request, int $id)
    {
        try {
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID должен быть положительным числом'
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            $data = $request->validate([
                'field' => 'required|string|in:starts_at,ends_at',
                'value' => [
                    'required',
                    'string',
                    'regex:/^([0-1][0-9]|2[0-3]):(00|30)$/'
                ],
            ], [
                'field.required' => 'Поле Колонка обязательно для заполнения',
                'field.string' => 'Поле Колонка должно быть строкой',
                'field.in' => 'Недопустимое значение для Колонки',
                'value.required' => 'Поле Время обязательно для заполнения',
                'value.string' => 'Поле Время должно быть строкой',
                'value.regex' => 'Время должно быть в формате HH:MM (00 или 30 минут)',
            ]);

            $field = $data['field'];
            $value = $data['value'];

            $count = $this->itemService->updateItemsTime($id, $field, $value);

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не найдено элементов для обновления или стратегия не существует'
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json([
                'data' => ['updated' => $count],
                'success' => true,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = implode(', ', array_map(function ($error) {
                return is_array($error) ? implode(', ', $error) : $error;
            }, $e->errors()));
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . $errors
            ], 422, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении времени'
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    private function formatTime($value)
    {
        return $value ? substr($value, 0, 5) : null;
    }

    public function items(Request $request, int $id)
    {
        try {
            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Id стратегии должен быть положительным числом'
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }

            $validated = $request->validate([
                'filters' => 'sometimes|array',
                'sort_by' => 'sometimes|string',
                'sort_dir' => 'sometimes|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'filters.array' => 'Фильтры должны быть массивом',
                'sort_by.string' => 'Поле сортировки должно быть строкой',
                'sort_dir.in' => 'Направление сортировки должно быть asc или desc',
                'page.integer' => 'Номер страницы должен быть числом',
                'page.min' => 'Номер страницы должен быть положительным числом',
                'per_page.integer' => 'Количество элементов на странице должно быть числом',
                'per_page.min' => 'Количество элементов на странице должно быть не менее 1',
                'per_page.max' => 'Количество элементов на странице не может превышать 100',
            ]);

            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortDir = $validated['sort_dir'] ?? 'desc';
            $filters = $validated['filters'] ?? [];
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            $list = $this->itemService->listByStrategy($id, $filters, $sortBy, $sortDir, $page, $perPage);

            $transformedData = $list->getCollection()->transform(function ($item) {
                $product = $item->wbProduct;

                return [
                    'id'             => $item->id,
                    'status'         => $item->status,
                    'discount'       => $item->discount,
                    'temp_discount'  => round($item->temp_discount),
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
                        'product'     => $product,
                    ] : null,
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'success' => true,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = implode(', ', array_map(function ($error) {
                return is_array($error) ? implode(', ', $error) : $error;
            }, $e->errors()));
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации параметров: ' . $errors
            ], 422, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка элементов'
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function availableProducts(Request $request, int $id)
    {
        $validated = $request->validate([
            'filters' => 'sometimes|array',
            'sort_by' => 'sometimes|string',
            'sort_dir' => 'sometimes|in:asc,desc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $filters = $validated['filters'] ?? [];

        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        
        if ($id == null || $id <= 0) {
            return [];
        }

        return $this->itemService->getAvailableProducts($id, $filters, $sortBy, $sortDir, $perPage, $page);
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