<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;
use Carbon\Carbon;

class DateTimeModifier
{
    private static $is_constructed=false;
    private static $timezone;

    public static function construct()
    {
        if (!self::$is_constructed) {
            self::$timezone = config('sd-datatable-two-ssp.default_modifier_timezone', 'UTC');
            self::$is_constructed = true;
        }
    }

    public static function setTimezone(string $timezone)
    {
        self::$timezone = $timezone;
    }

    public static function getTimezone() : string
    {
        return self::$timezone;
    }

    public static function getDateTimeCarbon($datetime = null)
    {
        if ($datetime === null) return now(self::$timezone);

        if ($datetime instanceof Carbon) return $datetime->copy()->tz(self::$timezone);
        else return Carbon::parse($datetime)->copy()->tz(self::$timezone);
        
        return null;
    }
    
    public static function getMysqlQueryTzRaw(string $value, string $timezone_from = null): string
    {
        if ($timezone_from) $from_datetime_carbon = now($timezone_from);
        else $from_datetime_carbon = now();

        return "CONVERT_TZ($value, '".$from_datetime_carbon->format("P")."', '".now(self::$timezone)->format("P")."')";
    }

    public static function getMysqlQueryTzRawDB(string $value, string $timezone_from=null): Expression
    {
        return DB::raw(self::getMysqlQueryTzRaw($value, $timezone_from));
    }
}

DateTimeModifier::construct();