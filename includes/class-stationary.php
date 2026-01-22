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
            'academic_year_id' => intval($data['academic_year_id']),
            'grade_id' => intval($data['grade_id']),
            'notebooks' => sanitize_textarea_field($data['notebooks'] ?? ''),
            'stationary' => sanitize_textarea_field($data['stationary'] ?? ''),
            'teacher_notes' => sanitize_textarea_field($data['teacher_notes'] ?? ''),
        );

        $existing = self::get_stationary($fields['academic_year_id'], $fields['grade_id']);

        if ($existing) {
            return $wpdb->update(
                "{$wpdb->prefix}olama_stationary",
                $fields,
                array('id' => $existing->id)
            );
        } else {
            return $wpdb->insert(
                "{$wpdb->prefix}olama_stationary",
                $fields
            );
        }
    }
}
