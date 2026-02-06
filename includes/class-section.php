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
     * Get all sections for a specific academic year
     */
    public static function get_sections_by_year($academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $cache_key = 'all_sections_year_' . $academic_year_id;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, g.grade_name 
             FROM {$wpdb->prefix}olama_sections s 
             JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id 
             WHERE s.academic_year_id = %d 
             ORDER BY CAST(g.grade_level AS UNSIGNED), s.section_name",
            $academic_year_id
        ));
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Get sections by grade and academic year
     */
    public static function get_by_grade($grade_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $cache_key = 'sections_grade_' . $grade_id . '_year_' . $academic_year_id;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND academic_year_id = %d",
            $grade_id,
            $academic_year_id
        ));
        self::$cache[$cache_key] = $results;
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

        // Check for duplicates in the same grade and academic year
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s AND academic_year_id = %d",
            $data['grade_id'],
            $data['section_name'],
            $data['academic_year_id']
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade in the selected academic year.', 'olama-school'));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_sections",
            array(
                'academic_year_id' => $data['academic_year_id'],
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
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s AND academic_year_id = %d AND id != %d",
            $data['grade_id'],
            $data['section_name'],
            $data['academic_year_id'],
            $id
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade in the selected academic year.', 'olama-school'));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_sections",
            array(
                'academic_year_id' => $data['academic_year_id'],
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

        // Check for related records (enrollments)
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_student_enrollment WHERE section_id = %d",
            $id
        ));

        if ($count > 0) {
            return new WP_Error('linked_records', sprintf(__('This section is linked to %d students.', 'olama-school'), $count));
        }

        return $wpdb->delete("{$wpdb->prefix}olama_sections", array('id' => $id));
    }
}