<?php

namespace App\Models;

use App\Filters\StockFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Stock extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = StockFilters::class;

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
