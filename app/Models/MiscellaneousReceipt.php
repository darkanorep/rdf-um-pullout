<?php

namespace App\Models;

use App\Filters\MiscellaneousReceiptFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class MiscellaneousReceipt extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = MiscellaneousReceiptFilters::class;

    public function miscellaneousInventory(): HasMany
    {
        return $this->hasMany(
            MiscellaneousInventory::class,
            'misc_receipt_id',
            'id'
        );
    }
}
