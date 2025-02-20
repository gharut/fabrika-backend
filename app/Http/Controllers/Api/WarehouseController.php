<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Warehouse\WarehouseCreateRequest;
use App\Http\Requests\Api\Warehouse\WarehouseUpdateRequest;
use App\Models\Warehouse;
use App\Services\ConsumableService;
use App\Services\SupplierService;
use App\Services\WarehouseService;

class WarehouseController extends Controller
{
    private WarehouseService $warehouseService;
    private ConsumableService $consumableService;
    private SupplierService $supplierService;

    public function __construct(
        WarehouseService $warehouseService,
        ConsumableService $consumableService,
        SupplierService $supplierService
    )
    {
        $this->warehouseService = $warehouseService;
        $this->consumableService = $consumableService;
        $this->supplierService = $supplierService;
    }

    public function index()
    {
        return $this->warehouseService->getAll();
    }

    public function getSelections()
    {
        return [
            'consumableSelection' => $this->consumableService->getSelection(),
            'supplierSelection' => $this->supplierService->getSelection()
        ];
    }

    public function store(WarehouseCreateRequest $request)
    {
        $item = $this->warehouseService->store($request->validated());

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function edit(Warehouse $warehouse)
    {
        //
    }

    public function update(int $id, WarehouseUpdateRequest $request)
    {
        $item = $this->warehouseService->update($id, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function destroy(int $id)
    {
        $this->warehouseService->delete($id);

        // return response()->noContent();
        return response()->json([
            'success' => true
        ]);
    }
}
