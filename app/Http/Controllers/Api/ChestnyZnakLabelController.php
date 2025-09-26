<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChestnyZnakLabelService;
use App\Models\ChestnyZnakLabel;
use App\Services\LabelPdfService;
use App\Http\Requests\Api\ChestnyZnakLabel\ChestnyZnakLabelImportRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\LabelPrintOptions;

class ChestnyZnakLabelController extends Controller
{
    public function __construct(private ChestnyZnakLabelService $service, private LabelPdfService $labelPdfService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['size_id', 'status']);
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->service->getAll($filters, $perPage);
        return response()->json($paginator);
    }

    public function indexNew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filters' => 'sometimes|array',
            'sort_by' => 'sometimes|string',
            'sort_dir' => 'sometimes|in:asc,desc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'group_by_size' => 'sometimes|boolean'
        ]);

        // Если включена группировка по размеру
        if ($validated['group_by_size'] ?? false) {
            return $this->getGroupedBySize($validated);
        }

        // Стандартный запрос без группировки
        $query = ChestnyZnakLabel::with(['size.product']);

        // Применяем фильтры
        if (!empty($validated['filters'])) {
            $this->applyFilters($query, $validated['filters']);
        }

        // Сортировка
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Пагинация
        $perPage = $validated['per_page'] ?? 15;
        $page = $validated['page'] ?? 1;

        $labels = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $labels->items(),
            'meta' => [
                'current_page' => $labels->currentPage(),
                'last_page' => $labels->lastPage(),
                'per_page' => $labels->perPage(),
                'total' => $labels->total(),
            ]
        ]);
    }

    private function getGroupedBySize(array $params): JsonResponse
    {
        $query = \DB::table('chestny_znak_labels as czl')
            ->join('product_sizes as ps', 'czl.size_id', '=', 'ps.id')
            ->join('wb_products as p', 'ps.product_id', '=', 'p.id')
            ->select([
                'p.id as id',
                'p.id as product_id',
                'p.name as product_name',
                'ps.id as size_id',
                'ps.barcode as barcode',
                'ps.value as size_value',
                \DB::raw('COUNT(czl.id) as total'),
                \DB::raw('SUM(CASE WHEN czl.status = "used" THEN 1 ELSE 0 END) as used'),
                \DB::raw('SUM(CASE WHEN czl.status = "available" THEN 1 ELSE 0 END) as unused'),
            ])
            ->groupBy('ps.id');

        if (!empty($params['filters'])) {
            $this->applyGroupedFilters($query, $params['filters']);
        }

        $sortBy = $params['sort_by'] ?? 'product_name';
        $sortDir = $params['sort_dir'] ?? 'asc';
        
        $allowedSortFields = ['product_name', 'size_value', 'total', 'used', 'unused'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        $totalQuery = clone $query;
        $total = \DB::table(\DB::raw("({$totalQuery->toSql()}) as sub"))
            ->mergeBindings($totalQuery)
            ->count();

        $query->offset(($page - 1) * $perPage)->limit($perPage);
        $results = $query->get();

        return response()->json([
            'data' => $results,
            'meta' => [
                'grouped_by' => 'size',
                'current_page' => (int)$page,
                'last_page' => ceil($total / $perPage),
                'per_page' => (int)$perPage,
                'total' => $total,
                'total_groups' => $total
            ]
        ]);
    }

    private function applyGroupedFilters($query, array $filters): void
    {
        foreach ($filters as $filter) {
            if (isset($filter['group'])) {
                // Обработка групп фильтров
                $query->where(function ($subQuery) use ($filter) {
                    $this->applyGroupedFilters($subQuery, $filter['filters']);
                }, null, null, $filter['group']);
            } else {
                $field = $filter['field'];
                $operation = $filter['op'];
                $value = $filter['value'];

                $fieldMap = [
                    'product_id' => 'p.id',
                    'product_name' => 'p.name',
                    'size_id' => 'ps.id',
                    'size_name' => 's.name',
                    'barcode' => 'ps.barcode'
                ];

                $dbField = $fieldMap[$field] ?? $field;

                switch ($operation) {
                    case 'eq':
                        $query->where($dbField, $value);
                        break;
                    case 'ne':
                        $query->where($dbField, '!=', $value);
                        break;
                    case 'like':
                        $query->where($dbField, 'like', "%{$value}%");
                        break;
                }
            }
        }
    }

    private function applyFilters($query, array $filters, string $groupOperator = 'and'): void
    {
        foreach ($filters as $filter) {
            if (isset($filter['group'])) {
                $query->where(function ($subQuery) use ($filter) {
                    $this->applyFilters($subQuery, $filter['filters'], $filter['group']);
                }, null, null, $filter['group']);
            } 
            else {
                $field = $filter['field'];
                $operation = $filter['op'];
                $value = $filter['value'];

                $this->applySimpleFilter($query, $field, $operation, $value, $groupOperator);
            }
        }
    }

    private function applySimpleFilter($query, string $field, string $operation, $value, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        switch ($operation) {
            case 'eq':
                $query->{$method}($field, $value);
                break;
                
            case 'ne':
                $query->{$method}($field, '!=', $value);
                break;
                
            case 'like':
                $query->{$method}($field, 'like', "%{$value}%");
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported operation: {$operation}");
        }
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->getOne($id));
    }

    public function store(Request $request): JsonResponse
    {
        $label = $this->service->create($request->all());

        return response()->json($label, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(null, 204);
    }

    public function findByCode(string $code): JsonResponse
    {
        $label = $this->service->findByCode($code);
        return response()->json($label);
    }

    public function markAsUnused(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        $result = $this->service->markAsUnused($data['ids']);

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
                'data'    => ['updated' => $result->updated],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'data'    => ['updated' => $result->updated],
        ]);
    }

    public function import(ChestnyZnakLabelImportRequest $req): JsonResponse
    {
        $files = $req->file('file');
        $sizeIds = $req->input('size_id');

        $fileResults = [];

        foreach ($files as $idx => $file) {
            $sizeId = (int) ($sizeIds[$idx] ?? 0);
            $path  = $file->getRealPath();
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $result = $this->service->importCsv($sizeId, $lines);

            $fileResults[] = [
                'fileName' => $file->getClientOriginalName(),
                'created'  => $result['created_count'] ?? 0,
                'errors'   => $result['errors'] ?? [],
            ];
        }

        $hasErrors = collect($fileResults)->contains(fn($f) => !empty($f['errors']));

        return response()->json($fileResults, $hasErrors ? 207 : 201);
    }
    
    public function downloadPdfLabels(Request $request)
    {
        $data = $request->validate([
            'sizeId' => 'required|exists:product_sizes,id',
            'labelId' => 'required|exists:labels,id',
            'quantity' => 'required|integer|min:1'
        ]);
        
        $options = new LabelPrintOptions(
            sizeId: $data['sizeId'],
            labelId: $data['labelId'],
        );

        // return $this->labelPdfService->generateFromHtml($options, $data['quantity']);
        return $this->labelPdfService->generateFromDesignerSchema($options, $data['quantity']);
    }

    public function replaceSize(Request $request)
    {
        $data = $request->validate([
            'quantity'    => 'required|integer|min:1',
            'old_size_id' => 'required|integer|exists:chestny_znak_labels,size_id',
            'new_size_id' => 'required|integer|exists:product_sizes,id',
        ]);

        try {
            $updated = $this->service->replaceSize(
                $data['quantity'],
                $data['old_size_id'],
                $data['new_size_id']
            );

            return response()->json([
                'message'       => "Успешно заменено {$updated} меток",
                'updated_count' => $updated,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Произошла ошибка при замене меток',
            ], 500);
        }
    }
}