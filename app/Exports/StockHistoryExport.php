<?php

namespace App\Exports;

use App\Services\StockHistorySummaryService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockHistoryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(private readonly int $userId) {}

    public function query(): Builder
    {
        return app(StockHistorySummaryService::class)
            ->stockHistorySummaryQuery($this->userId);
    }

    public function headings(): array
    {
        return [
            'ITEM ID',
            'ITEM CODE',
            'ITEM DESCRIPTION',
            'UOM',
            'ITEM CATEGORY',
            'UNIT COST',
            'TOTAL INVENTORY COST',
            'SOH',
            'PREPARED QUANTITY',
            'RESERVE',
            'BUFFER LEVEL',
            'RECEIVE',
            'MISCELLANEOUS RECEIPT',
            'MOVE ORDER',
            'MISCELLANEOUS ISSUE',
            'BORROWED',
            'RETURNED',
            'CONSUMED',
            'SUGGESTED PO',
            'RESERVE USAGE',
        ];
    }

    public function map($row): array
    {
        return [
            $row->item_id,
            $row->item_code,
            $row->item_name,
            $this->valueOrZero($row, 'uom'),
            $this->valueOrZero($row, 'item_category'),
            $this->valueOrZero($row, 'unit_cost'),
            $this->valueOrZero($row, 'total_inventory_cost'),
            $this->valueOrZero($row, 'stock_on_hand'),
            $this->valueOrZero($row, 'prepared_quantity'),
            $this->valueOrZero($row, 'reserve'),
            $this->valueOrZero($row, 'buffer_level'),
            $this->valueOrZero($row, 'received'),
            $this->valueOrZero($row, 'misc_receipt'),
            $this->valueOrZero($row, 'move_order'),
            $this->valueOrZero($row, 'misc_issue'),
            $this->valueOrZero($row, 'borrowed'),
            $this->valueOrZero($row, 'returned'),
            $this->valueOrZero($row, 'consumed'),
            $this->valueOrZero($row, 'suggested_po'),
            $this->valueOrZero($row, 'reserve_usage'),
        ];
    }

    private function valueOrZero(object $row, string $field): mixed
    {
        $value = $row->{$field} ?? null;

        return $value === null || (is_string($value) && trim($value) === '')
            ? '0'
            : $value;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F4E78'],
                ],
            ],
        ];
    }
}
