<?php

namespace App\Services;

use App\Models\MoveOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MoveOrderService
{
    public function __construct(private readonly MoveOrder $moveOrder) {}

    public function getMoveOrders()
    {
        return $this->moveOrder->query()
            ->with(['moveInventory'])
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeMoveOrder(array $data): MoveOrder
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->moveOrder->create(Arr::except($data, 'move_inventory'));

                    $stock->moveInventory()->createMany($data['move_inventory']);

                    return $stock->load('moveInventory');
                });
            } catch (QueryException $exception) {
                if ( $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create move order.');
    }

    public function findById(int $id): ?MoveOrder
    {
        return $this->moveOrder->with(['moveInventory'])->find($id);
    }

    public function updateMoveOrder(array $data, MoveOrder $moveOrder): MoveOrder
    {
        return DB::transaction(function () use ($data, $moveOrder) {
            // Serialize concurrent edits of the same stock
            $stock = $this->moveOrder->whereKey($moveOrder->getKey())->lockForUpdate()->firstOrFail();

            $stock->update(Arr::except($data, 'move_inventory'));

            // Only touch lines if the client sent them (supports header-only PATCH)
            if (isset($data['move_inventory'])) {
                $stock->moveInventory()->delete();
                $stock->moveInventory()->createMany($data['move_inventory']);
            }

            return $stock->load('moveInventory');
        });
    }

    public function deleteMoveOrder(int $id): void {
        $moveOrder = $this->moveOrder->withTrashed()->find($id);
        if ($moveOrder->trashed()) {
            $moveOrder->restore();
        } else {
            $moveOrder->delete();
        }
    }
}
