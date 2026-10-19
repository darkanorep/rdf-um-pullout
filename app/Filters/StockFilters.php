<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class StockFilters extends QueryFilters
{
    protected array $allowedFilters = ['mode', 'supplier_name', 'remarks'];

    protected array $columnSearch = ['mode', 'supplier_name', 'remarks'];
}
