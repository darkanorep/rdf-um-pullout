<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Pagination\LengthAwarePaginator;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouseService) {}

    public function index()
    {
        $warehouses = $this->warehouseService->getWarehouses();

        if (empty($warehouses->items())) {
            return $this->responseNotFound(message: 'No warehouses found.');
        }

        return $warehouses instanceof LengthAwarePaginator
            ? $warehouses->through(fn($item) => new WarehouseResource($item))
            : $this->responseSuccess('Warehouses retrieved successfully.', WarehouseResource::collection($warehouses));
    }

    public function store(WarehouseRequest $request) {
        $data = $request->validated();
        $warehouse = $this->warehouseService->storeWarehouse($data);

        return $this->responseCreated(
            message: 'Warehouse created successfully.',
            data: new WarehouseResource($warehouse)
        );
    }

    public function show(int $id)
    {
        $warehouse = $this->warehouseService->findById($id);

        if (! $warehouse) {
            return $this->responseNotFound(message: 'Warehouse not found.');
        }

        return $this->responseSuccess(
            message: 'Warehouse retrieved successfully.',
            data: new WarehouseResource($warehouse)
        );
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $data = $request->validated();

        $updatedWarehouse = $this->warehouseService->updateWarehouse($data, $warehouse);

        return $this->responseAccepted(
            message: 'Warehouse updated successfully.',
            data: new WarehouseResource($updatedWarehouse)
        );
    }

    public function destroy(int $id)
    {
        $this->warehouseService->destroyWarehouse($id);

        return $this->responseAccepted(
            message: 'Warehouse status updated successfully.'
        );
    }
}
