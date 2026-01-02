<?php
/**
 * Student Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Student
{

    /**
     * Get all students with caching
     */
    public static function get_students()
    {
        // Check cache first
        $cache_key = 'olama_students_list';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $results = $wpdb->get_results("SELECT s.*, g.grade_name, sec.section_name 
			FROM {$wpdb->prefix}olama_students s 
			LEFT JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
			LEFT JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id");

        // Cache for 5 minutes
        set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Add student
     */
    public static function add_student($data)
    {
        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}olama_students",
            array(
                'student_name' => $data['student_name'],
                'student_id_number' => $data['student_id_number'],
                'grade_id' => $data['grade_id'],
                'section_id' => $data['section_id'],
            )
        );

        // Invalidate cache
        delete_transient('olama_students_list');

        return $result;
    }
}
