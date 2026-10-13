<?php

namespace App\Http\Controllers\Controllers;

use App\Http\Requests\ItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Pagination\LengthAwarePaginator;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService) {}

    public function index()
    {
        $items = $this->itemService->getAllItems();

        if (empty($items->items())) {
            return $this->responseNotFound(message: 'No items found.');
        }

        return $items instanceof LengthAwarePaginator
            ? $items->through(fn($item) => new ItemResource($item))
            : $this->responseSuccess('Roles retrieved successfully.', ItemResource::collection($items));
    }

    public function store(ItemRequest $request) {
        $data = $request->validated();
        $item = $this->itemService->storeItem($data);

        return $this->responseCreated(
            message: 'Item created successfully.',
            data: new ItemResource($item)
        );
    }

    public function show(int $id)
    {
        $item = $this->itemService->findById($id);

        if (! $item) {
            return $this->responseNotFound(message: 'Item not found.');
        }

        return $this->responseSuccess(
            message: 'Item retrieved successfully.',
            data: new ItemResource($item)
        );
    }

    public function update(ItemRequest $request, Item $item)
    {
        $data = $request->validated();

        $updatedItem = $this->itemService->updateItem($data, $item);

        return $this->responseAccepted(
            message: 'Role updated successfully.',
            data: new ItemResource($updatedItem)
        );
    }

    public function destroy(int $id)
    {
        $this->itemService->destroyItem($id);

        return $this->responseAccepted(
            message: 'Item status updated successfully.'
        );
    }
}
