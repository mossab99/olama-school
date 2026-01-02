<?php
/**
 * Section Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Section
{
    private static $cache = array();


    /**
     * Get all sections
     */
    public static function get_sections()
    {
        if (isset(self::$cache['all_sections'])) {
            return self::$cache['all_sections'];
        }
        global $wpdb;
        $results = $wpdb->get_results("SELECT s.*, g.grade_name FROM {$wpdb->prefix}olama_sections s JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id");
        self::$cache['all_sections'] = $results;
        return $results;
    }

    /**
     * Get sections by grade
     */
    public static function get_by_grade($grade_id)
    {
        if (isset(self::$cache['sections_grade_' . $grade_id])) {
            return self::$cache['sections_grade_' . $grade_id];
        }
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d",
            $grade_id
        ));
        self::$cache['sections_grade_' . $grade_id] = $results;
        return $results;
    }

    /**
     * Get a single section by ID
     */
    public static function get_section($id)
    {
        if (isset(self::$cache['section_' . $id])) {
            return self::$cache['section_' . $id];
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_sections WHERE id = %d",
            $id
        ));
        self::$cache['section_' . $id] = $row;
        return $row;
    }

    /**
     * Add section
     */
    public static function add_section($data)
    {
        global $wpdb;

        // Check for duplicates in the same grade
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s",
            $data['grade_id'],
            $data['section_name']
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade.', 'olama-school'));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_sections",
            array(
                'grade_id' => $data['grade_id'],
                'section_name' => $data['section_name'],
                'room_number' => $data['room_number'] ?? '',
            )
        );
    }

    /**
     * Update section
     */
    public static function update_section($id, $data)
    {
        global $wpdb;

        // Check for duplicates (excluding current ID)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s AND id != %d",
            $data['grade_id'],
            $data['section_name'],
            $id
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade.', 'olama-school'));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_sections",
            array(
                'grade_id' => $data['grade_id'],
                'section_name' => $data['section_name'],
                'room_number' => $data['room_number'] ?? '',
            ),
            array('id' => $id)
        );
    }

    /**
     * Delete section with validation
     */
    public static function delete_section($id)
    {
        global $wpdb;

        // Check for related records (students)
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_students WHERE section_id = %d",
            $id
        ));

        if ($count > 0) {
            return new WP_Error('linked_records', sprintf(__('This section is linked to %d students.', 'olama-school'), $count));
        }

        return $wpdb->delete("{$wpdb->prefix}olama_sections", array('id' => $id));
    }
}
