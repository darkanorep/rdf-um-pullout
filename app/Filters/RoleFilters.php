<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class RoleFilters extends BaseFilters
{
    protected array $allowedFilters = ['name'];

    protected array $columnSearch = ['name'];
}
