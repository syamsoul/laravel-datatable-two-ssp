<?php

namespace SoulDoit\DataTableTwo\Exceptions;

use InvalidArgumentException;

class DbFakeMustHaveFormatter extends InvalidArgumentException
{
    public static function create(string $dbFake)
    {
        return new static("The given DB Fake ($dbFake) should have formatter (e.g `['formatter' => function (\$m) { return \$m->id; }]`).");
    }
}