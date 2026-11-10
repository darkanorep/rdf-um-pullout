<?php

namespace App\Exports;

use App\Services\StockService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockHistoryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private readonly int $userId) {}

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return app(\App\Services\StockHistorySummaryService::class)
            ->stockOnHandQuery($this->userId);
    }

    public function headings(): array
    {
        return [
            'ID',
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
            $row->item_id,
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
