<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockRequest;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

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
}
