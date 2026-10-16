<?php

namespace App\Models;

use App\Filters\AccountTitleFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class AccountTitle extends Model
{
    use SoftDeletes, Filterable;

    protected $fillable = [
        'code',
        'sync_id',
    ];

    protected string $default_filters = AccountTitleFilters::class;

}
