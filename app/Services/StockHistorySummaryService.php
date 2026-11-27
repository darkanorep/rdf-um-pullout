<?php

namespace App\Services;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class StockHistorySummaryService
{
    public function __construct(private readonly Stock $stock) {}

    /**
     * Return current stock on hand for the authenticated user. When requested,
     * attach transaction detail for the item groups on the current page.
     */
    public function getStockOnHandSummary(
        bool $includeHistory = false
    ): LengthAwarePaginator|SupportCollection
    {
        $userId = auth()->id();

        abort_unless($userId, 401, 'Authentication is required.');

        $summary = $this->stockHistorySummaryQuery((int) $userId)->dynamicPaginate();

        if (! $includeHistory || $summary->isEmpty()) {
            return $summary;
        }

        $rows = $summary instanceof LengthAwarePaginator
            ? $summary->getCollection()
            : $summary;
        $itemIds = $rows->pluck('item_id')->unique()->values()->all();

        $events = $this->historyEventsQuery((int) $userId, $itemIds)->get();
        $eventsByItem = $events->groupBy('item_id');

        $rows->transform(function ($row) use ($eventsByItem) {
            $groupEvents = $eventsByItem->get((int) $row->item_id, collect());

            $balance = 0;

            $history = $groupEvents
                ->map(function (object $event) use (&$balance): object {
                    $event->reference = ucfirst(str_replace('_', ' ', $event->movement_type))
                        .' #'.$event->reference_id;
                    $balance += $this->toMilliQuantity((string) $event->quantity_in)
                        - $this->toMilliQuantity((string) $event->quantity_out);
                    $event->balance_after = $this->fromMilliQuantity($balance);

                    return $event;
                })
                ->values();

            $row->setAttribute('history', $history);

            return $row;
        });

        if ($summary instanceof LengthAwarePaginator) {
            $summary->setCollection($rows);

            return $summary;
        }

        return $rows;
    }

