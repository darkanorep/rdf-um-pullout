<?php

namespace App\Exports;

use App\Services\StockHistorySummaryService;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockWarehouseHistoryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly int $warehouseId) {}

    public function query(): Builder
    {
        return app(StockHistorySummaryService::class)
            ->stockWarehouseItemSummaryQuery($this->warehouseId);
    }

    public function headings(): array
    {
        return [
            'WAREHOUSE CODE',
            'WAREHOUSE NAME',
            'ITEM CODE',
            'ITEM NAME',
            'RECEIVED',
            'MISC RECEIPT',
            'MISC ISSUE',
            'MOVE ORDER',
            'TRANSFER IN',
            'TRANSFER OUT',
            'STOCK ON HAND',
        ];
    }

    public function map($row): array
    {
        return [
            $row->warehouse_code,
            $row->warehouse_name,
            $row->item_code,
            $row->item_name,
            $row->received,
            $row->misc_receipt,
            $row->misc_issue,
            $row->move_order,
            $row->transfer_in,
            $row->transfer_out,
            $row->stock_on_hand,
        ];
    }
}
