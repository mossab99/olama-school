<?php
/**
 * Olama School Helpers Class
 * Shared utility functions used across the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Helpers
{
    /**
     * Calculate progress status based on plan date vs lesson dates
     * 
     * @param string $plan_date The date the plan was executed
     * @param string $start_date The lesson start date
     * @param string $end_date The lesson end date
     * @return array|null Status array with 'label' and 'class' keys
     */
    public static function get_progress_status($plan_date, $start_date, $end_date)
    {
        if (!$start_date || !$end_date) {
            return null;
        }

        $plan_ts = strtotime($plan_date);
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);

        if ($plan_ts >= $start_ts && $plan_ts <= $end_ts) {
            return array('label' => __('On-time', 'olama-school'), 'class' => 'status-ontime');
        } elseif ($plan_ts > $end_ts) {
            $days = ceil(($plan_ts - $end_ts) / 86400);
            return array('label' => sprintf(__('Delayed by %d days', 'olama-school'), $days), 'class' => 'status-delayed');
        } else {
            $days = ceil(($start_ts - $plan_ts) / 86400);
            return array('label' => sprintf(__('Bypass by %d days', 'olama-school'), $days), 'class' => 'status-bypass');
        }
    }

    /**
     * Get week date range from a given date
     * 
     * @param string $date Any date within the week
     * @return array Array with 'start' and 'end' keys
     */
    public static function get_week_range($date)
    {
        $ts = strtotime($date);
        $day_of_week = date('w', $ts);
        $week_start = date('Y-m-d', $ts - ($day_of_week * 86400));
        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));

        return array(
            'start' => $week_start,
            'end' => $week_end
        );
    }

    /**
     * Filter grades/sections by teacher assignment for non-admin users
     * 
     * @param array $items Array of grade or section objects
     * @param int $user_id The user ID to filter for
     * @param string $type Either 'grades' or 'sections'
     * @param int $grade_id Optional grade ID for section filtering
     * @return array Filtered array
     */
    public static function filter_by_assignment($items, $user_id, $type = 'grades', $grade_id = 0)
    {
        if (current_user_can('manage_options')) {
            return $items;
        }

        global $wpdb;

        if ($type === 'grades') {
            $assigned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT grade_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d",
                $user_id
            ));
        } else {
            $assigned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d AND grade_id = %d",
                $user_id,
                $grade_id
            ));
        }

        $filtered = array_filter($items, function ($item) use ($assigned_ids) {
            return in_array($item->id, $assigned_ids);
        });

        return array_values($filtered);
    }
}