    /**
     * Return stock totals grouped by warehouse, with item detail and optional
     * transaction history for the items on the current warehouse page.
     */
    public function getStockOnHandSummaryForAllWarehouses(
        ?int $warehouseId = null,
        ?int $perPage = null,
        bool $includeHistory = false,
    ): LengthAwarePaginator {
        abort_unless(auth()->id(), 401, 'Authentication is required.');

        $perPage = min(100, max(1, $perPage ?? (int) request()->input('per_page', 15)));
        $summary = $this->stockHistorySummaryQuery(null, $warehouseId);

        $warehousePage = DB::query()
            ->fromSub($summary, 'user_item_summary')
            ->select([
                'user_item_summary.warehouse_id',
                'user_item_summary.warehouse_code',
                'user_item_summary.warehouse_name',
            ])
            ->selectRaw('COALESCE(SUM(user_item_summary.received), 0) AS received')
            ->selectRaw('COALESCE(SUM(user_item_summary.misc_receipt), 0) AS misc_receipt')
            ->selectRaw('COALESCE(SUM(user_item_summary.misc_issue), 0) AS misc_issue')
            ->selectRaw('COALESCE(SUM(user_item_summary.move_order), 0) AS move_order')
            ->selectRaw('COALESCE(SUM(user_item_summary.transfer_in), 0) AS transfer_in')
            ->selectRaw('COALESCE(SUM(user_item_summary.transfer_out), 0) AS transfer_out')
            ->selectRaw('COALESCE(SUM(user_item_summary.stock_on_hand), 0) AS stock_on_hand')
            ->groupBy(
                'user_item_summary.warehouse_id',
                'user_item_summary.warehouse_code',
                'user_item_summary.warehouse_name',
            )
            ->orderBy('user_item_summary.warehouse_name')
            ->paginate($perPage);

        $warehouseIds = $warehousePage->getCollection()
            ->pluck('warehouse_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $itemsByWarehouse = $warehouseIds === []
            ? collect()
            : $this->stockWarehouseItemSummaryQuery(null, $warehouseIds)
                ->get()
                ->groupBy('warehouse_id');

        $itemIds = $itemsByWarehouse->flatten(1)
            ->pluck('item_id')
            ->unique()
            ->values()
            ->all();
        $eventsByWarehouseItem = $includeHistory && $itemIds !== []
            ? $this->historyEventsQuery(null, $itemIds, $warehouseId)
                ->get()
                ->groupBy(fn ($event) => $event->warehouse_id.':'.$event->item_id)
            : collect();

        $quantityFields = [
            'received',
            'misc_receipt',
            'misc_issue',
            'move_order',
            'transfer_in',
            'transfer_out',
            'stock_on_hand',
        ];

        $warehousePage->setCollection($warehousePage->getCollection()->map(
            function (object $row) use ($itemsByWarehouse, $quantityFields, $eventsByWarehouseItem, $includeHistory) {
                $items = $itemsByWarehouse->get($row->warehouse_id, collect())
                    ->map(function (object $itemRow) use ($quantityFields, $eventsByWarehouseItem, $includeHistory, $row) {
                        $item = [
                            'item_id' => $itemRow->item_id,
                            'item_code' => $itemRow->item_code,
                            'item_name' => $itemRow->item_name,
                            'item_category' => $itemRow->item_category,
                            'uom_id' => $itemRow->uom_id,
                            'uom' => $itemRow->uom,
                            'uom_symbol' => $itemRow->uom_symbol,
                        ];

                        foreach ($quantityFields as $field) {
                            $item[$field] = $this->fromMilliQuantity(
                                (int) round((float) $itemRow->{$field} * 1000)
                            );
                        }

                        if ($includeHistory) {
                            $events = $eventsByWarehouseItem->get(
                                $row->warehouse_id.':'.$itemRow->item_id,
                                collect(),
                            );
                            $balance = 0;
                            $item['history'] = $events->map(function (object $event) use (&$balance) {
                                $event->reference = ucfirst(str_replace('_', ' ', $event->movement_type))
                                    .' #'.$event->reference_id;
                                $balance += $this->toMilliQuantity((string) $event->quantity_in)
                                    - $this->toMilliQuantity((string) $event->quantity_out);
                                $event->balance_after = $this->fromMilliQuantity($balance);

                                return $event;
                            })->values();
                        }

                        return $item;
                    })
                    ->values();

                $warehouse = [
                    'warehouse_id' => $row->warehouse_id,
                    'warehouse_code' => $row->warehouse_code,
                    'warehouse_name' => $row->warehouse_name,
                ];

                foreach ($quantityFields as $field) {
                    $warehouse[$field] = $this->fromMilliQuantity(
                        (int) round((float) $row->{$field} * 1000)
                    );
                }

                $warehouse['items'] = $items;

                return $warehouse;
            }
        ));

        return $warehousePage;
    }

    /** Full summary query shared by the API and Excel export; it is not paginated. */
    public function stockHistorySummaryQuery(?int $userId = null, ?int $warehouseId = null): EloquentBuilder
    {
        $movedQuantities = DB::table('move_inventories as mi')
            ->select('mi.inventory_id')
            ->selectRaw('SUM(mi.quantity) AS moved_quantity')
            ->whereNull('mi.deleted_at')
            ->groupBy('mi.inventory_id');

        $availablePerRow = '
            CASE
                WHEN COALESCE(i.quantity_on_hand, 0)
                     - COALESCE(mv.moved_quantity, 0) > 0
                THEN COALESCE(i.quantity_on_hand, 0)
                     - COALESCE(mv.moved_quantity, 0)
                ELSE 0
            END
        ';

        $currentStock = DB::table('inventories as i')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->leftJoinSub($movedQuantities, 'mv', fn ($join) =>
            $join->on('mv.inventory_id', '=', 'i.id')
            )
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'stock_on_hand' AS movement_type,
                CASE WHEN COALESCE(i.quantity_on_hand, 0) - COALESCE(mv.moved_quantity, 0) > 0
                    THEN COALESCE(i.quantity_on_hand, 0) - COALESCE(mv.moved_quantity, 0)
                    ELSE 0 END AS quantity");

        $received = DB::table('inventories as i')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('transfer_items as ti')
                    ->whereColumn('ti.received_inventory_id', 'i.id');
            })
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'received' AS movement_type, COALESCE(i.actual_received, 0) AS quantity");

