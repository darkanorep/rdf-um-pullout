<?php

namespace App\Http\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    protected bool $excludeReceivedQuantities = false;

    public function withoutReceivedQuantities(): static
    {
        $this->excludeReceivedQuantities = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'item' => new ItemResource($this->item),
            'uom' => new UnitOfMeasurementResource($this->uom),
            'quantity_received' => $this->quantity_received,
            'actual_received' => $this->actual_received,
            'account_title' => new AccountTitleResource($this->accountTitle),
        ];

        if ($this->excludeReceivedQuantities) {
            unset(
                $data['quantity_received'],
                $data['actual_received']
            );
        }
        return $data;
    }
}
