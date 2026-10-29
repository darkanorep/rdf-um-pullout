<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveOrderRequest;
use App\Http\Resources\MoveOrderResource;
use App\Models\MoveOrder;
use App\Services\MoveOrderService;
use Illuminate\Http\Request;

class MoveOrderController extends Controller
{
    public function __construct(private readonly MoveOrderService $moveOrderService) {}

    public function index()
    {
        $moveOrders = $this->moveOrderService->getMoveOrders();

        if ($moveOrders->isEmpty()) {
            return $this->responseNotFound(message: 'No move orders found.');
        }

        return $this->responseSuccess(
            message: 'Move orders retrieved successfully.',
            data: MoveOrderResource::collection($moveOrders)
        );
    }

    public function store(MoveOrderRequest $request) {
        $data = $request->validated();

        $moveOrder = $this->moveOrderService->storeMoveOrder($data);

        return $this->responseCreated(
            message: 'Move Order created successfully.',
            data: new MoveOrderResource($moveOrder)
        );
    }

    public function show(int $id) {
        $moveOrder = $this->moveOrderService->findById($id);

        if (! $moveOrder) {
            return $this->responseNotFound(message: 'Move order not found.');
        }

        return $this->responseSuccess(
            message: 'Move order retrieved successfully.',
            data: new MoveOrderResource($moveOrder)
        );
    }

    public function update(MoveOrderRequest $request, MoveOrder $moveOrder) {
        $data = $request->validated();
        $moveOrder = $this->moveOrderService->updateMoveOrder($data, $moveOrder);

        return $this->responseAccepted(
            message: 'Move order updated successfully.',
            data: new MoveOrderResource($moveOrder)
        );
    }

    public function destroy(int $id)
    {
        $this->moveOrderService->deleteMoveOrder($id);

        return $this->responseAccepted(
            message: 'Move order status updated successfully.'
        );
    }
}
