<?php

declare(strict_types=1);

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class MoverOrderFilters extends BaseFilters
{
    protected array $allowedFilters = ['description', 'remark'];

    protected array $columnSearch = ['description', 'remark'];
}
