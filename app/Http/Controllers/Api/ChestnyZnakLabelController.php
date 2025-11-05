<?php

namespace App\Http\Controllers\Api;

use App\Services\ChestnyZnakLabelService;
use App\Services\LabelPdfService;

use App\Models\ChestnyZnakLabel;
use App\Models\FileOperation;
use App\Models\LabelPrintOptions;

use App\Jobs\ProcessPdfImportJob;
use App\Support\ClientContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChestnyZnakLabel\ChestnyZnakLabelImportRequest;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class ChestnyZnakLabelController extends Controller
{
    public function __construct(
        private ChestnyZnakLabelService $service, 
        private LabelPdfService $labelPdfService,
        private ClientContext $clientContext
    ) {}

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
        $query = ChestnyZnakLabel::with(['size.product', 'usedBy:id,name', 'creator:id,name']);

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
            'data' => $labels->getCollection()->transform(function ($label) {
                return [
                    'id'          => $label->id,
                    'code'        => $label->code,
                    'number'      => $label->number,
                    'status'      => $label->status,
                    'created_at'  => $label->created_at,
                    'created_by' => $label->creator ? [
                            'id'   => $label->creator->id,
                            'name' => $label->creator->name,
                        ] : null,
                    'used_at'     => $label->used_at,
                    'used_by' => $label->usedBy ? [
                            'id'   => $label->usedBy->id,
                            'name' => $label->usedBy->name,
                        ] : null,
                    'size' => $label->size ?? null,
                ];
            }),
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
        $clientId = $this->clientContext->get();

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
            ->when($clientId, fn($q) => $q->where('czl.client_id', $clientId))
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
                    case 'ge':
                        $query->where($dbField, '>=', $value);
                        break;
                    case 'le':
                        $query->where($dbField, '<=', $value);
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

            case 'ge':
                $query->{$method}($field, '>=', $value);
                break;

            case 'le':
                $query->{$method}($field, '<=', $value);
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

    public function importCsv(ChestnyZnakLabelImportRequest $req): JsonResponse
    {
        $files = $req->file('file');
        $sizeIds = $req->input('size_id');

        $fileResults = [];

        foreach ($files as $idx => $file) {
            $sizeId = (int) ($sizeIds[$idx] ?? 0);
            $path  = $file->getRealPath();
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            if (strtolower((string) $file->getClientOriginalExtension()) !== 'csv') {
                continue;
            }

            $result = $this->service->importCsv($sizeId, $lines, $fileName, $fileSize);
            $fileResults[] = [
                'fileName' => $file->getClientOriginalName(),
                'created'  => $result['created_count'] ?? 0,
                'errors'   => $result['errors'] ?? [],
            ];
        }

        $hasErrors = collect($fileResults)->contains(fn($f) => !empty($f['errors']));

        return response()->json($fileResults, $hasErrors ? 207 : 201);
    }

    public function importPdf(Request $req): JsonResponse
    {
        $files = $req->file('file');
        $sizeIds = $req->input('size_id', []);
        $clientId = app(\App\Support\ClientContext::class)->id();

        if (!$files) {
            return response()->json(['error' => 'Файлы не загружены'], 400);
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $userId = Auth::id();
        $operations = [];

        foreach ($files as $index => $file) {
            if (!$file->isValid()) {
                continue;
            }

            $sizeId = (int) ($sizeIds[$index] ?? 0);
            $path = $file->store('imports');

            $operation = FileOperation::create([
                'operation_type' => 'import',
                'file_name'      => $file->getClientOriginalName(),
                'file_extension' => $file->getClientOriginalExtension(),
                'file_size'      => $file->getSize(),
                'user_id'        => $userId,
                'status'         => FileOperation::STATUS_IN_PROGRESS,
                'related_to'     => 'ChestnyZnakLabel',
            ]);

            ProcessPdfImportJob::dispatch($operation->id, $path, $sizeId, $clientId, Auth::id());

            $operations[] = [
                'operation_id' => $operation->id,
                'file_name'    => $file->getClientOriginalName(),
            ];
        }

        return response()->json([
            'data' => collect($operations)->map(fn($op) => [
                'fileName' => $op['file_name'],
            ]),
            'message' => 'Файлы приняты в обработку',
        ], 202);

    }

    public function status(Request $request): JsonResponse
    {
        $id = $request->query('operation_id');
        $op = FileOperation::findOrFail($id);

        return response()->json($op);
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