<?php
/**
 * KG Student Evaluation Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_KG_Evaluation
{
    /**
     * Get evaluation record
     */
    public static function get_evaluation($student_id, $year_id, $semester_id, $template_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_kg_evaluations 
             WHERE student_id = %d AND academic_year_id = %d AND semester_id = %d AND template_id = %d",
            $student_id,
            $year_id,
            $semester_id,
            $template_id
        ));
    }

    /**
     * Save/Create evaluation record
     */
    public static function save_evaluation($data)
    {
        global $wpdb;

        $fields = array(
            'template_id' => intval($data['template_id']),
            'student_id' => intval($data['student_id']),
            'teacher_id' => get_current_user_id(),
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'status' => sanitize_text_field($data['status'] ?? 'draft')
        );

        $existing = self::get_evaluation($fields['student_id'], $fields['academic_year_id'], $fields['semester_id'], $fields['template_id']);

        if ($existing) {
            $wpdb->update("{$wpdb->prefix}olama_kg_evaluations", $fields, array('id' => $existing->id));
            return $existing->id;
        } else {
            $wpdb->insert("{$wpdb->prefix}olama_kg_evaluations", $fields);
            return $wpdb->insert_id;
        }
    }

    /**
     * Get scores for an evaluation
     */
    public static function get_scores($evaluation_id)
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_kg_evaluation_scores WHERE evaluation_id = %d",
            $evaluation_id
        ));

        $scores = array();
        foreach ($results as $row) {
            $scores[$row->indicator_id] = $row;
        }
        return $scores;
    }

    /**
     * Save individual score
     */
    public static function save_score($evaluation_id, $indicator_id, $score, $notes = '')
    {
        global $wpdb;

        $fields = array(
            'evaluation_id' => intval($evaluation_id),
            'indicator_id' => intval($indicator_id),
            'score' => !is_null($score) ? intval($score) : null,
            'notes' => sanitize_textarea_field($notes)
        );

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_kg_evaluation_scores 
             WHERE evaluation_id = %d AND indicator_id = %d",
            $evaluation_id,
            $indicator_id
        ));

        if ($existing) {
            return $wpdb->update("{$wpdb->prefix}olama_kg_evaluation_scores", $fields, array('id' => $existing->id));
        } else {
            return $wpdb->insert("{$wpdb->prefix}olama_kg_evaluation_scores", $fields);
        }
    }
}
