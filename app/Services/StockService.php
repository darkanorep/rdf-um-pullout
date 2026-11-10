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
            ->where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeStock(array $data): Stock
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->stock->create(
                        array_merge(
                            Arr::except($data, 'inventory'),
                            ['user_id' => auth()->id()]
                        )
                    );

                    $inventoryRows = array_map(static function (array $item): array {
                        $item['quantity_on_hand'] = $item['actual_received'] ?? 0;

                        return $item;
                    }, $data['inventory']);

                    $stock->inventories()->createMany($inventoryRows);

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
        return $this->stock->with(['inventories'])->find($id);
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
        // Scoped to THIS stock, so a client can't hijack another stock's line by passing its ID
        $existing = $stock->inventories()->get()->keyBy('id');
        $keepIds = [];

        foreach ($lines as $line) {
            $id = $line['id'] ?? null;

            if ($id === null) {
                $line['user_id'] = auth()->id();

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

        $remainingStock = "inventories.quantity_on_hand - ({$movedQuantity})";

        $hasRemainingStock = static fn ($query) => $query->whereRaw(
            "{$remainingStock} > 0"
        );

        $stocks = $this->stock->query()
            ->where('user_id', auth()->id())
            ->whereHas('inventories', $hasRemainingStock)
            ->with([
                'inventories' => static function ($query) use (
                    $movedQuantity,
                    $remainingStock
                ) {
                    $query->addSelect('inventories.*')
                        ->selectRaw("{$remainingStock} AS remaining_stock")
                        ->whereRaw("{$remainingStock} > 0");
                },
                'inventories.moveInventories',
                'inventories.accountTitle',
                'inventories.item',
                'inventories.uom',
            ])
            ->orderByDesc('received_date')
            ->orderByDesc('id')
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
            ->map(function ($records, $itemId) {
                $availableRecord = $records->first(
                    fn ($inventory) =>
                        (float) $inventory->getRawOriginal('remaining_stock') > 0
                );

                $firstRecord = $records->first();

                return [
                    'item_id' => (int) $itemId,
                    'inventory_id' => $availableRecord?->id,
                    'item_code' => $firstRecord->item->code,
                    'item_name' => $firstRecord->item->name,
                    'uom_id' => $firstRecord->uom_id,
                    'uom_symbol' => $firstRecord->uom->symbol,
                    'uom' => $firstRecord->uom->name,
                    'remaining_stock' => $records->sum(
                        fn ($inventory) =>
                        (float) $inventory->getRawOriginal('remaining_stock')
                    ),
                    'account_title' => $availableRecord?->accountTitle,
                    'records' => $records->values(),
                ];
            })
            ->values();
    }

    public function truncateStocks(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('bonded_items')->truncate();
        DB::table('bonded_warehouses')->truncate();
        DB::table('inventories')->truncate();
        DB::table('miscellaneous')->truncate();
        DB::table('miscellaneous_inventories')->truncate();
        DB::table('miscellaneous_issues')->truncate();
        DB::table('miscellaneous_receipts')->truncate();
        DB::table('move_inventories')->truncate();
        DB::table('move_orders')->truncate();
        DB::table('stocks')->truncate();
        DB::table('transfer_items')->truncate();
        DB::table('transfers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function stockHistorySummaryQuery(int $userId)
    {
        // Current available balance: quantity_on_hand less active move orders.
        $moved = DB::table('move_inventories as mi')
            ->select('mi.inventory_id')
            ->selectRaw('SUM(mi.quantity) AS moved_quantity')
            ->whereNull('mi.deleted_at')
            ->groupBy('mi.inventory_id');

        $currentStock = DB::table('inventories as i')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->leftJoinSub($moved, 'mv', fn ($join) =>
            $join->on('mv.inventory_id', '=', 'i.id')
            )
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("
            s.user_id AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'stock_on_hand' AS movement_type,
            CASE
                WHEN COALESCE(i.quantity_on_hand, 0) - COALESCE(mv.moved_quantity, 0) > 0
                THEN COALESCE(i.quantity_on_hand, 0) - COALESCE(mv.moved_quantity, 0)
                ELSE 0
            END AS quantity
        ");

        // Original receiving. Transfer-in inventory rows are reported separately below.
        $received = DB::table('inventories as i')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('transfer_items as ti')
                    ->whereColumn('ti.received_inventory_id', 'i.id');
            })
            ->selectRaw("
            s.user_id AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'received' AS movement_type,
            COALESCE(i.actual_received, 0) AS quantity
        ");

        $miscReceipt = DB::table('miscellaneous_inventories as mi')
            ->join('miscellaneous_receipts as mr', 'mr.id', '=', 'mi.misc_receipt_id')
            ->selectRaw("
            mr.user_id AS user_id,
            mi.item_id AS item_id,
            mi.uom_id AS uom_id,
            mi.account_title_id AS account_title_id,
            'misc_receipt' AS movement_type,
            mi.quantity AS quantity
        ");

        // Attribute issues to the inventory owner, via stocks.user_id.
        $miscIssue = DB::table('miscellaneous_issues as issue')
            ->join('miscellaneous as misc', 'misc.id', '=', 'issue.miscellaneous_id')
            ->join('inventories as i', 'i.id', '=', 'issue.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("
            s.user_id AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'misc_issue' AS movement_type,
            issue.quantity AS quantity
        ");

        $moveOrder = DB::table('move_inventories as mi')
            ->join('inventories as i', 'i.id', '=', 'mi.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('mi.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("
            s.user_id AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'move_order' AS movement_type,
            mi.quantity AS quantity
        ");

        $transferIn = DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 't.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'ti.received_inventory_id')
            ->where('bw.status', 'accepted')
            ->whereNull('bw.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw("
            bw.transferred_to AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'transfer_in' AS movement_type,
            ti.quantity AS quantity
        ");

        // Pending transfers have already reserved stock; rejected ones are excluded.
        $transferOut = DB::table('bonded_items as bi')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 'bi.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'bi.inventory_id')
            ->whereIn('bw.status', ['pending', 'accepted'])
            ->whereNull('bw.deleted_at')
            ->whereNull('i.deleted_at')
            ->selectRaw("
            bw.transferred_by AS user_id,
            i.item_id AS item_id,
            i.uom_id AS uom_id,
            i.account_title_id AS account_title_id,
            'transfer_out' AS movement_type,
            bi.quantity AS quantity
        ");

        $movements = $currentStock
            ->unionAll($received)
            ->unionAll($miscReceipt)
            ->unionAll($miscIssue)
            ->unionAll($moveOrder)
            ->unionAll($transferIn)
            ->unionAll($transferOut);

        $totals = DB::query()
            ->fromSub($movements, 'm')
            ->select([
                'm.user_id',
                'm.item_id',
                'm.uom_id',
                'm.account_title_id',
            ])
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'received' THEN m.quantity ELSE 0 END) AS received")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'misc_receipt' THEN m.quantity ELSE 0 END) AS misc_receipt")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'misc_issue' THEN m.quantity ELSE 0 END) AS misc_issue")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'move_order' THEN m.quantity ELSE 0 END) AS move_order")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'transfer_in' THEN m.quantity ELSE 0 END) AS transfer_in")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'transfer_out' THEN m.quantity ELSE 0 END) AS transfer_out")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'stock_on_hand' THEN m.quantity ELSE 0 END) AS stock_on_hand")
            ->groupBy('m.user_id', 'm.item_id', 'm.uom_id', 'm.account_title_id');

        $formatted = DB::query()
            ->fromSub($totals, 'summary')
            ->join('users as u', 'u.id', '=', 'summary.user_id')
            ->join('items as item', 'item.id', '=', 'summary.item_id')
            ->join('unit_of_measurements as uom', 'uom.id', '=', 'summary.uom_id')
            ->join('account_titles as account', 'account.id', '=', 'summary.account_title_id')
            ->select([
                'summary.user_id',
                'u.first_name',
                'u.last_name',
                'summary.item_id as id',
                'item.code as item_code',
                'item.name as item_name',
                'summary.uom_id',
                'uom.name as uom',
                'uom.symbol as uom_symbol',
                'summary.account_title_id',
                'account.name as account_title',
                'summary.received',
                'summary.misc_receipt',
                'summary.misc_issue',
                'summary.move_order',
                'summary.transfer_in',
                'summary.transfer_out',
                'summary.stock_on_hand',
            ]);

        return $this->stock->newQueryWithoutScopes()
            ->fromSub($formatted, 'stock_summary')
            ->select('stock_summary.*')
            ->where('stock_summary.user_id', $userId)
            ->useFilters()
            ->orderBy('stock_summary.last_name')
            ->orderBy('stock_summary.item_name');
    }

    public function getStockHistorySummary()
    {
        return $this->stockHistorySummaryQuery(auth()->id())
            ->dynamicPaginate();
    }
}
