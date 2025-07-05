<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Printer\PrinterCreateRequest;
use App\Http\Requests\Api\Printer\PrinterUpdateRequest;
use App\Services\PrinterService;

class PrinterController extends Controller
{
    private PrinterService $printerService;

    public function __construct(PrinterService $printerService)
    {
        $this->printerService = $printerService;
    }

    public function index()
    {
        return $this->printerService->getAll();
    }

    public function show(int $id)
    {
        return $this->printerService->getOne($id);
    }

    public function store(PrinterCreateRequest $request)
    {
        $printer = $this->printerService->create($request->validated());
        return response()->json($printer, 201);
    }

    public function update(int $id, PrinterUpdateRequest $request)
    {
        $item = $this->printerService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    public function destroy(int $id)
    {
        $this->printerService->delete($id);
        return response()->noContent();
    }

    public function syncCount(int $id, int $newCount): Printer
    {
        return $this->setLabelsCount($id, $newCount);
    }
}