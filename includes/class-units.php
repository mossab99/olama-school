<?php
/**
 * Curriculum Unit Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Unit
{
    /**
     * Get units by subject, grade, and semester
     */
    public static function get_units($subject_id, $grade_id, $semester_id)
    {
        global $wpdb;
        $units_table = "{$wpdb->prefix}olama_curriculum_units";
        $lessons_table = "{$wpdb->prefix}olama_curriculum_lessons";

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.*, 
            (SELECT COUNT(*) FROM $lessons_table l WHERE l.unit_id = u.id) as lesson_count 
            FROM $units_table u
            WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d 
            ORDER BY CAST(u.unit_number AS UNSIGNED) ASC, u.unit_number ASC",
            $subject_id,
            $grade_id,
            $semester_id
        ));
    }

    /**
     * Add or update a unit
     */
    public static function save_unit($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_curriculum_units";

        $unit_data = array(
            'grade_id' => intval($data['grade_id']),
            'subject_id' => intval($data['subject_id']),
            'semester_id' => intval($data['semester_id']),
            'unit_number' => sanitize_text_field($data['unit_number']),
            'unit_name' => sanitize_text_field($data['unit_name']),
            'objectives' => sanitize_textarea_field($data['objectives'] ?? ''),
        );

        // Only update dates if explicitly provided or if it's a new unit
        if (isset($data['start_date']) || empty($data['id'])) {
            $unit_data['start_date'] = !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null;
        }
        if (isset($data['end_date']) || empty($data['id'])) {
            $unit_data['end_date'] = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null;
        }

        // Check for duplicate unit number
        $duplicate_check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table 
            WHERE unit_number = %s 
            AND grade_id = %d AND subject_id = %d AND semester_id = %d AND id != %d",
            $unit_data['unit_number'],
            $unit_data['grade_id'],
            $unit_data['subject_id'],
            $unit_data['semester_id'],
            isset($data['id']) ? intval($data['id']) : 0
        ));

        if ($duplicate_check) {
            return new WP_Error('duplicate_unit_number', sprintf(__('Unit #%s already exists for this subject.', 'olama-school'), $unit_data['unit_number']));
        }

        // Suppress errors from outputting HTML and capture them instead
        $wpdb->suppress_errors(true);

        if (!empty($data['id'])) {
            $result = $wpdb->update($table, $unit_data, array('id' => intval($data['id'])));
            return array(
                'id' => intval($data['id']),
                'result' => $result,
                'error' => $wpdb->last_error,
                'query' => $wpdb->last_query,
                'data' => $unit_data
            );
        } else {
            $result = $wpdb->insert($table, $unit_data);
            return array(
                'id' => $wpdb->insert_id,
                'result' => $result,
                'error' => $wpdb->last_error,
                'query' => $wpdb->last_query,
                'data' => $unit_data
            );
        }
    }

    /**
     * Delete a unit (only if it has no lessons)
     */
    public static function delete_unit($id)
    {
        global $wpdb;
        $id = intval($id);

        // Check if unit has lessons
        $lesson_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_curriculum_lessons WHERE unit_id = %d",
            $id
        ));

        if ($lesson_count > 0) {
            return new WP_Error(
                'has_lessons',
                sprintf(
                    __('Cannot delete this unit. It has %d lesson(s). Please delete all lessons first.', 'olama-school'),
                    $lesson_count
                )
            );
        }

        return $wpdb->delete("{$wpdb->prefix}olama_curriculum_units", array('id' => $id));
    }

    /**
     * Get single unit
     */
    public static function get_unit($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum_units WHERE id = %d",
            $id
        ));
    }
}