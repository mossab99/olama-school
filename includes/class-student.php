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
     * Get students with optional filtering by year and section
     * If academic_year_id is 0, returns the full registry with latest enrollment info
     */
    public static function get_students($args = array())
    {
        global $wpdb;
        $year_id = isset($args['academic_year_id']) ? intval($args['academic_year_id']) : 0;

        $cache_key = 'olama_students_list_' . $year_id . '_' . ($args['section_id'] ?? 0);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        if ($year_id > 0) {
            // Fetch enrolled students for a specific year
            $query = "SELECT s.*, e.section_id, e.academic_year_id, e.status as enrollment_status, 
                      g.grade_name, sec.section_name 
                      FROM {$wpdb->prefix}olama_students s 
                      JOIN {$wpdb->prefix}olama_student_enrollment e ON s.id = e.student_id 
                      JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id 
                      JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id 
                      WHERE e.academic_year_id = %d";
            $params = array($year_id);

            if (isset($args['section_id']) && $args['section_id']) {
                $query .= " AND e.section_id = %d";
                $params[] = intval($args['section_id']);
            }
        } else {
            // Fetch ALL students (Registry) with their LATEST enrollment info if any
            $query = "SELECT s.*, e.section_id, e.academic_year_id, e.status as enrollment_status, 
                      g.grade_name, sec.section_name, ay.year_name as academic_year_name
                      FROM {$wpdb->prefix}olama_students s 
                      LEFT JOIN (
                          SELECT e1.* FROM {$wpdb->prefix}olama_student_enrollment e1
                          WHERE e1.id = (SELECT MAX(id) FROM {$wpdb->prefix}olama_student_enrollment e2 WHERE e2.student_id = e1.student_id)
                      ) e ON s.id = e.student_id 
                      LEFT JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id 
                      LEFT JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id 
                      LEFT JOIN {$wpdb->prefix}olama_academic_years ay ON e.academic_year_id = ay.id
                      WHERE 1=1";
            $params = array();
        }

        $results = $wpdb->get_results($wpdb->prepare($query, ...$params));

        set_transient($cache_key, $results, 60 * MINUTE_IN_SECONDS);

        return $results;
    }

    /**
     * Register a new student (Master Record)
     */
    public static function register_student($data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}olama_students",
            array(
                'student_name' => $data['student_name'],
                'student_uid' => $data['student_uid'] ?? $data['student_id_number'],
                'family_id' => $data['family_id'] ?? null,
                'is_active' => 1
            )
        );

        if ($result) {
            self::clear_cache();
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Enroll a student in a section (Academic Placement)
     */
    public static function enroll_student($student_id, $section_id, $academic_year_id = 0)
    {
        global $wpdb;

        if (!$academic_year_id && class_exists('Olama_School_Academic')) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        // Check if already enrolled in this year
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_student_enrollment WHERE student_id = %d AND academic_year_id = %d",
            $student_id,
            $academic_year_id
        ));

        if ($exists) {
            // Update existing enrollment
            $result = $wpdb->update(
                "{$wpdb->prefix}olama_student_enrollment",
                array(
                    'section_id' => $section_id,
                    'status' => 'active'
                ),
                array('id' => $exists)
            );
        } else {
            // Create new enrollment
            $result = $wpdb->insert(
                "{$wpdb->prefix}olama_student_enrollment",
                array(
                    'student_id' => $student_id,
                    'academic_year_id' => $academic_year_id,
                    'section_id' => $section_id,
                    'enrollment_date' => current_time('mysql', 1),
                    'status' => 'active'
                )
            );
        }

        if ($result !== false) {
            self::clear_cache();
            return true;
        }

        return false;
    }

    /**
     * Unenroll a student from a specific year or all active enrollments
     */
    public static function unenroll_student($student_id, $academic_year_id = 0)
    {
        global $wpdb;

        $where = array('student_id' => $student_id);
        if ($academic_year_id) {
            $where['academic_year_id'] = $academic_year_id;
        }

        $result = $wpdb->delete("{$wpdb->prefix}olama_student_enrollment", $where);

        if ($result !== false) {
            self::clear_cache();
            return true;
        }

        return false;
    }

    /**
     * Get enrollment history for a student
     */
    public static function get_enrollment_history($student_id)
    {
        global $wpdb;
        $query = "SELECT e.*, g.grade_name, sec.section_name, ay.year_name as academic_year_name
                  FROM {$wpdb->prefix}olama_student_enrollment e 
                  JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id 
                  JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id 
                  JOIN {$wpdb->prefix}olama_academic_years ay ON e.academic_year_id = ay.id
                  WHERE e.student_id = %d 
                  ORDER BY ay.start_date DESC";

        return $wpdb->get_results($wpdb->prepare($query, $student_id));
    }

    /**
     * Get specific enrollment record for a student in a specific year
     */
    public static function get_student_enrollment($student_id, $academic_year_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, sec.grade_id 
             FROM {$wpdb->prefix}olama_student_enrollment e 
             JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id
             WHERE e.student_id = %d AND e.academic_year_id = %d",
            $student_id,
            $academic_year_id
        ));
    }

    /**
     * Add student (deprecated, kept for compatibility if needed elsewhere temporarily)
     */
    public static function add_student($data)
    {
        $student_id = self::register_student($data);
        if ($student_id && isset($data['section_id'])) {
            self::enroll_student($student_id, $data['section_id'], $data['academic_year_id'] ?? 0);
        }
        return $student_id;
    }

    /**
     * Update student information
     */
    public static function update_student($id, $data)
    {
        global $wpdb;

        $update_data = array();
        if (isset($data['student_name']))
            $update_data['student_name'] = $data['student_name'];
        if (isset($data['student_uid']))
            $update_data['student_uid'] = $data['student_uid'];
        if (isset($data['family_id']))
            $update_data['family_id'] = $data['family_id'];
        if (isset($data['is_active']))
            $update_data['is_active'] = intval($data['is_active']);

        if (empty($update_data))
            return false;

        $result = $wpdb->update(
            "{$wpdb->prefix}olama_students",
            $update_data,
            array('id' => $id)
        );

        self::clear_cache();
        return $result;
    }

    /**
     * Delete student and their enrollments
     */
    public static function delete_student($id)
    {
        global $wpdb;

        // Delete enrollments first
        $wpdb->delete("{$wpdb->prefix}olama_student_enrollment", array('student_id' => $id));

        // Delete student
        $result = $wpdb->delete("{$wpdb->prefix}olama_students", array('id' => $id));

        self::clear_cache();
        return $result;
    }

    /**
     * Clear student cache
     */
    public static function clear_cache()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_olama_students_list%'");
    }
}