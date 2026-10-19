<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Inventory extends Model
{
    use SoftDeletes;

    public function stock(): belongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo {
        return $this->belongsTo(UnitOfMeasurement::class, 'uom_id');
    }

    public function accountTitle(): BelongsTo {
        return $this->belongsTo(AccountTitle::class, 'account_title_id');
    }
}
