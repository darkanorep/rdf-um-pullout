<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiscellaneousReceiptResource extends JsonResource
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
            'transaction_date' => $this->transaction_date,
            'description' => $this->description,
            'remarks' => $this->remarks,
            'miscellaneous_inventory' => MiscellaneousInventoryResource::collection($this->whenLoaded('miscellaneousInventory')),
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
