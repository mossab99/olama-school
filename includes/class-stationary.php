<?php
/**
 * Stationary Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Stationary
{
    /**
     * Get stationary for a grade and year
     */
    public static function get_stationary($year_id, $grade_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_stationary WHERE academic_year_id = %d AND grade_id = %d",
            $year_id,
            $grade_id
        ));
    }

    /**
     * Save stationary requirements
     */
    public static function save_stationary($data)
    {
        global $wpdb;

        $fields = array(
            'academic_year_id' => absint($data['academic_year_id'] ?? 0),
            'grade_id' => absint($data['grade_id'] ?? 0),
            'teacher_notes' => sanitize_textarea_field(wp_unslash($data['teacher_notes'] ?? '')),
        );

        if (!$fields['academic_year_id'] || !$fields['grade_id']) {
            return new WP_Error('invalid_stationary_scope', __('An academic year and grade are required.', 'olama-school'));
        }

        $existing = self::get_stationary($fields['academic_year_id'], $fields['grade_id']);

        if ($existing) {
            $grade_result = $wpdb->update(
                "{$wpdb->prefix}olama_stationary",
                $fields,
                array('id' => $existing->id)
            );
        } else {
            $grade_result = $wpdb->insert(
                "{$wpdb->prefix}olama_stationary",
                $fields
            );
        }

        if ($grade_result === false) {
            return new WP_Error('stationary_grade_save_failed', $wpdb->last_error ?: __('Unable to save class teacher notes.', 'olama-school'));
        }

        $submitted = isset($data['subject_requirements']) && is_array($data['subject_requirements'])
            ? $data['subject_requirements']
            : array();

        foreach (Olama_School_Subject::get_for_stationary($fields['grade_id']) as $subject) {
            $subject_id = absint($subject->id);
            $requirements = isset($submitted[$subject_id]) && is_array($submitted[$subject_id])
                ? $submitted[$subject_id]
                : array();
            $row = array(
                'academic_year_id' => $fields['academic_year_id'],
                'grade_id' => $fields['grade_id'],
                'subject_id' => $subject_id,
                'notebooks' => sanitize_textarea_field(wp_unslash($requirements['notebooks'] ?? '')),
                'stationary' => sanitize_textarea_field(wp_unslash($requirements['stationary'] ?? '')),
            );

            $result = $wpdb->replace("{$wpdb->prefix}olama_subject_stationary", $row);
            if ($result === false) {
                return new WP_Error('stationary_subject_save_failed', $wpdb->last_error ?: __('Unable to save subject stationery.', 'olama-school'));
            }
        }

        return true;
    }

    /**
     * Get subject-level requirements for a grade, keyed by local subject ID.
     */
    public static function get_subject_stationary($year_id, $grade_id)
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_subject_stationary
             WHERE academic_year_id = %d AND grade_id = %d
             ORDER BY subject_id ASC",
            absint($year_id),
            absint($grade_id)
        ));
        $indexed = array();
        foreach ($rows as $row) {
            $subject = Olama_School_Subject::get_subject($row->subject_id);
            $row->subject_name = $subject ? $subject->subject_name : '';
            $indexed[(int) $row->subject_id] = $row;
        }
        return $indexed;
    }

    /**
     * Get all stationary records for a given academic year, joined with grades
     */
    public static function get_all_stationary_by_year($year_id)
    {
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, g.id AS grade_id, g.grade_name, g.grade_level
             FROM {$wpdb->prefix}olama_grades g
             LEFT JOIN {$wpdb->prefix}olama_stationary s
                ON s.grade_id = g.id AND s.academic_year_id = %d
             WHERE EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}olama_subjects subj
                    WHERE subj.grade_id = g.id
                        AND subj.requires_stationary = 1
                        AND subj.is_active = 1
                )
             ORDER BY CAST(g.grade_level AS SIGNED) ASC",
            $year_id
        ));
        foreach ($items as $item) {
            $enabled_ids = array_fill_keys(array_map(static function ($subject) {
                return (int) $subject->id;
            }, Olama_School_Subject::get_for_stationary($item->grade_id)), true);
            $item->subjects = array_values(array_filter(
                self::get_subject_stationary($year_id, $item->grade_id),
                static function ($requirement) use ($enabled_ids) {
                    return isset($enabled_ids[(int) $requirement->subject_id]);
                }
            ));
        }
        return $items;
    }
}
