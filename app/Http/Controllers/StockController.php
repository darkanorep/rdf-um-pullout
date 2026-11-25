<?php

namespace App\Http\Controllers;

use App\Exports\StockHistoryExport;
use App\Exports\StockWarehouseHistoryExport;
use App\Http\Requests\StockRequest;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Services\StockHistorySummaryService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly StockHistorySummaryService $stockHistorySummaryService
    ) {}

    public function index() {
        $stocks = $this->stockService->getStock();

        if ($stocks->isEmpty()) {
            return $this->responseNotFound(message: 'No stocks found.');
        }

        return $stocks instanceof LengthAwarePaginator
            ? $stocks->through(fn($item) => new StockResource($item))
            : $this->responseSuccess('Stocks retrieved successfully.', StockResource::collection($stocks));
    }

    public function store(StockRequest $request) {
        $data = $request->validated();
        $stock = $this->stockService->storeStock($data);

        return $this->responseCreated(
            message: 'Stock created successfully.',
            data: new StockResource($stock)
        );
    }

    public function show(int $id)
    {
        $stock = $this->stockService->findById($id);

        if (! $stock) {
            return $this->responseNotFound(message: 'Stock not found.');
        }

        return $this->responseSuccess(
            message: 'Stock retrieved successfully.',
            data: new StockResource($stock)
        );
    }

    public function update(StockRequest $request, Stock $stock)
    {
        $data = $request->validated();

        $updatedRole = $this->stockService->updateStock($data, $stock);

        return $this->responseAccepted(
            message: 'Stock updated successfully.',
            data: new StockResource($updatedRole)
        );
    }

    public function destroy(int $id)
    {
        $this->stockService->destroyStock($id);

        return $this->responseAccepted(
            message: 'Stock status updated successfully.'
        );
    }

    public function getStockOnHand()
    {
        $stockOnHand = $this->stockService->getStockOnHand();

        if ($stockOnHand->isEmpty()) {
            return $this->responseNotFound(message: 'No stock on hand found.');
        }

        return $this->responseSuccess(
            message: 'Stock on hand retrieved successfully.',
            data: $stockOnHand
        );
    }

    public function getWarehouseStockOnHand(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $stockOnHand = $this->stockService->getStockOnHandByWarehouse(
            (int) $validated['warehouse_id']
        );

        if ($stockOnHand['items']->isEmpty()) {
            return $this->responseNotFound(
                message: 'No stock on hand found for the specified warehouse.'
            );
        }

        return $this->responseSuccess(
            message: 'Stock on hand for the specified warehouse retrieved successfully.',
            data: $stockOnHand
        );
    }

    public function stockOnHandWarehouseSummary(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
        ]);

        $summary = $this->stockHistorySummaryService
            ->getStockOnHandSummaryForAllWarehouses(
                data_get($validated, 'warehouse_id')
            );

        if ($summary->isEmpty()) {
            return $this->responseNotFound(
                message: 'No warehouse stock summary found.'
            );
        }

        return $this->responseSuccess(
            message: 'Warehouse stock summary retrieved successfully.',
            data: $summary
        );
    }

    public function stockOnHandSummary(Request $request)
    {
        $summary = $this->stockHistorySummaryService
            ->getStockOnHandSummary($request->boolean('include_history'));

        if ($summary->isEmpty()) {
            return $this->responseNotFound(
                message: 'No stock on hand summary found.'
            );
        }

        return $this->responseSuccess(
            message: 'Stock on hand summary retrieved successfully.',
            data: $summary
        );
    }

    public function exportStockHistory(Request $request)
    {
        return Excel::download(
            new StockHistoryExport((int) $request->user()->id),
            'stock-history.xlsx'
        );
    }

    public function exportStockWarehouseSummary(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        return Excel::download(
            new StockWarehouseHistoryExport((int) $validated['warehouse_id']),
            'warehouse-stock-summary.xlsx'
        );
    }

    public function truncate()
    {
        $this->stockService->truncateStocks();

        return $this->responseAccepted(
            message: 'Stocks truncated successfully.'
        );
    }
}
