<?php
/**
 * School Student Evaluation Record Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Record
{
    /**
     * Get evaluation record
     */
    public static function get_evaluation($student_id, $year_id, $semester_id, $template_id, $context_type = 'student', $related_id = null, $subject_id = null)
    {
        global $wpdb;

        if ($context_type === 'supervisor' && $related_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_ev_records 
                 WHERE related_entity_id = %d AND context_type = 'supervisor' AND template_id = %d",
                $related_id,
                $template_id
            ));
        }

        $query = "SELECT * FROM {$wpdb->prefix}olama_ev_records 
                  WHERE student_id = %d AND academic_year_id = %d AND semester_id = %d AND template_id = %d AND context_type = %s";
        $params = array($student_id, $year_id, $semester_id, $template_id, $context_type);

        if ($subject_id) {
            $query .= " AND subject_id = %d";
            $params[] = $subject_id;
        }

        return $wpdb->get_row($wpdb->prepare($query, $params));
    }

    /**
     * Save/Create evaluation record
     */
    public static function save_evaluation($data)
    {
        global $wpdb;

        $context_type = sanitize_text_field($data['context_type'] ?? 'student');
        $student_id = isset($data['student_id']) ? intval($data['student_id']) : null;
        $student_uid = null;

        if ($student_id) {
            $student = $wpdb->get_row($wpdb->prepare(
                "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
                $student_id
            ));
            if ($student) {
                $student_uid = $student->student_uid;
            }
        }

        $fields = array(
            'template_id' => intval($data['template_id']),
            'student_id' => $student_id,
            'student_uid' => $student_uid,
            'subject_id' => isset($data['subject_id']) ? intval($data['subject_id']) : null,
            'teacher_id' => get_current_user_id(),
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'context_type' => $context_type,
            'related_entity_type' => sanitize_text_field($data['related_entity_type'] ?? null),
            'related_entity_id' => isset($data['related_entity_id']) ? intval($data['related_entity_id']) : null,
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'supervisor_comments' => isset($data['supervisor_comments']) ? sanitize_textarea_field($data['supervisor_comments']) : null
        );

        $existing = self::get_evaluation(
            $fields['student_id'],
            $fields['academic_year_id'],
            $fields['semester_id'],
            $fields['template_id'],
            $fields['context_type'],
            $fields['related_entity_id']
        );

        if ($existing) {
            $wpdb->update("{$wpdb->prefix}olama_ev_records", $fields, array('id' => $existing->id));
            return $existing->id;
        } else {
            $wpdb->insert("{$wpdb->prefix}olama_ev_records", $fields);
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
            "SELECT * FROM {$wpdb->prefix}olama_ev_scores WHERE evaluation_id = %d",
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

        // Fetch indicator weight and critical status for accurate scoring
        $indicator = $wpdb->get_row($wpdb->prepare(
            "SELECT weight, is_critical FROM {$wpdb->prefix}olama_ev_indicators WHERE id = %d",
            $indicator_id
        ));

        $calculated_score = null;
        if ($indicator && !is_null($score)) {
            $weight = (float) $indicator->weight;
            $multiplier = (bool) $indicator->is_critical ? 2.0 : 1.0;
            // Non-linear points calculation: (Rating^2 / 5) * Weight * Crit_Multiplier
            $clamped_score = min(5.0, (float) $score);
            $points = ($clamped_score * $clamped_score) / 5.0;
            $calculated_score = $points * $weight * $multiplier;
        }

        $fields = array(
            'evaluation_id' => intval($evaluation_id),
            'indicator_id' => intval($indicator_id),
            'score' => !is_null($score) ? intval($score) : null,
            'calculated_score' => $calculated_score,
            'notes' => sanitize_textarea_field($notes)
        );

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_ev_scores 
             WHERE evaluation_id = %d AND indicator_id = %d",
            $evaluation_id,
            $indicator_id
        ));

        if ($existing) {
            $wpdb->update("{$wpdb->prefix}olama_ev_scores", $fields, array('id' => $existing->id));
        } else {
            $wpdb->insert("{$wpdb->prefix}olama_ev_scores", $fields);
        }

        // After saving individual score, update the total if it's a supervisor visit
        self::sync_total_score($evaluation_id);

        return true;
    }

    /**
     * Syncs total weighted score to the related entity (e.g., Supervisor Visit)
     */
    public static function sync_total_score($evaluation_id)
    {
        global $wpdb;

        $evaluation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_records WHERE id = %d",
            $evaluation_id
        ));

        if (!$evaluation || $evaluation->context_type !== 'supervisor' || !$evaluation->related_entity_id) {
            return;
        }

        // Calculate total using Service
        $scores = $wpdb->get_results($wpdb->prepare(
            "SELECT s.score as rating, i.weight, i.is_critical 
             FROM {$wpdb->prefix}olama_ev_scores s
             JOIN {$wpdb->prefix}olama_ev_indicators i ON s.indicator_id = i.id
             WHERE s.evaluation_id = %d AND s.score IS NOT NULL",
            $evaluation_id
        ), ARRAY_A);

        $result = \Olama\Services\EvaluationScoringService::calculate_score($scores);

        // Update supervisor_visits table
        \Olama\Services\SupervisorVisitService::update_visit_completion(
            $evaluation->related_entity_id,
            $evaluation->status === 'published' ? 'completed' : 'planned',
            $result['percentage']
        );
    }

    /**
     * Get statuses of student evaluations for a specific context
     */
    public static function get_student_evaluation_statuses($year_id, $semester_id, $template_id)
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id as student_id, r.status, r.supervisor_comments
             FROM {$wpdb->prefix}olama_ev_records r
             JOIN {$wpdb->prefix}olama_students s ON r.student_uid = s.student_uid
             WHERE r.academic_year_id = %d AND r.semester_id = %d AND r.template_id = %d",
            $year_id,
            $semester_id,
            $template_id
        ));

        $statuses = array();
        foreach ($results as $row) {
            $statuses[$row->student_id] = array(
                'status' => $row->status,
                'has_comments' => !empty($row->supervisor_comments)
            );
        }
        return $statuses;
    }

    /**
     * Delete orphaned evaluation records and their scores
     */
    public static function delete_orphaned_records()
    {
        global $wpdb;
        $count = 0;

        // 1. Delete scores with no matching evaluation record
        $wpdb->query("DELETE s FROM {$wpdb->prefix}olama_ev_scores s 
                      LEFT JOIN {$wpdb->prefix}olama_ev_records r ON s.evaluation_id = r.id 
                      WHERE r.id IS NULL");

        // 2. Identify and delete orphaned evaluation records
        // Orphaned if student, template, year, or semester is missing
        $orphaned_ids = $wpdb->get_col(
            "SELECT r.id FROM {$wpdb->prefix}olama_ev_records r
             LEFT JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id
             LEFT JOIN {$wpdb->prefix}olama_ev_templates t ON r.template_id = t.id
             LEFT JOIN {$wpdb->prefix}olama_academic_years y ON r.academic_year_id = y.id
             LEFT JOIN {$wpdb->prefix}olama_semesters sem ON r.semester_id = sem.id
             WHERE s.id IS NULL 
                OR t.id IS NULL 
                OR y.id IS NULL 
                OR (r.semester_id IS NOT NULL AND sem.id IS NULL)"
        );

        if (!empty($orphaned_ids)) {
            $ids_str = implode(',', array_map('intval', $orphaned_ids));

            // Delete associated scores first
            $wpdb->query("DELETE FROM {$wpdb->prefix}olama_ev_scores WHERE evaluation_id IN ($ids_str)");

            // Delete records
            $count = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}olama_ev_records WHERE id IN ($ids_str)");
        }

        return $count;
    }
}
