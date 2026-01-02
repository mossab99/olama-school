<?php
/**
 * Subject Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Subject
{
    private static $cache = array();


    /**
     * Get all subjects
     */
    public static function get_subjects()
    {
        if (isset(self::$cache['all_subjects'])) {
            return self::$cache['all_subjects'];
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT s.*, g.grade_name FROM {$wpdb->prefix}olama_subjects s JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id");
        self::$cache['all_subjects'] = $results;
        return $results;
    }

    /**
     * Get subjects by grade
     */
    public static function get_by_grade($grade_id)
    {
        if (isset(self::$cache['subjects_grade_' . $grade_id])) {
            return self::$cache['subjects_grade_' . $grade_id];
        }
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE grade_id = %d",
            $grade_id
        ));
        self::$cache['subjects_grade_' . $grade_id] = $results;
        return $results;
    }

    /**
     * Add subject
     */
    public static function add_subject($data)
    {
        global $wpdb;
        return $wpdb->insert(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
            )
        );
    }

    /**
     * Get single subject
     */
    public static function get_subject($id)
    {
        if (isset(self::$cache['subject_' . $id])) {
            return self::$cache['subject_' . $id];
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE id = %d",
            $id
        ));
        self::$cache['subject_' . $id] = $row;
        return $row;
    }

    /**
     * Update subject
     */
    public static function update_subject($id, $data)
    {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
            ),
            array('id' => $id)
        );
    }

    /**
     * Delete subject
     */
    public static function delete_subject($id)
    {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}olama_subjects",
            array('id' => $id)
        );
    }
}
