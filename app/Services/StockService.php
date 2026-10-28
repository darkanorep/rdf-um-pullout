<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Stock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private readonly Stock $stock) {}

    public function getStock()
    {
        return $this->stock->query()
            ->with(['inventories'])
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeStock(array $data): Stock
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->stock->create(Arr::except($data, 'inventory'));

                    $stock->inventories()->createMany($data['inventory']);

                    return $stock->load('inventories');
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicateBatchNumber($exception) || $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create stock.');
    }

    private function isDuplicateBatchNumber(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'stocks_batch_no_unique');
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

    public function destroyStock(int $id): void
    {
        $stock = $this->stock->withTrashed()->find($id);
        if ($stock->trashed()) {
            $stock->restore();
        } else {
            $stock->delete();
        }
    }

    public function getStockOnHand()
    {
        $movedQuantity = <<<'SQL'
        COALESCE((
            SELECT SUM(mi.quantity)
            FROM move_inventories AS mi
            WHERE mi.inventory_id = inventories.id
              AND mi.deleted_at IS NULL
        ), 0)
        SQL;

        $hasRemainingStock = static fn ($query) => $query->whereRaw(
            "inventories.actual_received - ({$movedQuantity}) > 0"
        );

        $stocks = $this->stock->query()
            ->whereHas('inventories', $hasRemainingStock)
            ->with([
                'inventories' => static function ($query) use ($movedQuantity) {
                    $query->addSelect('inventories.*')
                        ->selectRaw(
                            "inventories.actual_received - ({$movedQuantity}) AS remaining_stock"
                        )
                        ->whereRaw(
                            "inventories.actual_received - ({$movedQuantity}) > 0"
                        );
                },
                'inventories.moveInventory',
            ])
            ->orderBy('updated_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        $inventoryRecords = $stocks->flatMap(function ($stock) {
            return $stock->inventories->map(function ($inventory) use ($stock) {
                $inventory->setAttribute('source_stock', [
                    'id' => $stock->id,
                    'batch_no' => $stock->batch_no,
                    'received_date' => $stock->received_date,
                    'supplier_name' => $stock->supplier_name,
                ]);

                return $inventory;
            });
        });

        return $inventoryRecords
            ->groupBy('item_id')
            ->map(fn ($records, $itemId) => [
                'item_id' => (int) $itemId,
                'item_code' => $records->first()->item->code,
                'item_name' => $records->first()->item->name,
                'uom_id' => $records->first()->uom_id,
                'uom' => $records->first()->uom->name,
                'remaining_stock' => $records->sum(
                    fn ($inventory) => (float) $inventory->remaining_stock
                ),
                'records' => $records->values(),
            ])
            ->values();
    }
}
