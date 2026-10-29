<?php

namespace App\Models;

use App\Filters\ItemFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Item extends Model
{
    use SoftDeletes, Filterable, HasFactory;

    protected string $default_filters = ItemFilters::class;

    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    public function category(): BelongsTo {
        return $this->belongsTo(Category::class);
    }

    public function unitOfMeasurement(): BelongsTo {
        return $this->belongsTo(UnitOfMeasurement::class, 'uom_id');
    }
}
