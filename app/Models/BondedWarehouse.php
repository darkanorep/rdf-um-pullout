<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class BondedWarehouse extends Model
{
    use SoftDeletes;

    public function items() {
        return $this->hasMany(BondedItem::class);
    }
    public function sender() {
        return $this->belongsTo(User::class, 'transferred_by');
    }
    public function recipient() {
        return $this->belongsTo(User::class, 'transferred_to');
    }
    public function transfer() {
        return $this->hasOne(Transfer::class);
    }

}
