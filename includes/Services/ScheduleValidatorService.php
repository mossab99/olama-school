<?php
/**
 * Service to validate if a specific date matches the day of the week in the school schedule.
 */

namespace Olama\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ScheduleValidatorService
{
    /**
     * Maps PHP day numeric representation to English day names used in the DB.
     * Note: Olama uses day names like 'Sunday', 'Monday', etc. in olama_schedule.
     */
    private static $day_map = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    ];

    /**
     * Validates if a visit date matches the scheduled day.
     * 
     * @param string $date YYYY-MM-DD
     * @param string $schedule_day_name e.g., 'Sunday'
     * @return bool
     */
    public static function validate_date_matches_day($date, $schedule_day_name)
    {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return false;
        }

        $day_index = (int) date('w', $timestamp);
        $actual_day_name = self::$day_map[$day_index];

        return strcasecmp($actual_day_name, $schedule_day_name) === 0;
    }

    /**
     * Checks if a visit date is in the past.
     * 
     * @param string $date YYYY-MM-DD
     * @return bool
     */
    public static function is_past_date($date)
    {
        $visit_date = new \DateTime($date);
        $today = new \DateTime('today');

        return $visit_date < $today;
    }
}
