<?php

namespace App\Models;

use App\Filters\MiscellaneousReceiptFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class MiscellaneousReceipt extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = MiscellaneousReceiptFilters::class;

    public function stocks() : BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_id', 'id');
    }
}
