<?php
/**
 * Stable local identities for Oracle academic records owned by Olama Core.
 *
 * School tables keep the numeric IDs referenced by schedules, plans and other
 * operational records. Names and grade/section membership remain Core-owned.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Academic_Bridge
{
    private static $synced_years = array();

    public static function is_available()
    {
        global $wpdb;

        if (!function_exists('olama_core')) {
            return false;
        }

        $grades = $wpdb->prefix . 'olama_core_academic_grades';
        $relations = $wpdb->prefix . 'olama_core_academic_grade_sections';

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $grades)) === $grades
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $relations)) === $relations;
    }

    public static function get_study_year($academic_year_id = 0)
    {
        if (!function_exists('olama_core')) {
            return '';
        }

        $year = $academic_year_id
            ? olama_core()->academic_calendar()->year(absint($academic_year_id))
            : olama_core()->academic_context()->current_year();

        if ($year) {
            foreach (array('code', 'year_name', 'name_en', 'name_ar') as $property) {
                if (!empty($year->{$property})) {
                    return sanitize_text_field((string) $year->{$property});
                }
            }
        }

        return '';
    }

    public static function get_academic_year_id($study_year)
    {
        if (!function_exists('olama_core')) {
            return 0;
        }

        $year = olama_core()->academic_calendar()->resolve_year_code($study_year);
        return $year ? (int) $year->id : 0;
    }

    public static function sync($study_year = '')
    {
        if (!self::is_available()) {
            return false;
        }

        $study_year = $study_year !== '' ? sanitize_text_field((string) $study_year) : self::get_study_year();
        if ($study_year === '' || isset(self::$synced_years[$study_year])) {
            return $study_year !== '';
        }

        self::$synced_years[$study_year] = true;
        $revision = self::source_revision($study_year);
        $revision_option = 'olama_school_core_academic_' . md5($study_year);
        if ($revision !== '' && get_option($revision_option, '') === $revision) {
            return true;
        }

        self::sync_grades();
        self::sync_sections($study_year);
        self::sync_subjects($study_year);
        if ($revision !== '') {
            update_option($revision_option, $revision, false);
        }
        return true;
    }

    private static function source_revision($study_year)
    {
        global $wpdb;

        $grade_table = $wpdb->prefix . 'olama_core_academic_grades';
        $relation_table = $wpdb->prefix . 'olama_core_academic_grade_sections';
        $subject_table = $wpdb->prefix . 'olama_core_academic_grade_subjects';
        $grade_revision = $wpdb->get_row(
            "SELECT COUNT(*) AS row_count, MAX(last_synced_at) AS synced_at FROM {$grade_table}",
            ARRAY_A
        );
        $relation_revision = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS row_count, MAX(last_synced_at) AS synced_at
             FROM {$relation_table} WHERE study_year = %s",
            $study_year
        ), ARRAY_A);
        $subject_revision = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS row_count, MAX(last_synced_at) AS synced_at
             FROM {$subject_table} WHERE study_year = %s",
            $study_year
        ), ARRAY_A);

        if (empty($grade_revision['row_count'])) {
            return '';
        }

        return implode('|', array(
            'bridge-v2',
            (string) $grade_revision['row_count'],
            (string) $grade_revision['synced_at'],
            (string) ($relation_revision['row_count'] ?? 0),
            (string) ($relation_revision['synced_at'] ?? ''),
            (string) ($subject_revision['row_count'] ?? 0),
            (string) ($subject_revision['synced_at'] ?? ''),
        ));
    }

    private static function sync_grades()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'olama_grades';
        foreach (olama_core()->academic()->grades() as $core_grade) {
            $core_id = sanitize_text_field((string) ($core_grade['grade_id'] ?? ''));
            $name = sanitize_text_field((string) ($core_grade['grade_name'] ?? ''));
            if ($core_id === '') {
                continue;
            }

            $local_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE core_grade_id = %s", $core_id));
            if (!$local_id) {
                $local_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE core_grade_id IS NULL AND grade_level = %s ORDER BY id LIMIT 1",
                    $core_id
                ));
            }
            if (!$local_id && $name !== '') {
                $local_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE core_grade_id IS NULL AND grade_name = %s ORDER BY id LIMIT 1",
                    $name
                ));
            }

            $data = array(
                'core_grade_id' => $core_id,
                'academic_source' => 'oracle',
                'grade_name' => $name !== '' ? $name : $core_id,
                'grade_level' => $core_id,
            );

            if ($local_id) {
                $wpdb->update($table, $data, array('id' => (int) $local_id));
            } else {
                $wpdb->insert($table, $data + array('periods_count' => 8, 'is_active' => 1));
            }
        }
    }

    private static function sync_sections($study_year)
    {
        global $wpdb;

        $grade_table = $wpdb->prefix . 'olama_grades';
        $section_table = $wpdb->prefix . 'olama_sections';
        $academic_year_id = self::get_academic_year_id($study_year);

        $grade_map = $wpdb->get_results(
            "SELECT id, core_grade_id FROM {$grade_table} WHERE core_grade_id IS NOT NULL",
            OBJECT_K
        );
        $local_grades = array();
        foreach ($grade_map as $row) {
            $local_grades[(string) $row->core_grade_id] = (int) $row->id;
        }

        foreach (olama_core()->academic()->grade_sections($study_year) as $relation) {
            $core_grade_id = sanitize_text_field((string) ($relation['grade_id'] ?? ''));
            $core_section_id = sanitize_text_field((string) ($relation['section_id'] ?? ''));
            $name = sanitize_text_field((string) ($relation['section_name'] ?? ''));

            // Oracle uses 0/-1 as placeholders rather than teachable sections.
            if ($core_grade_id === '' || $core_section_id === '' || in_array($core_section_id, array('0', '-1'), true)) {
                continue;
            }
            if (!isset($local_grades[$core_grade_id])) {
                continue;
            }

            $local_grade_id = $local_grades[$core_grade_id];
            $local_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$section_table}
                 WHERE core_study_year = %s AND core_grade_id = %s AND core_section_id = %s",
                $study_year,
                $core_grade_id,
                $core_section_id
            ));
            if (!$local_id && $name !== '') {
                $local_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$section_table}
                     WHERE core_section_id IS NULL AND academic_year_id = %d AND grade_id = %d AND section_name = %s
                     ORDER BY id LIMIT 1",
                    $academic_year_id,
                    $local_grade_id,
                    $name
                ));
            }

            $data = array(
                'academic_year_id' => $academic_year_id,
                'grade_id' => $local_grade_id,
                'core_grade_id' => $core_grade_id,
                'core_section_id' => $core_section_id,
                'core_study_year' => $study_year,
                'academic_source' => 'oracle',
                'section_name' => $name !== '' ? $name : $core_section_id,
            );

            if ($local_id) {
                $wpdb->update($section_table, $data, array('id' => (int) $local_id));
            } else {
                $wpdb->insert($section_table, $data + array('room_number' => ''));
            }
        }
    }

    private static function sync_subjects($study_year)
    {
        global $wpdb;

        $grade_table = $wpdb->prefix . 'olama_grades';
        $subject_table = $wpdb->prefix . 'olama_subjects';
        $grade_rows = $wpdb->get_results(
            "SELECT id, core_grade_id FROM {$grade_table} WHERE core_grade_id IS NOT NULL"
        );
        $local_grades = array();
        foreach ($grade_rows as $grade) {
            $local_grades[(string) $grade->core_grade_id] = (int) $grade->id;
        }

        foreach (olama_core()->academic()->grade_subjects($study_year) as $relation) {
            $core_grade_id = sanitize_text_field((string) ($relation['grade_id'] ?? ''));
            $core_subject_id = sanitize_text_field((string) ($relation['subject_id'] ?? ''));
            $name = sanitize_text_field((string) ($relation['subject_name'] ?? ''));
            if ($core_grade_id === '' || $core_subject_id === '' || !isset($local_grades[$core_grade_id])) {
                continue;
            }

            $local_grade_id = $local_grades[$core_grade_id];
            $local_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$subject_table}
                 WHERE core_study_year = %s AND core_grade_id = %s AND core_subject_id = %s",
                $study_year,
                $core_grade_id,
                $core_subject_id
            ));

            if (!$local_id && $name !== '') {
                $candidates = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, subject_name FROM {$subject_table}
                     WHERE grade_id = %d AND core_subject_id IS NULL ORDER BY id",
                    $local_grade_id
                ));
                $canonical_name = self::canonical_subject_name($name);
                foreach ($candidates as $candidate) {
                    if (self::canonical_subject_name($candidate->subject_name) === $canonical_name) {
                        $local_id = (int) $candidate->id;
                        break;
                    }
                }
            }

            $data = array(
                'core_study_year' => $study_year,
                'core_grade_id' => $core_grade_id,
                'core_subject_id' => $core_subject_id,
                'academic_source' => 'oracle',
                'grade_id' => $local_grade_id,
                'subject_name' => $name !== '' ? $name : $core_subject_id,
                'is_active' => empty($relation['is_active']) ? 0 : 1,
            );

            if ($local_id) {
                $wpdb->update($subject_table, $data, array('id' => (int) $local_id));
            } else {
                $wpdb->insert($subject_table, $data + array(
                    'subject_code' => $core_subject_id,
                    'color_code' => self::subject_color($core_subject_id),
                    'max_weekly_plans' => 0,
                ));
            }
        }
    }

    private static function canonical_subject_name($name)
    {
        $name = (string) $name;
        $name = preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $name);
        $name = strtr($name, array('أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه'));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', '', $name);

        $aliases = array(
            'الدراساتالاجتماعيه' => 'اجتماعيات',
            'التربيهالاجتماعيه' => 'اجتماعيات',
            'مهني' => 'تربيهمهنيه',
            'التربيهالمهنيه' => 'تربيهمهنيه',
            'المهاراتالرقميه' => 'مهاراترقميه',
            'مهاراترقميه' => 'مهاراترقميه',
        );

        return $aliases[$name] ?? $name;
    }

    private static function subject_color($subject_id)
    {
        $palette = array('#2271b1', '#8c564b', '#2ca02c', '#9467bd', '#d62728', '#ff7f0e', '#17becf', '#7f7f7f');
        return $palette[abs(crc32((string) $subject_id)) % count($palette)];
    }
}
