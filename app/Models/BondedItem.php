<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
class BondedItem extends Model
{
    public function bondedWarehouse() {
        return $this->belongsTo(BondedWarehouse::class);
    }
    public function inventory()
    {
        return $this->belongsTo(Inventory::class)->withTrashed();
    }
    public function transferItem() {
        return $this->hasOne(TransferItem::class);
    }
}
