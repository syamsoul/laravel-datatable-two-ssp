<?php

namespace SoulDoit\DataTableTwo\Exceptions;

use InvalidArgumentException;

class InvalidItemsPerPageValue extends InvalidArgumentException
{
    public static function create(string $items_per_page, array $allowed_items_per_page)
    {
        return new static("The given `items per page` value ($items_per_page) are not allowed. Only [".implode(',', $allowed_items_per_page)."] are allowed.");
    }
}
