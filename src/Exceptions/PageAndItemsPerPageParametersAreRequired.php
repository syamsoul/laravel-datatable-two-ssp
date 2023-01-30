<?php

namespace SoulDoit\DataTableTwo\Exceptions;

use InvalidArgumentException;

class PageAndItemsPerPageParametersAreRequired extends InvalidArgumentException
{
    public static function create(string $page_param_name, string $items_per_page_param_name)
    {
        return new static("`$page_param_name` and `$items_per_page_param_name` parameters are required.");
    }
}
