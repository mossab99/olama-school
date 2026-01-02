<?php
/**
 * Teacher Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Teacher
{

    /**
     * Get all teachers
     */
    public static function get_teachers()
    {
        global $wpdb;

        // Get only users with teacher role
        $teacher_users = get_users(array('role' => 'teacher'));

        if (empty($teacher_users)) {
            return array();
        }

        $teacher_ids = wp_list_pluck($teacher_users, 'ID');
        $placeholders = implode(',', array_fill(0, count($teacher_ids), '%d'));

        // Joining with custom teachers table for extra data
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, t.employee_id, t.phone_number 
			FROM {$wpdb->users} u 
			LEFT JOIN {$wpdb->prefix}olama_teachers t ON u.ID = t.id
            WHERE u.ID IN ($placeholders)",
            ...$teacher_ids
        ));
    }

    /**
     * Update teacher info
     */
    public static function update_teacher($id, $data)
    {
        global $wpdb;
        return $wpdb->replace(
            "{$wpdb->prefix}olama_teachers",
            array(
                'id' => $id,
                'employee_id' => $data['employee_id'],
                'phone_number' => $data['phone_number'],
            )
        );
    }

    /**
     * Get assigned subjects for a teacher and section
     */
    public static function get_assigned_subjects($teacher_id, $section_id)
    {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT subject_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE teacher_id = %d AND section_id = %d",
            $teacher_id,
            $section_id
        ));
    }

    /**
     * Toggle teacher assignment to a subject
     */
    public static function toggle_assignment($teacher_id, $section_id, $subject_id, $grade_id)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_teacher_assignments";

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE teacher_id = %d AND section_id = %d AND subject_id = %d",
            $teacher_id,
            $section_id,
            $subject_id
        ));

        if ($existing) {
            return $wpdb->delete($table, array('id' => $existing));
        } else {
            return $wpdb->insert($table, array(
                'teacher_id' => $teacher_id,
                'grade_id' => $grade_id,
                'section_id' => $section_id,
                'subject_id' => $subject_id,
            ));
        }
    }

    /**
     * Get all assignments for a teacher
     */
    public static function get_all_assignments($teacher_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT grade_id, section_id, subject_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE teacher_id = %d",
            $teacher_id
        ));
    }

    /**
     * Get office hours for a teacher
     */
    public static function get_office_hours($teacher_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_teacher_office_hours WHERE teacher_id = %d ORDER BY FIELD(day_name, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')",
            $teacher_id
        ));
    }

    /**
     * Save office hours for a teacher
     */
    public static function save_office_hours($teacher_id, $slots)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_teacher_office_hours";

        // Delete existing slots
        $wpdb->delete($table, array('teacher_id' => $teacher_id));

        if (empty($slots)) {
            return true;
        }

        foreach ($slots as $slot) {
            if (empty($slot['day_name']) || empty($slot['time'])) {
                continue;
            }
            $wpdb->insert($table, array(
                'teacher_id' => $teacher_id,
                'day_name' => sanitize_text_field($slot['day_name']),
                'available_time' => sanitize_text_field($slot['time']),
            ));
        }

        return true;
    }
}
