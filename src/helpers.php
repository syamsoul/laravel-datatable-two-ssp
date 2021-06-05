<?php

if (!function_exists('isNotLumen')) {
    function isNotLumen() : bool
    {
        return ! preg_match('/lumen/i', app()->version());
    }
}
