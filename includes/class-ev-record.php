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
    public static function get_evaluation($student_id, $year_id, $semester_id, $template_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_records 
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

        $student_id = intval($data['student_id']);

        // Look up the student's stable UID (ID Number) for future-proofing
        $student_uid = null;
        $student = $wpdb->get_row($wpdb->prepare(
            "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
            $student_id
        ));
        if ($student) {
            $student_uid = $student->student_uid;
        }

        $fields = array(
            'template_id' => intval($data['template_id']),
            'student_id' => $student_id,
            'student_uid' => $student_uid,
            'teacher_id' => get_current_user_id(),
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
            'supervisor_comments' => isset($data['supervisor_comments']) ? sanitize_textarea_field($data['supervisor_comments']) : null
        );

        $existing = self::get_evaluation($fields['student_id'], $fields['academic_year_id'], $fields['semester_id'], $fields['template_id']);

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

        $fields = array(
            'evaluation_id' => intval($evaluation_id),
            'indicator_id' => intval($indicator_id),
            'score' => !is_null($score) ? intval($score) : null,
            'notes' => sanitize_textarea_field($notes)
        );

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_ev_scores 
             WHERE evaluation_id = %d AND indicator_id = %d",
            $evaluation_id,
            $indicator_id
        ));

        if ($existing) {
            return $wpdb->update("{$wpdb->prefix}olama_ev_scores", $fields, array('id' => $existing->id));
        } else {
            return $wpdb->insert("{$wpdb->prefix}olama_ev_scores", $fields);
        }
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
