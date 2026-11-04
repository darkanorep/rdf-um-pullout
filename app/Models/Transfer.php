<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Transfer extends Model
{
    use SoftDeletes;

    public function bondedWarehouse() {
        return $this->belongsTo(BondedWarehouse::class);
    }

    public function items() {
        return $this->hasMany(TransferItem::class);
    }
}
