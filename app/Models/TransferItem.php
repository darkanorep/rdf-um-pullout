<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class TransferItem extends Model
{
    public function transfer() {
        return $this->belongsTo(Transfer::class);
    }
    public function bondedItem() {
        return $this->belongsTo(BondedItem::class);
    }
    public function originInventory()
    {
        return $this->belongsTo(Inventory::class, 'origin_inventory_id')
            ->withTrashed();
    }
    public function receivedInventory() {
        return $this->belongsTo(Inventory::class, 'received_inventory_id');
    }
}
