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
        $rows = function_exists('olama_core') ? olama_core()->families()->all() : array();
        return array_map(array(__CLASS__, 'normalize_family'), $rows);
    }

    public static function get_family($id_or_uid)
    {
        if (is_numeric($id_or_uid)) {
            $row = olama_core()->families()->get_by_id(absint($id_or_uid));
            if (!$row) {
                $row = olama_core()->families()->get_by_oracle_id((string) $id_or_uid);
            }
        } else {
            $row = olama_core()->families()->get_by_uid(sanitize_text_field((string) $id_or_uid));
        }
        return $row ? self::normalize_family($row) : null;
    }

    public static function get_family_students($family_uid)
    {
        $rows = olama_core()->students()->get_by_family_uid($family_uid);
        return array_map(function($row) {
            return (object) array_merge($row, array(
                'family_id' => $row['family_uid'],
                'dob' => $row['birth_date'],
                'national_id' => $row['student_national_no'],
                'gender' => $row['student_gender_name'] ?: $row['student_gender'],
                'is_active' => in_array(strtolower((string) $row['student_status']), array('0', 'inactive', 'disabled'), true) ? 0 : 1,
            ));
        }, $rows);
    }

    private static function normalize_family(array $row)
    {
        $row['family_name'] = $row['sponsor_full_name'] ?: ($row['father_name'] ?: $row['family_uid']);
        $row['father_first_name'] = $row['father_name'];
        $row['father_nationality'] = $row['father_nation'];
        $row['father_workplace'] = $row['father_work_place'];
        $row['mother_full_name'] = $row['mother_name'];
        $row['mother_nationality'] = $row['mother_nation'];
        $row['residential_area'] = $row['trans_region_name'];
        $row['home_address'] = $row['family_address'];
        $row['building_number'] = $row['building_no'];
        $row['apartment_number'] = $row['home_no'];
        $row['home_phone'] = $row['family_home_phone'];
        $row['student_count'] = count(olama_core()->students()->get_by_family_uid($row['family_uid']));
        return (object) $row;
    }

    public static function save_family($data) { return self::read_only_error(); }
    public static function delete_family($id) { return self::read_only_error(); }
    public static function delete_all_families() { return self::read_only_error(); }

    private static function read_only_error()
    {
        return new WP_Error('olama_core_managed', __('Families are managed by Olama Core and synchronized from Oracle.', 'olama-school'));
    }
}
