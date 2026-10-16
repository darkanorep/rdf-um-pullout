<?php

namespace App\Models;

use App\Filters\AccountTitleFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sync_id',
    'code',
    'name',
    'account_type_name',
    'account_group_name',
    'account_sub_group_name',
    'financial_statement_name',
    'normal_balance_name',
    'allocation_name',
    'account_unit_name',
    'charge',
    'deleted_at'
])]
class AccountTitle extends Model
{
    use SoftDeletes, Filterable;

    protected $fillable = [
        'code',
        'sync_id',
    ];

    protected string $default_filters = AccountTitleFilters::class;

}
