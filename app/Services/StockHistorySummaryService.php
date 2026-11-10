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

    /** Full summary query shared by the API and Excel export; it is not paginated. */
    public function stockHistorySummaryQuery(int $userId): EloquentBuilder
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
            ->select([
                'summary.user_id',
                'u.first_name',
                'u.last_name',
                'summary.item_id as id',
                'summary.item_id',
                'item.code as item_code',
                'item.name as item_name',
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

    /** Backward-compatible alias for callers that only need the balance query. */
    public function stockOnHandQuery(int $userId): EloquentBuilder
    {
        return $this->stockHistorySummaryQuery($userId);
    }

    /**
     * Detailed events for the summary page. All source branches return the
     * same columns so they can be combined with UNION ALL.
     */
    private function historyEventsQuery(int $userId, array $itemIds): QueryBuilder
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
            ->where('history.user_id', $userId)
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
