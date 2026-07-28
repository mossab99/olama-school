<?php
/**
 * Read-only student adapter backed by Olama Core.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Student
{
    public static function get_students($args = array())
    {
        global $wpdb;

        if (!self::core_available()) {
            return array();
        }

        $year_id = isset($args['academic_year_id']) ? absint($args['academic_year_id']) : 0;
        $section_id = isset($args['section_id']) ? absint($args['section_id']) : 0;
        $study_year = Olama_School_Academic_Bridge::get_study_year($year_id);
        if ($study_year === '') {
            return array();
        }

        Olama_School_Academic_Bridge::sync($study_year);
        $cache_key = 'olama_core_students_list_' . md5($study_year . '|' . $section_id . '|' . ($year_id ? 'year' : 'registry'));
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $students = olama_core()->read_models()->table('students');
        $years = olama_core()->read_models()->table('student_years');
        $families = olama_core()->read_models()->table('families');
        $sections = $wpdb->prefix . 'olama_sections';
        $grades = $wpdb->prefix . 'olama_grades';
        $academic_years = $wpdb->prefix . 'olama_academic_years';

        $year_join = $year_id
            ? $wpdb->prepare("INNER JOIN {$years} y ON y.student_uid=s.student_uid AND y.study_year=%s", $study_year)
            : "LEFT JOIN {$years} y ON y.id=(SELECT y2.id FROM {$years} y2 WHERE y2.student_uid=s.student_uid ORDER BY y2.study_year DESC, y2.id DESC LIMIT 1)";

        $sql = "SELECT s.id, s.student_name, s.student_uid, s.family_uid AS family_id,
                       s.birth_date AS dob, s.student_national_no AS national_id,
                       COALESCE(s.student_gender_name, s.student_gender) AS gender,
                       CASE WHEN s.student_status IN ('0','inactive','disabled') THEN 0 ELSE 1 END AS is_active,
                       y.id AS enrollment_id, sec.id AS section_id, ay.id AS academic_year_id,
                       y.student_status AS enrollment_status, g.grade_name, sec.section_name,
                       ay.year_name AS academic_year_name,
                       COALESCE(f.sponsor_full_name, f.father_name, f.family_uid) AS family_name,
                       f.family_uid AS f_uid
                FROM {$students} s
                {$year_join}
                LEFT JOIN {$families} f ON f.family_uid=s.family_uid
                LEFT JOIN {$academic_years} ay ON REPLACE(ay.year_name, '/', '-')=REPLACE(y.study_year, '/', '-')
                LEFT JOIN {$sections} sec ON sec.core_study_year=y.study_year
                    AND sec.core_grade_id=y.class_id AND sec.core_section_id=y.section_id
                LEFT JOIN {$grades} g ON g.id=sec.grade_id
                WHERE 1=1";
        $params = array();
        if ($section_id) {
            $sql .= ' AND sec.id=%d';
            $params[] = $section_id;
        }
        $sql .= ' ORDER BY s.student_name ASC';

        $results = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        $results = $results ?: array();
        set_transient($cache_key, $results, 15 * MINUTE_IN_SECONDS);
        return $results;
    }

    public static function get_enrollment_history($student_id)
    {
        global $wpdb;
        if (!self::core_available()) {
            return array();
        }

        $core_students = olama_core()->read_models()->table('students');
        $core_student_years = olama_core()->read_models()->table('student_years');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT y.id, s.id AS student_id, y.student_uid, ay.id AS academic_year_id,
                    sec.id AS section_id, y.registration_date AS enrollment_date,
                    y.student_status AS status, g.grade_name, sec.section_name,
                    y.study_year AS academic_year_name
             FROM {$core_students} s
             INNER JOIN {$core_student_years} y ON y.student_uid=s.student_uid
             LEFT JOIN {$wpdb->prefix}olama_academic_years ay
                ON REPLACE(ay.year_name, '/', '-')=REPLACE(y.study_year, '/', '-')
             LEFT JOIN {$wpdb->prefix}olama_sections sec ON sec.core_study_year=y.study_year
                AND sec.core_grade_id=y.class_id AND sec.core_section_id=y.section_id
             LEFT JOIN {$wpdb->prefix}olama_grades g ON g.id=sec.grade_id
             WHERE s.id=%d ORDER BY y.study_year DESC",
            absint($student_id)
        ));
    }

    public static function get_student_enrollment($student_id, $academic_year_id)
    {
        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year(absint($academic_year_id));
        if (!self::core_available() || $study_year === '') {
            return null;
        }

        $core_students = olama_core()->read_models()->table('students');
        $core_student_years = olama_core()->read_models()->table('student_years');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT y.id, s.id AS student_id, y.student_uid, ay.id AS academic_year_id,
                    sec.id AS section_id, sec.grade_id, y.registration_date AS enrollment_date,
                    y.student_status AS status
             FROM {$core_students} s
             INNER JOIN {$core_student_years} y ON y.student_uid=s.student_uid
             LEFT JOIN {$wpdb->prefix}olama_academic_years ay
                ON REPLACE(ay.year_name, '/', '-')=REPLACE(y.study_year, '/', '-')
             LEFT JOIN {$wpdb->prefix}olama_sections sec ON sec.core_study_year=y.study_year
                AND sec.core_grade_id=y.class_id AND sec.core_section_id=y.section_id
             WHERE s.id=%d AND y.study_year=%s LIMIT 1",
            absint($student_id),
            $study_year
        ));
    }

    public static function register_student($data) { return self::read_only_error(); }
    public static function enroll_student($student_id, $section_id, $academic_year_id = 0) { return self::read_only_error(); }
    public static function unenroll_student($student_id, $academic_year_id = 0) { return self::read_only_error(); }
    public static function add_student($data) { return self::read_only_error(); }
    public static function update_student($id, $data) { return self::read_only_error(); }
    public static function delete_student($id) { return self::read_only_error(); }
    public static function delete_all_students() { return self::read_only_error(); }

    public static function clear_cache()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_olama_core_students_list_%'");
    }

    private static function core_available()
    {
        return function_exists('olama_core') && olama_core()->read_models()->available('students');
    }

    private static function read_only_error()
    {
        return new WP_Error('olama_core_managed', __('Students and enrollment are managed by Olama Core and synchronized from Oracle.', 'olama-school'));
    }
}
