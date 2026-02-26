<?php

namespace App\Helpers;

class TimeValidator
{
    public static function validateFormat($time)
    {
        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time);
    }
    
    public static function isValidTimeRange($startTime, $endTime)
    {
        if (!self::validateFormat($startTime) || !self::validateFormat($endTime)) {
            return false;
        }
        
        $startParts = explode(':', $startTime);
        $endParts = explode(':', $endTime);
        $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
        $endMinutes = (int)$endParts[0] * 60 + (int)$endParts[1];
        
        return $endMinutes > $startMinutes;
    }
}
