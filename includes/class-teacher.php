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
     * Get assigned subjects for a teacher, section and academic year
     */
    public static function get_assigned_subjects($teacher_id, $section_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT subject_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE teacher_id = %d AND section_id = %d AND academic_year_id = %d",
            $teacher_id,
            $section_id,
            $academic_year_id
        ));
    }

    /**
     * Toggle teacher assignment to a subject
     */
    public static function toggle_assignment($teacher_id, $section_id, $subject_id, $grade_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        $table = "{$wpdb->prefix}olama_teacher_assignments";

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE teacher_id = %d AND section_id = %d AND subject_id = %d AND academic_year_id = %d",
            $teacher_id,
            $section_id,
            $subject_id,
            $academic_year_id
        ));

        if ($existing) {
            return $wpdb->delete($table, array('id' => $existing));
        } else {
            return $wpdb->insert($table, array(
                'academic_year_id' => $academic_year_id,
                'teacher_id' => $teacher_id,
                'grade_id' => $grade_id,
                'section_id' => $section_id,
                'subject_id' => $subject_id,
            ));
        }
    }

    /**
     * Get all assignments for a teacher in an academic year
     */
    public static function get_all_assignments($teacher_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT grade_id, section_id, subject_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE teacher_id = %d AND academic_year_id = %d",
            $teacher_id,
            $academic_year_id
        ));
    }

    /**
     * Get office hours for a teacher
     */
    public static function get_office_hours($teacher_id, $academic_year_id = 0, $semester_id = 0)
    {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}olama_teacher_office_hours WHERE teacher_id = %d";
        $params = array($teacher_id);

        if ($academic_year_id) {
            $query .= " AND academic_year_id = %d";
            $params[] = $academic_year_id;
        }

        if ($semester_id) {
            $query .= " AND semester_id = %d";
            $params[] = $semester_id;
        }

        $query .= " ORDER BY FIELD(day_name, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')";

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    /**
     * Save office hours for a teacher
     */
    public static function save_office_hours($teacher_id, $slots, $academic_year_id = 0, $semester_id = 0)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_teacher_office_hours";

        // Delete existing slots for this teacher/year/semester
        $delete_params = array('teacher_id' => $teacher_id);
        if ($academic_year_id)
            $delete_params['academic_year_id'] = $academic_year_id;
        if ($semester_id)
            $delete_params['semester_id'] = $semester_id;

        $wpdb->delete($table, $delete_params);

        if (empty($slots)) {
            return true;
        }

        foreach ($slots as $slot) {
            if (empty($slot['day_name']) || empty($slot['time'])) {
                continue;
            }
            $wpdb->insert($table, array(
                'teacher_id' => $teacher_id,
                'academic_year_id' => $academic_year_id,
                'semester_id' => $semester_id,
                'day_name' => sanitize_text_field($slot['day_name']),
                'available_time' => sanitize_text_field($slot['time']),
            ));
        }

        return true;
    }

    /**
     * Get teachers assigned to a specific section
     */
    public static function get_teachers_for_section($section_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        $teacher_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT teacher_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE section_id = %d AND academic_year_id = %d",
            $section_id,
            $academic_year_id
        ));

        if (empty($teacher_ids))
            return array();

        $placeholders = implode(',', array_fill(0, count($teacher_ids), '%d'));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, t.employee_id, t.phone_number 
			FROM {$wpdb->users} u 
			LEFT JOIN {$wpdb->prefix}olama_teachers t ON u.ID = t.id
            WHERE u.ID IN ($placeholders)",
            ...$teacher_ids
        ));
    }

    /**
     * Get teachers assigned to a specific grade
     */
    public static function get_teachers_for_grade($grade_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        $teacher_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT teacher_id FROM {$wpdb->prefix}olama_teacher_assignments 
            WHERE grade_id = %d AND academic_year_id = %d",
            $grade_id,
            $academic_year_id
        ));

        if (empty($teacher_ids))
            return array();

        $placeholders = implode(',', array_fill(0, count($teacher_ids), '%d'));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, t.employee_id, t.phone_number 
			FROM {$wpdb->users} u 
			LEFT JOIN {$wpdb->prefix}olama_teachers t ON u.ID = t.id
            WHERE u.ID IN ($placeholders)",
            ...$teacher_ids
        ));
    }

    /**
     * Get subjects assigned to a teacher in an academic year
     */
    public static function get_teacher_academic_subjects($teacher_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT s.id, s.subject_name 
            FROM {$wpdb->prefix}olama_subjects s
            JOIN {$wpdb->prefix}olama_teacher_assignments ta ON s.id = ta.subject_id
            WHERE ta.teacher_id = %d AND ta.academic_year_id = %d",
            $teacher_id,
            $academic_year_id
        ));
    }

    /**
     * Get detailed assignments (Grade, Section, Subject + Color) for a teacher
     */
    public static function get_teacher_academic_assignments($teacher_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.grade_name, sec.section_name, s.subject_name, s.color_code 
            FROM {$wpdb->prefix}olama_teacher_assignments ta
            JOIN {$wpdb->prefix}olama_grades g ON ta.grade_id = g.id
            JOIN {$wpdb->prefix}olama_sections sec ON ta.section_id = sec.id
            JOIN {$wpdb->prefix}olama_subjects s ON ta.subject_id = s.id
            WHERE ta.teacher_id = %d AND ta.academic_year_id = %d",
            $teacher_id,
            $academic_year_id
        ));
    }
}