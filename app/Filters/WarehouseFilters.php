<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class WarehouseFilters extends BaseFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];
}
