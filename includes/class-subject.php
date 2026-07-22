<?php
/**
 * Subject Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Subject
{
    private static $cache = array();

    /**
     * Clear the internal cache
     */
    public static function clear_cache()
    {
        self::$cache = array();
    }


    /**
     * Get all subjects
     */
    public static function get_subjects($active_only = false)
    {
        $cache_key = $active_only ? 'all_subjects_active' : 'all_subjects';
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year();
        if (Olama_School_Academic_Bridge::sync($study_year)) {
            $where = $active_only ? ' AND gs.is_active = 1' : '';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, gs.subject_id AS oracle_subject_id,
                        gs.subject_name AS subject_name, gs.is_active AS is_active,
                        gs.grade_id AS oracle_grade_id, gs.grade_name AS grade_name,
                        gs.last_synced_at AS oracle_last_synced_at
                 FROM {$wpdb->prefix}olama_subjects s
                 INNER JOIN {$wpdb->prefix}olama_core_academic_grade_subjects gs
                    ON gs.study_year = s.core_study_year
                   AND gs.grade_id = s.core_grade_id
                   AND gs.subject_id = s.core_subject_id
                 WHERE gs.study_year = %s {$where}
                 ORDER BY CAST(gs.grade_id AS SIGNED), gs.grade_id,
                          CAST(gs.subject_id AS UNSIGNED), gs.subject_id",
                $study_year
            ));
        } else {
            $where = $active_only ? " WHERE s.is_active = 1" : "";
            $results = $wpdb->get_results("SELECT s.*, g.grade_name FROM {$wpdb->prefix}olama_subjects s JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id" . $where);
        }
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Get subjects by grade
     */
    public static function get_by_grade($grade_id, $active_only = false)
    {
        $cache_key = 'subjects_grade_' . $grade_id . ($active_only ? '_active' : '');
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year();
        if (Olama_School_Academic_Bridge::sync($study_year)) {
            $where = $active_only ? ' AND gs.is_active = 1' : '';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, gs.subject_id AS oracle_subject_id,
                        gs.subject_name AS subject_name, gs.is_active AS is_active,
                        gs.grade_id AS oracle_grade_id, gs.grade_name AS grade_name,
                        gs.last_synced_at AS oracle_last_synced_at
                 FROM {$wpdb->prefix}olama_subjects s
                 INNER JOIN {$wpdb->prefix}olama_core_academic_grade_subjects gs
                    ON gs.study_year = s.core_study_year
                   AND gs.grade_id = s.core_grade_id
                   AND gs.subject_id = s.core_subject_id
                 WHERE gs.study_year = %s AND s.grade_id = %d {$where}
                 ORDER BY gs.is_active DESC, CAST(gs.subject_id AS UNSIGNED), gs.subject_id",
                $study_year,
                $grade_id
            ));
        } else {
            $where = $active_only ? " AND is_active = 1" : "";
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE grade_id = %d" . $where,
                $grade_id
            ));
        }
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Alias for get_by_grade
     */
    public static function get_subjects_by_grade($grade_id, $active_only = false)
    {
        return self::get_by_grade($grade_id, $active_only);
    }

    /**
     * Add subject
     */
    public static function add_subject($data)
    {
        if (Olama_School_Academic_Bridge::is_available()) {
            return new WP_Error('oracle_managed', __('Subjects are managed by Oracle and synchronized through Olama Core.', 'olama-school'));
        }

        global $wpdb;
        $result = $wpdb->insert(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            )
        );
        self::clear_cache();
        return $result;
    }

    /**
     * Get single subject
     */
    public static function get_subject($id)
    {
        if (isset(self::$cache['subject_' . $id])) {
            return self::$cache['subject_' . $id];
        }
        global $wpdb;
        if (Olama_School_Academic_Bridge::sync()) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, gs.subject_id AS oracle_subject_id,
                        gs.subject_name AS subject_name, gs.is_active AS is_active,
                        gs.grade_id AS oracle_grade_id, gs.grade_name AS grade_name,
                        gs.last_synced_at AS oracle_last_synced_at
                 FROM {$wpdb->prefix}olama_subjects s
                 INNER JOIN {$wpdb->prefix}olama_core_academic_grade_subjects gs
                    ON gs.study_year = s.core_study_year
                   AND gs.grade_id = s.core_grade_id
                   AND gs.subject_id = s.core_subject_id
                 WHERE s.id = %d",
                $id
            ));
            if (!$row) {
                // Historical records may reference a legacy subject that is
                // not present in the current Oracle study-year snapshot.
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE id = %d",
                    $id
                ));
            }
        } else {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_subjects WHERE id = %d",
                $id
            ));
        }
        self::$cache['subject_' . $id] = $row;
        return $row;
    }

    /**
     * Update subject
     */
    public static function update_subject($id, $data)
    {
        global $wpdb;
        if (Olama_School_Academic_Bridge::is_available()) {
            $allowed = array('color_code', 'max_weekly_plans');
            $settings = array_intersect_key($data, array_flip($allowed));
            if (empty($settings)) {
                return new WP_Error('oracle_managed', __('Subject names, IDs, grade membership, and status are managed by Oracle.', 'olama-school'));
            }
            $result = $wpdb->update("{$wpdb->prefix}olama_subjects", $settings, array('id' => $id));
            self::clear_cache();
            return $result;
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}olama_subjects",
            array(
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? '',
                'grade_id' => $data['grade_id'],
                'color_code' => $data['color_code'] ?? '#000000',
                'max_weekly_plans' => $data['max_weekly_plans'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            ),
            array('id' => $id)
        );
        self::clear_cache();
        return $result;
    }

    /**
     * Delete subject
     */
    public static function delete_subject($id)
    {
        if (Olama_School_Academic_Bridge::is_available()) {
            return new WP_Error('oracle_managed', __('Subjects are managed by Oracle and cannot be deleted in Olama School.', 'olama-school'));
        }

        global $wpdb;
        $result = $wpdb->delete(
            "{$wpdb->prefix}olama_subjects",
            array('id' => $id)
        );
        self::clear_cache();
        return $result;
    }
}
