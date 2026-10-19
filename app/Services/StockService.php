<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Stock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private readonly Stock $stock) {}

    public function getStock() {
        return $this->stock->query()
            ->with(['inventories'])
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeStock(array $data): Stock
    {
        return DB::transaction(function () use ($data) {
            $stock = $this->stock->create(Arr::except($data, 'inventory'));

            $stock->inventories()->createMany($data['inventory']);

            return $stock->load('inventories');
        });
    }

    public function findById(int $id): ?Stock
    {
        return $this->stock->find($id);
    }

    public function updateStock(array $data, Stock $stock): Stock
    {
        return DB::transaction(function () use ($data, $stock) {
            // Serialize concurrent edits of the same stock
            $stock = $this->stock->whereKey($stock->getKey())->lockForUpdate()->firstOrFail();

            $stock->update(Arr::except($data, 'inventory'));

            // Only touch lines if the client sent them (supports header-only PATCH)
            if (array_key_exists('inventory', $data)) {
                $this->syncInventories($stock, $data['inventory']);
            }

            return $stock->load('inventories');
        });
    }

    private function syncInventories(Stock $stock, array $lines): void
    {
        // Scoped to THIS stock, so a client can't hijack another stock's line by passing its id
        $existing = $stock->inventories()->get()->keyBy('id');
        $keepIds = [];

        foreach ($lines as $line) {
            $id = $line['id'] ?? null;

            if ($id === null) {
                $keepIds[] = $stock->inventories()->create($line)->id;
                continue;
            }

            $inventory = $existing->get($id)
                ?? throw (new ModelNotFoundException)->setModel(Inventory::class, [$id]);

            $inventory->update(Arr::except($line, 'id'));
            $keepIds[] = $inventory->id;
        }

        // Soft-delete lines the client removed
        $stock->inventories()->whereNotIn('id', $keepIds)->delete();
    }

    public function destroyStock(int $id): void {
        $stock = $this->stock->withTrashed()->find($id);
        if ($stock->trashed()) {
            $stock->restore();
        } else {
            $stock->delete();
        }
    }
}
