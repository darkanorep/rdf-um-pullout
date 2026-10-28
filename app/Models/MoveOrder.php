<?php

namespace App\Models;

use App\Filters\MoverOrderFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class MoveOrder extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = MoverOrderFilters::class;

    public function moveInventory(): HasMany
    {
        return $this->hasMany(
            MoveInventory::class,
            'move_order_id',
            'id'
        );
    }
}
