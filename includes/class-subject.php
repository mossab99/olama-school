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
     * Clear the internal cache
     */
    public static function clear_cache()
    {
        self::$cache = array();
    }


    /**
     * Get all subjects
     */
    public static function get_subjects($active_only = false)
    {
        $cache_key = $active_only ? 'all_subjects_active' : 'all_subjects';
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        global $wpdb;
        $where = $active_only ? " WHERE s.is_active = 1" : "";
        $results = $wpdb->get_results("SELECT s.*, g.grade_name FROM {$wpdb->prefix}olama_subjects s JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id" . $where);
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Get subjects by grade
     */
    public static function get_by_grade($grade_id, $active_only = false)
    {
        $cache_key = 'subjects_grade_' . $grade_id . ($active_only ? '_active' : '');
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        global $wpdb;
        $where = $active_only ? " AND is_active = 1" : "";
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE grade_id = %d" . $where,
            $grade_id
        ));
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Alias for get_by_grade
     */
    public static function get_subjects_by_grade($grade_id, $active_only = false)
    {
        return self::get_by_grade($grade_id, $active_only);
    }

    /**
     * Add subject
     */
    public static function add_subject($data)
    {
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            )
        );
        self::clear_cache();
        return $result;
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
        $result = $wpdb->update(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            ),
            array('id' => $id)
        );
        self::clear_cache();
        return $result;
    }

    /**
     * Delete subject
     */
    public static function delete_subject($id)
    {
        global $wpdb;
        $result = $wpdb->delete(
            "{$wpdb->prefix}olama_subjects",
            array('id' => $id)
        );
        self::clear_cache();
        return $result;
    }
}