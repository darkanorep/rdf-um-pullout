<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class MoveInventory extends Model
{
    use SoftDeletes;

    public function inventory(): BelongsTo {
        return $this->belongsTo(Inventory::class);
    }
}
