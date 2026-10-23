<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiscellaneousInventoryResource extends JsonResource
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
            'quantity' => $this->quantity,
            'account_title' => new AccountTitleResource($this->accountTitle),
        ];
    }
}
