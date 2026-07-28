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

        $identity_table = $wpdb->prefix . 'olama_user_identities';
        $profile_table = olama_core()->read_models()->table('staff_profiles');
        $has_identity_source = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $identity_table)) === $identity_table
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $profile_table)) === $profile_table;

        if ($has_identity_source) {
            $teacher_ids = get_users(array(
                'role' => 'olama_teacher',
                'fields' => 'ids',
            ));

            if (empty($teacher_ids)) {
                return array();
            }

            $placeholders = implode(',', array_fill(0, count($teacher_ids), '%d'));

            return $wpdb->get_results($wpdb->prepare(
                "SELECT u.ID, u.display_name, u.user_email,
                        p.employee_id, p.phone_number, i.account_status
                 FROM {$wpdb->users} u
                 INNER JOIN {$profile_table} p ON p.user_id = u.ID
                 INNER JOIN {$identity_table} i
                    ON i.wp_user_id = u.ID
                   AND i.identity_type = 'employee'
                   AND i.account_status = 'active'
                 WHERE u.ID IN ({$placeholders})
                 ORDER BY CAST(p.employee_id AS UNSIGNED), p.employee_id, u.display_name",
                ...array_map('intval', $teacher_ids)
            ));
        }

        // Compatibility fallback for sites that have not installed Olama Core/Users yet.
        $teacher_users = get_users(array('role__in' => array('teacher', 'assistant')));

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
            WHERE u.ID IN ($placeholders)
            ORDER BY u.display_name",
            ...$teacher_ids
        ));
    }

    /**
     * Get an active teacher synchronized by Olama Users/Core.
     */
    public static function get_synced_teacher($teacher_id)
    {
        $teacher_id = absint($teacher_id);
        if (!$teacher_id) {
            return null;
        }

        foreach (self::get_teachers() as $teacher) {
            if ((int) $teacher->ID === $teacher_id) {
                return $teacher;
            }
        }

        return null;
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
        $teacher = self::get_synced_teacher($teacher_id);
        $employee_id = $teacher ? (string) $teacher->employee_id : '';
        $study_year = Olama_School_Academic_Bridge::get_study_year($academic_year_id);

        if ($study_year !== '' && Olama_School_Academic_Bridge::sync($study_year)) {
            $core_grade_sections = olama_core()->read_models()->table('academic_grade_sections');
            $core_grade_subjects = olama_core()->read_models()->table('academic_grade_subjects');
            return $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ta.subject_id
                 FROM {$wpdb->prefix}olama_teacher_assignments ta
                 INNER JOIN {$wpdb->prefix}olama_sections sec
                    ON sec.id = ta.section_id
                   AND sec.grade_id = ta.grade_id
                   AND sec.academic_year_id = ta.academic_year_id
                 INNER JOIN {$core_grade_sections} csec
                    ON csec.study_year = sec.core_study_year
                   AND csec.grade_id = sec.core_grade_id
                   AND csec.section_id = sec.core_section_id
                 INNER JOIN {$wpdb->prefix}olama_subjects s
                    ON s.id = ta.subject_id
                   AND s.grade_id = ta.grade_id
                 INNER JOIN {$core_grade_subjects} cs
                    ON cs.study_year = s.core_study_year
                   AND cs.grade_id = s.core_grade_id
                   AND cs.subject_id = s.core_subject_id
                   AND cs.is_active = 1
                 WHERE (ta.teacher_id = %d OR (%s <> '' AND ta.teacher_employee_id = %s))
                   AND ta.section_id = %d
                   AND ta.academic_year_id = %d
                   AND csec.study_year = %s
                   AND cs.study_year = %s",
                $teacher_id,
                $employee_id,
                $employee_id,
                $section_id,
                $academic_year_id,
                $study_year,
                $study_year
            ));
        }

        return $wpdb->get_col($wpdb->prepare(
            "SELECT subject_id FROM {$wpdb->prefix}olama_teacher_assignments
             WHERE (teacher_id = %d OR (%s <> '' AND teacher_employee_id = %s))
               AND section_id = %d AND academic_year_id = %d",
            $teacher_id,
            $employee_id,
            $employee_id,
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

        $teacher = self::get_synced_teacher($teacher_id);
        if (!$teacher) {
            return new WP_Error('invalid_teacher', __('The selected teacher is not an active synchronized employee.', 'olama-school'));
        }

        $grade = Olama_School_Grade::get_grade($grade_id);
        if (!$grade) {
            return new WP_Error('invalid_grade', __('The selected grade is not available in the current Oracle data.', 'olama-school'));
        }

        $valid_section = false;
        foreach (Olama_School_Section::get_by_grade($grade_id, $academic_year_id) as $section) {
            if ((int) $section->id === (int) $section_id) {
                $valid_section = true;
                break;
            }
        }
        if (!$valid_section) {
            return new WP_Error('invalid_section', __('The selected section does not belong to this grade and study year.', 'olama-school'));
        }

        $valid_subject = false;
        foreach (Olama_School_Subject::get_by_grade($grade_id, true) as $subject) {
            if ((int) $subject->id === (int) $subject_id) {
                $valid_subject = true;
                break;
            }
        }
        if (!$valid_subject) {
            return new WP_Error('invalid_subject', __('The selected subject is not active for this grade in Oracle.', 'olama-school'));
        }

        global $wpdb;
        $table = "{$wpdb->prefix}olama_teacher_assignments";

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table
             WHERE (teacher_id = %d OR teacher_employee_id = %s)
               AND section_id = %d AND subject_id = %d AND academic_year_id = %d",
            $teacher_id,
            (string) $teacher->employee_id,
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
                'teacher_employee_id' => (string) $teacher->employee_id,
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
        $teacher = self::get_synced_teacher($teacher_id);
        $employee_id = $teacher ? (string) $teacher->employee_id : '';
        $study_year = Olama_School_Academic_Bridge::get_study_year($academic_year_id);

        if ($study_year !== '' && Olama_School_Academic_Bridge::sync($study_year)) {
            $core_grade_sections = olama_core()->read_models()->table('academic_grade_sections');
            $core_grade_subjects = olama_core()->read_models()->table('academic_grade_subjects');
            return $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT ta.grade_id, ta.section_id, ta.subject_id
                 FROM {$wpdb->prefix}olama_teacher_assignments ta
                 INNER JOIN {$wpdb->prefix}olama_sections sec
                    ON sec.id = ta.section_id
                   AND sec.grade_id = ta.grade_id
                   AND sec.academic_year_id = ta.academic_year_id
                 INNER JOIN {$core_grade_sections} csec
                    ON csec.study_year = sec.core_study_year
                   AND csec.grade_id = sec.core_grade_id
                   AND csec.section_id = sec.core_section_id
                 INNER JOIN {$wpdb->prefix}olama_subjects s
                    ON s.id = ta.subject_id
                   AND s.grade_id = ta.grade_id
                 INNER JOIN {$core_grade_subjects} cs
                    ON cs.study_year = s.core_study_year
                   AND cs.grade_id = s.core_grade_id
                   AND cs.subject_id = s.core_subject_id
                   AND cs.is_active = 1
                 WHERE (ta.teacher_id = %d OR (%s <> '' AND ta.teacher_employee_id = %s))
                   AND ta.academic_year_id = %d
                   AND csec.study_year = %s
                   AND cs.study_year = %s",
                $teacher_id,
                $employee_id,
                $employee_id,
                $academic_year_id,
                $study_year,
                $study_year
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT grade_id, section_id, subject_id
             FROM {$wpdb->prefix}olama_teacher_assignments
             WHERE (teacher_id = %d OR (%s <> '' AND teacher_employee_id = %s))
               AND academic_year_id = %d",
            $teacher_id,
            $employee_id,
            $employee_id,
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
