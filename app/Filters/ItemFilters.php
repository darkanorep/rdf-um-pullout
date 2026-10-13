<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class ItemFilters extends BaseFilters
{
    protected array $allowedFilters = ['code', 'name', 'slug'];

    protected array $columnSearch = ['code', 'name', 'slug'];
}
