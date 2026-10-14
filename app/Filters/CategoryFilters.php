<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class CategoryFilters extends BaseFilters
{
    protected array $allowedFilters = ['code', 'name'];

    protected array $columnSearch = ['code', 'name'];
}
