<?php

namespace App\Services;

use App\Models\Warehouse;

class WarehouseService
{
    public function __construct(private readonly  Warehouse $warehouse) {}

    public function getWarehouses() {
        return $this->warehouse->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeWarehouse(array $data): Warehouse
    {
        return $this->warehouse->create($data);
    }

    public function findById(int $id): ?Warehouse
    {
        return $this->warehouse->find($id);
    }

    public function updateWarehouse(array $data, Warehouse $warehouse): Warehouse {
        $warehouse->update($data);
        return $warehouse;
    }

    public function destroyWarehouse(int $id): void
    {
        $warehouse = $this->warehouse->withTrashed()->find($id);
        if ($warehouse->trashed()) {
            $warehouse->restore();
        } else {
            $warehouse->delete();
        }
    }
}
