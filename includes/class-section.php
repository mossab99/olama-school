<?php
/**
 * Section Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Section
{
    private static $cache = array();


    /**
     * Get all sections
     */
    public static function get_sections()
    {
        if (isset(self::$cache['all_sections'])) {
            return self::$cache['all_sections'];
        }
        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year();
        if (Olama_School_Academic_Bridge::sync($study_year)) {
            $results = self::query_core_sections($study_year);
        } else {
            $results = $wpdb->get_results("SELECT s.*, g.grade_name FROM {$wpdb->prefix}olama_sections s JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id");
        }
        self::$cache['all_sections'] = $results;
        return $results;
    }

    /**
     * Get all sections for a specific academic year
     */
    public static function get_sections_by_year($academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $cache_key = 'all_sections_year_' . $academic_year_id;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year($academic_year_id);
        if (Olama_School_Academic_Bridge::sync($study_year)) {
            $results = self::query_core_sections($study_year);
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, g.grade_name
                 FROM {$wpdb->prefix}olama_sections s
                 JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
                 WHERE s.academic_year_id = %d
                 ORDER BY CAST(g.grade_level AS SIGNED), s.section_name",
                $academic_year_id
            ));
        }
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Get sections by grade and academic year
     */
    public static function get_by_grade($grade_id, $academic_year_id = 0)
    {
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $cache_key = 'sections_grade_' . $grade_id . '_year_' . $academic_year_id;
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        global $wpdb;
        $study_year = Olama_School_Academic_Bridge::get_study_year($academic_year_id);
        if (Olama_School_Academic_Bridge::sync($study_year)) {
            $results = self::query_core_sections($study_year, $grade_id);
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND academic_year_id = %d",
                $grade_id,
                $academic_year_id
            ));
        }
        self::$cache[$cache_key] = $results;
        return $results;
    }

    /**
     * Get a single section by ID
     */
    public static function get_section($id)
    {
        if (isset(self::$cache['section_' . $id])) {
            return self::$cache['section_' . $id];
        }
        global $wpdb;
        if (Olama_School_Academic_Bridge::is_available()) {
            $core_grade_sections = olama_core()->read_models()->table('academic_grade_sections');
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, gs.section_name AS section_name, gs.grade_name AS grade_name,
                        gs.section_id AS oracle_section_id, gs.grade_id AS oracle_grade_id,
                        gs.last_synced_at AS oracle_last_synced_at
                 FROM {$wpdb->prefix}olama_sections s
                 INNER JOIN {$core_grade_sections} gs
                    ON gs.study_year = s.core_study_year
                   AND gs.grade_id = s.core_grade_id
                   AND gs.section_id = s.core_section_id
                 WHERE s.id = %d",
                $id
            ));
            if (!$row) {
                // Preserve access to historical School sections that are not
                // members of the current Oracle study-year snapshot.
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}olama_sections WHERE id = %d",
                    $id
                ));
            }
        } else {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_sections WHERE id = %d",
                $id
            ));
        }
        self::$cache['section_' . $id] = $row;
        return $row;
    }

    /**
     * Add section
     */
    public static function add_section($data)
    {
        if (Olama_School_Academic_Bridge::is_available()) {
            return new WP_Error('oracle_managed', __('Sections are managed by Oracle and synchronized through Olama Core.', 'olama-school'));
        }

        global $wpdb;

        // Check for duplicates in the same grade and academic year
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s AND academic_year_id = %d",
            $data['grade_id'],
            $data['section_name'],
            $data['academic_year_id']
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade in the selected academic year.', 'olama-school'));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_sections",
            array(
                'academic_year_id' => $data['academic_year_id'],
                'grade_id' => $data['grade_id'],
                'section_name' => $data['section_name'],
                'room_number' => $data['room_number'] ?? '',
            )
        );
    }

    /**
     * Update section
     */
    public static function update_section($id, $data)
    {
        if (Olama_School_Academic_Bridge::is_available()) {
            return new WP_Error('oracle_managed', __('Section membership and names are managed by Oracle.', 'olama-school'));
        }

        global $wpdb;

        // Check for duplicates (excluding current ID)
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE grade_id = %d AND section_name = %s AND academic_year_id = %d AND id != %d",
            $data['grade_id'],
            $data['section_name'],
            $data['academic_year_id'],
            $id
        ));

        if ($exists) {
            return new WP_Error('duplicate_section', __('A section with this name already exists for this grade in the selected academic year.', 'olama-school'));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_sections",
            array(
                'academic_year_id' => $data['academic_year_id'],
                'grade_id' => $data['grade_id'],
                'section_name' => $data['section_name'],
                'room_number' => $data['room_number'] ?? '',
            ),
            array('id' => $id)
        );
    }

    /**
     * Delete section with validation
     */
    public static function delete_section($id)
    {
        if (Olama_School_Academic_Bridge::is_available()) {
            return new WP_Error('oracle_managed', __('Sections are managed by Oracle and cannot be deleted in Olama School.', 'olama-school'));
        }

        global $wpdb;

        // Check for related Core enrollment records.
        $section = self::get_section($id);
        $core_student_years = olama_core()->read_models()->table('student_years');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$core_student_years}
             WHERE study_year = %s AND class_id = %s AND section_id = %s",
            (string) ($section->core_study_year ?? ''),
            (string) ($section->core_grade_id ?? ''),
            (string) ($section->core_section_id ?? '')
        ));

        if ($count > 0) {
            return new WP_Error('linked_records', sprintf(__('This section is linked to %d students.', 'olama-school'), $count));
        }

        return $wpdb->delete("{$wpdb->prefix}olama_sections", array('id' => $id));
    }

    private static function query_core_sections($study_year, $grade_id = 0)
    {
        global $wpdb;

        $where = 'WHERE gs.study_year = %s';
        $values = array($study_year);
        if ($grade_id) {
            $where .= ' AND s.grade_id = %d';
            $values[] = $grade_id;
        }

        $core_grade_sections = olama_core()->read_models()->table('academic_grade_sections');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, gs.section_name AS section_name, gs.grade_name AS grade_name,
                    gs.section_id AS oracle_section_id, gs.grade_id AS oracle_grade_id,
                    gs.last_synced_at AS oracle_last_synced_at
             FROM {$wpdb->prefix}olama_sections s
             INNER JOIN {$core_grade_sections} gs
                ON gs.study_year = s.core_study_year
               AND gs.grade_id = s.core_grade_id
               AND gs.section_id = s.core_section_id
             {$where}
             ORDER BY CAST(gs.grade_id AS SIGNED), gs.grade_id,
                      CAST(gs.section_id AS SIGNED), gs.section_id",
            $values
        ));
    }
}
