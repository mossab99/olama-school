<?php
/**
 * Master Schedule Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Schedule
{

    /**
     * Get all sections that have a schedule defined
     */
    public static function get_scheduled_sections($schedule_type = 'normal')
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT s.section_id, s.semester_id, sec.section_name, sem.semester_name, g.grade_name, g.id as grade_id
            FROM {$wpdb->prefix}olama_schedule s
            JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
            JOIN {$wpdb->prefix}olama_semesters sem ON s.semester_id = sem.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            WHERE s.schedule_type = %s
            ORDER BY sem.semester_name ASC, g.grade_level ASC, sec.section_name ASC",
            $schedule_type
        ));
    }

    /**
     * Get master schedule by section and semester
     */
    public static function get_schedule($section_id, $semester_id, $schedule_type = 'normal')
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, subj.subject_name, subj.color_code 
            FROM {$wpdb->prefix}olama_schedule s 
            JOIN {$wpdb->prefix}olama_subjects subj ON s.subject_id = subj.id 
            WHERE s.section_id = %d AND s.semester_id = %d AND s.schedule_type = %s",
            $section_id,
            $semester_id,
            $schedule_type
        ));

        $schedule = [];
        foreach ($results as $row) {
            $schedule[$row->day_name][$row->period_number] = $row;
        }

        return $schedule;
    }

    /**
     * Save schedule item
     */
    public static function save_schedule_item($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_schedule";

        $result = $wpdb->replace(
            $table,
            array(
                'semester_id' => intval($data['semester_id']),
                'section_id' => intval($data['section_id']),
                'day_name' => sanitize_text_field($data['day_name']),
                'period_number' => intval($data['period_number']),
                'subject_id' => intval($data['subject_id']),
                'schedule_type' => sanitize_text_field($data['schedule_type'] ?? 'normal'),
            ),
            array('%d', '%d', '%s', '%d', '%d', '%s')
        );

        return $result !== false;
    }

    /**
     * Delete schedule item
     */
    public static function delete_schedule_item($semester_id, $section_id, $day_name, $period_number, $schedule_type = 'normal')
    {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}olama_schedule",
            array(
                'semester_id' => $semester_id,
                'section_id' => $section_id,
                'day_name' => $day_name,
                'period_number' => $period_number,
                'schedule_type' => $schedule_type,
            )
        );
    }

    /**
     * Save bulk schedule
     */
    public static function save_bulk_schedule($section_id, $semester_id, $data, $schedule_type = 'normal')
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_schedule";

        foreach ($data as $day => $periods) {
            foreach ($periods as $period_no => $subject_id) {
                if (empty($subject_id)) {
                    $wpdb->delete($table, array(
                        'semester_id' => $semester_id,
                        'section_id' => $section_id,
                        'day_name' => $day,
                        'period_number' => $period_no,
                        'schedule_type' => $schedule_type
                    ));
                } else {
                    $wpdb->replace($table, array(
                        'semester_id' => $semester_id,
                        'section_id' => $section_id,
                        'day_name' => $day,
                        'period_number' => $period_no,
                        'subject_id' => intval($subject_id),
                        'schedule_type' => $schedule_type
                    ));
                }
            }
        }
        return true;
    }

    /**
     * Get unique subjects for a specific day and section
     */
    public static function get_unique_subjects_for_day($section_id, $day_name, $semester_id, $plan_date = null)
    {
        global $wpdb;
        $schedule_type = self::is_ramadan($plan_date) ? 'ramadan' : 'normal';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT subj.id, subj.subject_name, subj.color_code 
            FROM {$wpdb->prefix}olama_schedule s 
            JOIN {$wpdb->prefix}olama_subjects subj ON s.subject_id = subj.id 
            WHERE s.section_id = %d AND s.day_name = %s AND s.semester_id = %d
            AND s.schedule_type = %s
            AND subj.is_active = 1
            ORDER BY subj.subject_name ASC",
            $section_id,
            $day_name,
            $semester_id,
            $schedule_type
        ));
    }

    /**
     * Check if a date is within the Ramadan range
     */
    public static function is_ramadan($date = null)
    {
        if (!$date) {
            $date = current_time('Y-m-d');
        }

        $settings = get_option('olama_school_settings', array());
        $ramadan_start = $settings['ramadan_start'] ?? null;
        $ramadan_end = $settings['ramadan_end'] ?? null;

        if (!$ramadan_start || !$ramadan_end) {
            return false;
        }

        $current_ts = strtotime($date);
        $start_ts = strtotime($ramadan_start);
        $end_ts = strtotime($ramadan_end);

        return $current_ts >= $start_ts && $current_ts <= $end_ts;
    }

    /**
     * Clone schedule from one type to another
     */
    public static function clone_schedule($section_id, $semester_id, $from_type, $to_type)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_schedule";

        // First clear the target schedule
        $wpdb->delete($table, array(
            'section_id' => $section_id,
            'semester_id' => $semester_id,
            'schedule_type' => $to_type
        ));

        // Insert from source to target
        $query = $wpdb->prepare(
            "INSERT INTO $table (semester_id, section_id, day_name, period_number, subject_id, schedule_type)
            SELECT semester_id, section_id, day_name, period_number, subject_id, %s
            FROM $table
            WHERE section_id = %d AND semester_id = %d AND schedule_type = %s",
            $to_type,
            $section_id,
            $semester_id,
            $from_type
        );

        return $wpdb->query($query) !== false;
    }
}