<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Category extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = \App\Filters\CategoryFilters::class;
}