        $miscReceipt = DB::table('miscellaneous_inventories as mi')
            ->join('miscellaneous_receipts as mr', 'mr.id', '=', 'mi.misc_receipt_id')
            ->selectRaw("mr.user_id AS user_id, mi.item_id, mi.uom_id, mi.account_title_id,
                'misc_receipt' AS movement_type, mi.quantity AS quantity");

        $miscIssue = DB::table('miscellaneous_issues as issue')
            ->join('miscellaneous as misc', 'misc.id', '=', 'issue.miscellaneous_id')
            ->join('inventories as i', 'i.id', '=', 'issue.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'misc_issue' AS movement_type, issue.quantity AS quantity");

        $moveOrder = DB::table('move_inventories as mi')
            ->join('inventories as i', 'i.id', '=', 'mi.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('mi.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'move_order' AS movement_type, mi.quantity AS quantity");

        $transferIn = DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 't.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'ti.received_inventory_id')
            ->where('bw.status', 'accepted')
            ->whereNull('bw.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw("bw.transferred_to AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'transfer_in' AS movement_type, ti.quantity AS quantity");

        $transferOut = DB::table('bonded_items as bi')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 'bi.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'bi.inventory_id')
            ->whereIn('bw.status', ['pending', 'accepted'])
            ->whereNull('bw.deleted_at')
            ->whereNull('i.deleted_at')
            ->selectRaw("bw.transferred_by AS user_id, i.item_id, i.uom_id, i.account_title_id,
                'transfer_out' AS movement_type, bi.quantity AS quantity");

        $movements = $currentStock
            ->unionAll($received)
            ->unionAll($miscReceipt)
            ->unionAll($miscIssue)
            ->unionAll($moveOrder)
            ->unionAll($transferIn)
            ->unionAll($transferOut);

        $totals = DB::query()
            ->fromSub($movements, 'm')
            ->select(['m.user_id', 'm.item_id'])
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'received' THEN m.quantity ELSE 0 END) AS received")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'misc_receipt' THEN m.quantity ELSE 0 END) AS misc_receipt")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'misc_issue' THEN m.quantity ELSE 0 END) AS misc_issue")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'move_order' THEN m.quantity ELSE 0 END) AS move_order")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'transfer_in' THEN m.quantity ELSE 0 END) AS transfer_in")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'transfer_out' THEN m.quantity ELSE 0 END) AS transfer_out")
            ->selectRaw("SUM(CASE WHEN m.movement_type = 'stock_on_hand' THEN m.quantity ELSE 0 END) AS stock_on_hand")
            ->groupBy('m.user_id', 'm.item_id');

        $formatted = DB::query()
            ->fromSub($totals, 'summary')
            ->join('users as u', 'u.id', '=', 'summary.user_id')
            ->join('items as item', 'item.id', '=', 'summary.item_id')
            ->leftJoin('categories as item_category', 'item_category.id', '=', 'item.category_id')
            ->leftJoin('unit_of_measurements as item_uom', 'item_uom.id', '=', 'item.uom_id')
            ->leftJoin('warehouses as warehouse', 'warehouse.id', '=', 'u.warehouse_id')
            ->select([
                'summary.user_id',
                'u.first_name',
                'u.last_name',
                'summary.item_id as id',
                'summary.item_id',
                'u.warehouse_id as warehouse_id',
                'warehouse.code as warehouse_code',
                'warehouse.name as warehouse_name',
                'item.code as item_code',
                'item.name as item_name',
                'item_category.name as item_category',
                'item.uom_id',
                'item_uom.name as uom',
                'item_uom.symbol as uom_symbol',
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
            ->when(
                $userId !== null,
                fn ($query) => $query->where('stock_summary.user_id', $userId)
            )
            ->when(
                $warehouseId !== null,
                fn ($query) => $query->where('stock_summary.warehouse_id', $warehouseId)
            )
            ->useFilters()
            ->orderBy('stock_summary.last_name')
            ->orderBy('stock_summary.item_name');
    }

    /** Item-level movement totals for all users assigned to one warehouse. */
    public function stockWarehouseItemSummaryQuery(?int $warehouseId = null, ?array $warehouseIds = null): QueryBuilder
    {
        $summary = $this->stockHistorySummaryQuery(null, $warehouseId);

        return DB::query()
            ->fromSub($summary, 'user_item_summary')
            ->select([
                'user_item_summary.warehouse_id',
                'user_item_summary.warehouse_code',
                'user_item_summary.warehouse_name',
                'user_item_summary.item_id',
                'user_item_summary.item_code',
                'user_item_summary.item_name',
                'user_item_summary.item_category',
                'user_item_summary.uom_id',
                'user_item_summary.uom',
                'user_item_summary.uom_symbol',
            ])
            ->selectRaw('COALESCE(SUM(user_item_summary.received), 0) AS received')
            ->selectRaw('COALESCE(SUM(user_item_summary.misc_receipt), 0) AS misc_receipt')
            ->selectRaw('COALESCE(SUM(user_item_summary.misc_issue), 0) AS misc_issue')
            ->selectRaw('COALESCE(SUM(user_item_summary.move_order), 0) AS move_order')
            ->selectRaw('COALESCE(SUM(user_item_summary.transfer_in), 0) AS transfer_in')
            ->selectRaw('COALESCE(SUM(user_item_summary.transfer_out), 0) AS transfer_out')
            ->selectRaw('COALESCE(SUM(user_item_summary.stock_on_hand), 0) AS stock_on_hand')
            ->groupBy(
                'user_item_summary.warehouse_id',
                'user_item_summary.warehouse_code',
                'user_item_summary.warehouse_name',
                'user_item_summary.item_id',
                'user_item_summary.item_code',
                'user_item_summary.item_name',
                'user_item_summary.item_category',
                'user_item_summary.uom_id',
                'user_item_summary.uom',
                'user_item_summary.uom_symbol',
            )
            ->when($warehouseIds !== null, fn ($query) => $query->whereIn('user_item_summary.warehouse_id', $warehouseIds))
            ->orderBy('user_item_summary.item_name');
    }

    /** Backward-compatible alias for callers that only need the balance query. */
    public function stockOnHandQuery(int $userId): EloquentBuilder
    {
        return $this->stockHistorySummaryQuery($userId);
    }

    /**
     * Detailed events for the summary page. All source branches return the
     * same columns so they can be combined with UNION ALL.
     */
    private function historyEventsQuery(?int $userId, array $itemIds, ?int $warehouseId = null): QueryBuilder
    {
        if ($itemIds === []) {
            return DB::query()->fromSub(DB::table('inventories')->whereRaw('1 = 0'), 'empty_history')
                ->selectRaw('NULL AS user_id, NULL AS item_id, NULL AS uom_id, NULL AS account_title_id,
                    NULL AS event_date, NULL AS movement_type, NULL AS reference_id,
                    0 AS quantity_in, 0 AS quantity_out, NULL AS remarks')
                ->whereRaw('1 = 0');
        }

        // Initial receipts, excluding inventory rows generated by transfer-in.
        $received = DB::table('inventories as i')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('transfer_items as ti')
                    ->whereColumn('ti.received_inventory_id', 'i.id');
            })
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                s.received_date AS event_date, 'receiving' AS movement_type,
                s.id AS reference_id, COALESCE(i.actual_received, 0) AS quantity_in,
                0 AS quantity_out, s.remarks AS remarks");

        $miscReceipts = DB::table('miscellaneous_inventories as mi')
            ->join('miscellaneous_receipts as mr', 'mr.id', '=', 'mi.misc_receipt_id')
            ->selectRaw("mr.user_id AS user_id, mi.item_id, mi.uom_id, mi.account_title_id,
                mr.transaction_date AS event_date, 'misc_receipt' AS movement_type,
                mr.id AS reference_id, mi.quantity AS quantity_in, 0 AS quantity_out,
                mr.remarks AS remarks");

        // Attribute issues to the inventory owner, not the user who recorded them.
        $miscIssues = DB::table('miscellaneous_issues as issue')
            ->join('miscellaneous as misc', 'misc.id', '=', 'issue.miscellaneous_id')
            ->join('inventories as i', 'i.id', '=', 'issue.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                misc.date AS event_date, 'misc_issue' AS movement_type,
                misc.id AS reference_id, 0 AS quantity_in, issue.quantity AS quantity_out,
                misc.remarks AS remarks");

        $moveOrders = DB::table('move_inventories as mi')
            ->join('inventories as i', 'i.id', '=', 'mi.inventory_id')
            ->join('stocks as s', 's.id', '=', 'i.stock_id')
            ->whereNull('mi.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereNull('s.deleted_at')
            ->selectRaw("s.user_id AS user_id, i.item_id, i.uom_id, i.account_title_id,
                mi.created_at AS event_date, 'move_order' AS movement_type,
                mi.id AS reference_id, 0 AS quantity_in, mi.quantity AS quantity_out,
                NULL AS remarks");

        $transferIn = DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 't.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'ti.received_inventory_id')
            ->where('bw.status', 'accepted')
            ->whereNull('bw.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw("bw.transferred_to AS user_id, i.item_id, i.uom_id, i.account_title_id,
                COALESCE(t.accepted_at, bw.release_date) AS event_date,
                'transfer_in' AS movement_type, t.id AS reference_id,
                ti.quantity AS quantity_in, 0 AS quantity_out, bw.remarks AS remarks");

        // A rejected request has an outbound reservation and a return event.
        $transferOut = DB::table('bonded_items as bi')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 'bi.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'bi.inventory_id')
            ->whereIn('bw.status', ['pending', 'accepted'])
            ->whereNull('bw.deleted_at')
            ->whereNull('i.deleted_at')
            ->selectRaw("bw.transferred_by AS user_id, i.item_id, i.uom_id, i.account_title_id,
                bw.transfer_date AS event_date, 'transfer_out' AS movement_type,
                bw.id AS reference_id, 0 AS quantity_in, bi.quantity AS quantity_out,
                bw.remarks AS remarks");

        $rejectedTransferOut = DB::table('bonded_items as bi')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 'bi.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'bi.inventory_id')
            ->where('bw.status', 'rejected')
            ->whereNull('bw.deleted_at')
            ->whereNull('i.deleted_at')
            ->selectRaw("bw.transferred_by AS user_id, i.item_id, i.uom_id, i.account_title_id,
                bw.transfer_date AS event_date, 'transfer_rejected' AS movement_type,
                bw.id AS reference_id, 0 AS quantity_in, bi.quantity AS quantity_out,
                bw.rejection_reason AS remarks");

        $transferReturns = DB::table('bonded_items as bi')
            ->join('bonded_warehouses as bw', 'bw.id', '=', 'bi.bonded_warehouse_id')
            ->join('inventories as i', 'i.id', '=', 'bi.inventory_id')
            ->where('bw.status', 'rejected')
            ->whereNull('bw.deleted_at')
            ->whereNull('i.deleted_at')
            ->selectRaw("bw.transferred_by AS user_id, i.item_id, i.uom_id, i.account_title_id,
                COALESCE(bw.decided_at, bw.updated_at) AS event_date,
                'transfer_return' AS movement_type, bw.id AS reference_id,
                bi.quantity AS quantity_in, 0 AS quantity_out, bw.rejection_reason AS remarks");

        $union = $received
            ->unionAll($miscReceipts)
            ->unionAll($miscIssues)
            ->unionAll($moveOrders)
            ->unionAll($transferIn)
            ->unionAll($transferOut)
            ->unionAll($rejectedTransferOut)
            ->unionAll($transferReturns);

        return DB::query()
            ->fromSub($union, 'history')
            ->join('users as history_user', 'history_user.id', '=', 'history.user_id')
            ->select('history.*', 'history_user.warehouse_id')
            ->when($userId !== null, fn ($query) => $query->where('history.user_id', $userId))
            ->when($warehouseId !== null, fn ($query) => $query->where('history_user.warehouse_id', $warehouseId))
            ->whereIn('history.item_id', $itemIds)
            ->orderBy('history.event_date')
            ->orderBy('history.reference_id');
    }

    private function toMilliQuantity(string $quantity): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function fromMilliQuantity(int $quantity): string
    {
        $sign = $quantity < 0 ? '-' : '';
        $quantity = abs($quantity);

        return $sign.intdiv($quantity, 1000).'.'.str_pad(
                (string) ($quantity % 1000),
                3,
                '0',
                STR_PAD_LEFT,
            );
    }
}
