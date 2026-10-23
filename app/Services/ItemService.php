<?php

namespace App\Services;

use App\Models\Item;

class ItemService
{
    public function __construct(private readonly Item $item) {}

    public function getAllItems()
    {
        return $this->item->query()
            ->with(['warehouse', 'category', 'unitOfMeasurement'])
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeItem(array $data): Item
    {
        return $this->item->create($data);
    }

    public function findById(int $id): ?Item
    {
        return $this->item->with(['warehouse', 'category', 'unitOfMeasurement'])->find($id);
    }

    public function updateItem(array $data, Item $item): Item {
        $item->update($data);
        return $item;
    }

    public function destroyItem(int $id): void
    {
        $item = $this->item->withTrashed()->find($id);
        if ($item->trashed()) {
            $item->restore();
        } else {
            $item->delete();
        }
    }
}
