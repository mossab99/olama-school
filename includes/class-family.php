<?php
/**
 * Read-only family adapter backed by Olama Core.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Family
{
    public static function get_families()
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT f.id, f.family_uid,
                    COALESCE(f.sponsor_full_name, f.father_name, f.family_uid) AS family_name,
                    f.father_name AS father_first_name, f.father_nation AS father_nationality,
                    f.father_job, f.father_work_place AS father_workplace,
                    f.father_mobile, f.father_email, f.mother_name AS mother_full_name,
                    f.mother_nation AS mother_nationality, f.mother_mobile, f.mother_email,
                    f.trans_region_name AS residential_area, f.family_address AS home_address,
                    f.building_no AS building_number, f.home_no AS apartment_number,
                    f.family_home_phone AS home_phone, f.address, f.created_at,
                    COUNT(s.id) AS student_count
             FROM {$wpdb->prefix}olama_core_families f
             LEFT JOIN {$wpdb->prefix}olama_core_students s ON s.family_uid=f.family_uid
             GROUP BY f.id ORDER BY family_name ASC"
        ) ?: array();
    }

    public static function get_family($id_or_uid)
    {
        global $wpdb;
        if (is_numeric($id_or_uid)) {
            $where = '(f.id=%d OR f.oracle_family_id=%s)';
            $values = array(absint($id_or_uid), (string) $id_or_uid);
        } else {
            $where = 'f.family_uid=%s';
            $values = array(sanitize_text_field((string) $id_or_uid));
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, COALESCE(f.sponsor_full_name, f.father_name, f.family_uid) AS family_name,
                    f.father_name AS father_first_name, f.father_nation AS father_nationality,
                    f.father_work_place AS father_workplace, f.mother_name AS mother_full_name,
                    f.mother_nation AS mother_nationality, f.trans_region_name AS residential_area,
                    f.family_address AS home_address, f.building_no AS building_number,
                    f.home_no AS apartment_number, f.family_home_phone AS home_phone
             FROM {$wpdb->prefix}olama_core_families f WHERE {$where} LIMIT 1",
            $values
        ));
    }

    public static function get_family_students($family_uid)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.student_name, s.student_uid, s.family_uid AS family_id,
                    s.birth_date AS dob, s.student_national_no AS national_id,
                    COALESCE(s.student_gender_name, s.student_gender) AS gender,
                    CASE WHEN s.student_status IN ('0','inactive','disabled') THEN 0 ELSE 1 END AS is_active
             FROM {$wpdb->prefix}olama_core_students s
             WHERE s.family_uid=%s ORDER BY s.student_name ASC",
            sanitize_text_field((string) $family_uid)
        )) ?: array();
    }

    public static function save_family($data) { return self::read_only_error(); }
    public static function delete_family($id) { return self::read_only_error(); }
    public static function delete_all_families() { return self::read_only_error(); }

    private static function read_only_error()
    {
        return new WP_Error('olama_core_managed', __('Families are managed by Olama Core and synchronized from Oracle.', 'olama-school'));
    }
}
