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

        if ($cached !== false && !empty($cached)) {
            return $cached;
        }
        // If cached result was empty, delete it so we re-query
        if ($cached !== false && empty($cached)) {
            delete_transient($cache_key);
        }

        if ($year_id > 0) {
            // Fetch enrolled students for a specific year
            $query = "SELECT s.*, e.section_id, e.academic_year_id, e.status as enrollment_status, 
                      g.grade_name, sec.section_name 
                      FROM {$wpdb->prefix}olama_students s 
                      JOIN {$wpdb->prefix}olama_student_enrollment e ON s.student_uid = e.student_uid 
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
                      g.grade_name, sec.section_name, ay.year_name as academic_year_name,
                      f.family_name, f.family_uid as f_uid
                      FROM {$wpdb->prefix}olama_students s 
                      LEFT JOIN {$wpdb->prefix}olama_families f ON s.family_id = f.family_uid
                      LEFT JOIN (
                          SELECT e1.* FROM {$wpdb->prefix}olama_student_enrollment e1
                          WHERE e1.id = (SELECT MAX(id) FROM {$wpdb->prefix}olama_student_enrollment e2 WHERE e2.student_uid = e1.student_uid)
                      ) e ON s.student_uid = e.student_uid 
                      LEFT JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id 
                      LEFT JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id 
                      LEFT JOIN {$wpdb->prefix}olama_academic_years ay ON e.academic_year_id = ay.id
                      WHERE 1=1";
            $params = array();
        }

        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $results = $wpdb->get_results($query);
        }

        if ($wpdb->last_error) {
            error_log('Olama get_students query error: ' . $wpdb->last_error);
        }

        // Only cache non-empty results to avoid caching query failures
        if (!empty($results)) {
            set_transient($cache_key, $results, 60 * MINUTE_IN_SECONDS);
        }

        return $results ?: array();
    }

    /**
     * Register a new student (Master Record)
     */
    public static function register_student($data)
    {
        global $wpdb;

        $student_uid = $data['student_uid'] ?? $data['student_id_number'];
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_students WHERE student_uid = %s",
            $student_uid
        ));

        $student_payload = array(
            'student_name' => $data['student_name'],
            'student_uid' => $student_uid,
            'family_id' => $data['family_id'] ?? null,
            'dob' => !empty($data['dob']) ? $data['dob'] : null,
            'national_id' => $data['national_id'] ?? null,
            'gender' => $data['gender'] ?? null,
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
        );

        if ($existing_id) {
            $result = $wpdb->update(
                "{$wpdb->prefix}olama_students",
                $student_payload,
                array('id' => $existing_id)
            );
            if ($result !== false) {
                self::clear_cache();
                return $existing_id;
            }
        } else {
            $result = $wpdb->insert(
                "{$wpdb->prefix}olama_students",
                $student_payload
            );
            if ($result) {
                self::clear_cache();
                return $wpdb->insert_id;
            }
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

        // Fetch student_uid for stable linkage (must be done before the existence check)
        $student_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
            $student_id
        ));

        // Check if already enrolled in this year using stable UID
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_student_enrollment WHERE student_uid = %s AND academic_year_id = %d",
            $student_uid,
            $academic_year_id
        ));

        if ($exists) {
            // Update existing enrollment
            $result = $wpdb->update(
                "{$wpdb->prefix}olama_student_enrollment",
                array(
                    'section_id' => $section_id,
                    'student_uid' => $student_uid,
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
                    'student_uid' => $student_uid,
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
                  JOIN {$wpdb->prefix}olama_students s ON e.student_uid = s.student_uid
                  WHERE s.id = %d 
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
             JOIN {$wpdb->prefix}olama_students s ON e.student_uid = s.student_uid
             WHERE s.id = %d AND e.academic_year_id = %d",
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
        if (isset($data['dob']))
            $update_data['dob'] = !empty($data['dob']) ? $data['dob'] : null;
        if (isset($data['national_id']))
            $update_data['national_id'] = $data['national_id'];
        if (isset($data['gender']))
            $update_data['gender'] = $data['gender'];
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
     * NOTE: Evaluation records are intentionally preserved for data integrity.
     * They can be re-linked to re-imported students via student_uid.
     */
    public static function delete_student($id)
    {
        global $wpdb;

        // Delete enrollments
        $wpdb->delete("{$wpdb->prefix}olama_student_enrollment", array('student_id' => $id));

        // Delete student
        $result = $wpdb->delete("{$wpdb->prefix}olama_students", array('id' => $id));

        self::clear_cache();
        return $result;
    }

    /**
     * Delete ALL students and ALL enrollments
     * NOTE: Evaluation records are intentionally preserved for data integrity.
     * They can be re-linked to re-imported students via student_uid.
     */
    public static function delete_all_students()
    {
        global $wpdb;

        // Delete all enrollment and student records (evaluation data is preserved)
        $wpdb->query("DELETE FROM {$wpdb->prefix}olama_student_enrollment");
        $result = $wpdb->query("DELETE FROM {$wpdb->prefix}olama_students");

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
        // Also explicitly delete common keys if possible or just use the DB query if no object cache
        // If object cache is present, we might need a different approach, but this is a good start.
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('olama_students_list_0_0', 'transient');
        }
    }
}