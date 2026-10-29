<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\MoveInventory;
use App\Models\MoveOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveOrderService
{
    public function __construct(
        private readonly MoveOrder $moveOrder
    ) {}

    public function getMoveOrders()
    {
        return $this->moveOrder->query()
            ->with('moveInventory')
            ->orderByDesc('updated_at')
            ->useFilters()
            ->dynamicPaginate();
    }

    public function storeMoveOrder(array $data): MoveOrder
    {
        return DB::transaction(function () use ($data) {
            $moveOrder = $this->moveOrder->create(
                Arr::except($data, ['move_inventory'])
            );

            $this->allocateInventory($moveOrder, $data['move_inventory']);

            return $moveOrder->load('moveInventory');
        }, 3);
    }

    public function findById(int $id): ?MoveOrder
    {
        return $this->moveOrder
            ->with('moveInventory')
            ->find($id);
    }

    public function updateMoveOrder(
        array $data,
        MoveOrder $moveOrder
    ): MoveOrder {
        return DB::transaction(function () use ($data, $moveOrder) {
            $lockedMoveOrder = $this->moveOrder
                ->whereKey($moveOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMoveOrder->update(
                Arr::except($data, ['move_inventory'])
            );

            if (array_key_exists('move_inventory', $data)) {
                $lockedMoveOrder->moveInventory()->delete();

                $this->allocateInventory(
                    $lockedMoveOrder,
                    $data['move_inventory']
                );
            }

            return $lockedMoveOrder->load('moveInventory');
        }, 3);
    }

    public function deleteMoveOrder(int $id): void
    {
        DB::transaction(function () use ($id) {
            $moveOrder = $this->moveOrder
                ->withTrashed()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $moveInventory = $moveOrder
                ->moveInventory()
                ->withTrashed();

            if ($moveOrder->trashed()) {
                $moveInventory->restore();
                $moveOrder->restore();

                return;
            }

            $moveInventory->delete();
            $moveOrder->delete();
        });
    }

    /**
     * Allocate requested item quantities across batches,
     * newest received batch first.
     *
     * @param array<int, array{item_id: int, quantity: int|float|string}> $lines
     */
    private function allocateInventory(
        MoveOrder $moveOrder,
        array $lines
    ): void {
        foreach ($lines as $index => $line) {
            $quantityLeft = round((float) $line['quantity'], 3);

            $inventories = Inventory::query()
                ->join('stocks', 'stocks.id', '=', 'inventories.stock_id')
                ->where('inventories.item_id', $line['item_id'])
                ->whereNull('stocks.deleted_at')
                ->orderByDesc('stocks.received_date')
                ->orderByDesc('inventories.id')
                ->select('inventories.*')
                ->lockForUpdate()
                ->get();

            foreach ($inventories as $inventory) {
                $alreadyMoved = (float) MoveInventory::query()
                    ->where('inventory_id', $inventory->id)
                    ->whereNull('deleted_at')
                    ->sum('quantity');

                $available = round(
                    (float) $inventory->actual_received - $alreadyMoved,
                    3
                );

                if ($available <= 0) {
                    continue;
                }

                $allocated = round(min($available, $quantityLeft), 3);

                $moveOrder->moveInventory()->create([
                    'inventory_id' => $inventory->id,
                    'quantity' => $allocated,
                ]);

                $quantityLeft = round($quantityLeft - $allocated, 3);

                if ($quantityLeft <= 0) {
                    break;
                }
            }

            if ($quantityLeft > 0) {
                throw ValidationException::withMessages([
                    "move_inventory.{$index}.quantity" =>
                        'The requested quantity exceeds the available stock for this item.',
                ]);
            }
        }
    }
}
