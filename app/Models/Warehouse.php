<?php

namespace App\Models;

use App\Filters\WarehouseFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Warehouse extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = WarehouseFilters::class;
}
