<?php

namespace App\Models;

use App\Filters\MiscellaneousFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Miscellaneous extends Model
{
    use SoftDeletes, Filterable;

    protected $table = 'miscellaneous';

    protected string $default_filters = MiscellaneousFilters::class;

    public function miscellaneousIssue(): HasMany
    {
        return $this->hasMany(MiscellaneousIssue::class);
    }
}
