<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class UnitOfMeasurementFilters extends BaseFilters
{
    protected array $allowedFilters = ['symbol', 'name'];

    protected array $columnSearch = ['symbol', 'name'];
}
