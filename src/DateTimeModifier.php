<?php
namespace SoulDoit\DataTableTwo;

use Illuminate\Support\Facades\DB;

class DateTimeModifier{
    private static $is_constructed=false;
    private static $timezone;

    public static function construct()
    {
        if(!self::$is_constructed){
            self::$timezone = config('sd-datatable-two-ssp.default_modifier_timezone', 'UTC');
            self::$is_constructed = true;
        }
    }

    public static function getDateTimeCarbon($datetime)
    {
        if($datetime instanceof \Carbon\Carbon) return $datetime->copy()->tz(self::$timezone);
        else return \Carbon\Carbon::parse($datetime)->copy()->tz(self::$timezone);
        
        return null;
    }
    
    public static function getMysqlQueryTzRaw($value, $timezone_from=null)
    {
        if($timezone_from) $from_datetime_carbon = now($timezone_from);
        else $from_datetime_carbon = now();

        return DB::raw("CONVERT_TZ($value, '".$timezone_from->format("P")."', '".now(self::$timezone)->format("P")."')");
    }
}

DateTimeModifier::construct();