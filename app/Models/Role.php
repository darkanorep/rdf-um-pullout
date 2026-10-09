<?php

namespace App\Models;

use App\Filters\RoleFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Role extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = RoleFilters::class;

    protected $casts = [
        'permissions' => 'array',
    ];
}
