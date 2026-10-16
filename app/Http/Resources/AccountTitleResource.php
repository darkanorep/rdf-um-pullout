<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTitleResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'account_type_name' => $this->account_type_name,
            'account_group_name' => $this->account_group_name,
            'account_sub_group_name' => $this->account_sub_group_name,
            'financial_statement_name' => $this->financial_statement_name,
            'normal_balance_name' => $this->normal_balance_name,
            'allocation_name' => $this->allocation_name,
            'account_unit_name' => $this->account_unit_name,
            'charge' => $this->charge,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
