<?php
/**
 * Grade Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Grade
{
    private static $cache = array();


    /**
     * Get all grades
     */
    public static function get_grades()
    {
        if (isset(self::$cache['all_grades'])) {
            return self::$cache['all_grades'];
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_grades ORDER BY CAST(grade_level AS UNSIGNED) ASC");
        self::$cache['all_grades'] = $results;
        return $results;
    }

    /**
     * Add grade
     */
    public static function add_grade($data)
    {
        global $wpdb;

        // Check for duplicates
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_grades WHERE grade_name = %s OR grade_level = %d",
            $data['grade_name'],
            $data['grade_level']
        ));

        if ($exists) {
            return new WP_Error('duplicate_grade', __('A grade with this name or level already exists.', 'olama-school'));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_grades",
            array(
                'grade_name' => $data['grade_name'],
                'grade_level' => $data['grade_level'],
                'periods_count' => $data['periods_count'] ?? 8,
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            )
        );
    }

    /**
     * Get a single grade
     */
    public static function get_grade($id)
    {
        if (isset(self::$cache['grade_' . $id])) {
            return self::$cache['grade_' . $id];
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_grades WHERE id = %d",
            $id
        ));
        self::$cache['grade_' . $id] = $row;
        return $row;
    }

    /**
     * Update grade
     */
    public static function update_grade($id, $data)
    {
        global $wpdb;

        // Check for duplicates (excluding current ID)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_grades WHERE (grade_name = %s OR grade_level = %d) AND id != %d",
            $data['grade_name'],
            $data['grade_level'],
            $id
        ));

        if ($exists) {
            return new WP_Error('duplicate_grade', __('A grade with this name or level already exists.', 'olama-school'));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_grades",
            array(
                'grade_name' => $data['grade_name'],
                'grade_level' => $data['grade_level'],
                'periods_count' => $data['periods_count'] ?? 8,
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
            ),
            array('id' => $id)
        );
    }

    /**
     * Delete grade with validation
     */
    public static function delete_grade($id)
    {
        global $wpdb;

        // Check for related records
        $tables_to_check = array(
            'olama_sections' => __('sections', 'olama-school'),
            'olama_students' => __('students', 'olama-school'),
            'olama_subjects' => __('subjects', 'olama-school'),
            'olama_curriculum' => __('curriculum items', 'olama-school'),
        );

        $errors = array();
        foreach ($tables_to_check as $table => $label) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE grade_id = %d",
                $id
            ));
            if ($count > 0) {
                $errors[] = sprintf(__('This grade is linked to %d %s.', 'olama-school'), $count, $label);
            }
        }

        if (!empty($errors)) {
            return new WP_Error('linked_records', implode(' ', $errors));
        }

        return $wpdb->delete("{$wpdb->prefix}olama_grades", array('id' => $id));
    }
}
