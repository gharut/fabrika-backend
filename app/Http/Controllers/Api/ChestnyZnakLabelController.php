<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChestnyZnakLabelService;
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
        $filters = $request->only(['size_id', 'used']);
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->service->getAll($filters, $perPage);
        return response()->json($paginator);
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

        return $this->labelPdfService->generateFromHtml($options, $data['quantity']);
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