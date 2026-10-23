<?php

namespace App\Models;

use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sync_id',
    'code',
    'name',
    'company_code',
    'company_name',
    'business_unit_code',
    'business_unit_name',
    'department_code',
    'department_name',
    'unit_code',
    'unit_name',
    'sub_unit_code',
    'sub_unit_name',
    'location_code',
    'location_name',
    'deleted_at'
])]
class Charge extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = \App\Filters\ChargeFilters::class;
}
