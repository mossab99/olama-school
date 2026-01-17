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
    public static function get_scheduled_sections()
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT DISTINCT s.section_id, s.semester_id, sec.section_name, sem.semester_name, g.grade_name, g.id as grade_id
            FROM {$wpdb->prefix}olama_schedule s
            JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
            JOIN {$wpdb->prefix}olama_semesters sem ON s.semester_id = sem.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            ORDER BY sem.semester_name ASC, g.grade_level ASC, sec.section_name ASC"
        );
    }

    /**
     * Get master schedule by section and semester
     */
    public static function get_schedule($section_id, $semester_id)
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, subj.subject_name, subj.color_code 
            FROM {$wpdb->prefix}olama_schedule s 
            JOIN {$wpdb->prefix}olama_subjects subj ON s.subject_id = subj.id 
            WHERE s.section_id = %d AND s.semester_id = %d",
            $section_id,
            $semester_id
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
            ),
            array('%d', '%d', '%s', '%d', '%d')
        );

        return $result !== false;
    }

    /**
     * Delete schedule item
     */
    public static function delete_schedule_item($semester_id, $section_id, $day_name, $period_number)
    {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}olama_schedule",
            array(
                'semester_id' => $semester_id,
                'section_id' => $section_id,
                'day_name' => $day_name,
                'period_number' => $period_number,
            )
        );
    }

    /**
     * Save bulk schedule
     */
    public static function save_bulk_schedule($section_id, $semester_id, $data)
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
                        'period_number' => $period_no
                    ));
                } else {
                    $wpdb->replace($table, array(
                        'semester_id' => $semester_id,
                        'section_id' => $section_id,
                        'day_name' => $day,
                        'period_number' => $period_no,
                        'subject_id' => intval($subject_id)
                    ));
                }
            }
        }
        return true;
    }

    /**
     * Get unique subjects for a specific day and section
     */
    public static function get_unique_subjects_for_day($section_id, $day_name, $semester_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT subj.id, subj.subject_name, subj.color_code 
            FROM {$wpdb->prefix}olama_schedule s 
            JOIN {$wpdb->prefix}olama_subjects subj ON s.subject_id = subj.id 
            WHERE s.section_id = %d AND s.day_name = %s AND s.semester_id = %d
            ORDER BY subj.subject_name ASC",
            $section_id,
            $day_name,
            $semester_id
        ));
    }
}