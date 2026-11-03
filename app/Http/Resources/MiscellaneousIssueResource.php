<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiscellaneousIssueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            (new InventoryResource($this->inventory))
                ->withoutReceivedQuantities()
                ->toArray($request),
            [
                'quantity' => $this->quantity,
            ]
        );
    }
}
