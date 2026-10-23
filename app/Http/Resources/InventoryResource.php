<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item' => new ItemResource($this->item),
            'uom' => new UnitOfMeasurementResource($this->uom),
            'quantity_received' => $this->quantity_received,
            'actual_received' => $this->actual_received,
            'account_title' => new AccountTitleResource($this->accountTitle),
        ];
    }
}
