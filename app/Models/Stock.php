<?php

namespace App\Models;

use App\Filters\StockFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded([])]
class Stock extends Model
{
    use Filterable, SoftDeletes;

    protected string $default_filters = StockFilters::class;

    protected static function booted(): void
    {
        static::creating(function (Stock $stock): void {
            $prefix = now()->format('ymd');
            $lastBatchNumber = static::query()
                ->where('batch_no', 'like', $prefix.'-%')
                ->max('batch_no');
            $nextNumber = $lastBatchNumber
                ? ((int) substr($lastBatchNumber, strlen($prefix) + 1)) + 1
                : 1;

            $stock->batch_no = $prefix.'-'.str_pad((string) $nextNumber, 5            , '0', STR_PAD_LEFT);
        });
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
