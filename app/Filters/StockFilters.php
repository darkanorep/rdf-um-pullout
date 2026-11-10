<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class StockFilters extends QueryFilters
{
    protected array $allowedFilters = ['item_code', 'item_name'];

    protected array $columnSearch = ['item_code', 'item_name'];
}
