<?php
/**
 * Service to manage supervisor visits.
 */

namespace Olama\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SupervisorVisitService
{
    /**
     * Creates a new supervisor visit record.
     * 
     * @param array $data [ schedule_id, supervisor_id, visit_date, notes ]
     * @return int|false The visit ID or false on failure.
     */
    public static function create_visit($data)
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            "{$wpdb->prefix}olama_supervisor_visits",
            [
                'schedule_id' => intval($data['schedule_id']),
                'supervisor_id' => intval($data['supervisor_id']),
                'visit_date' => sanitize_text_field($data['visit_date']),
                'status' => 'planned',
                'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Retrieves a visit record by ID with schedule details.
     * 
     * @param int $visit_id
     * @return object|null
     */
    public static function get_visit($visit_id)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT v.*, s.day_name, s.period_number, sub.subject_name, t.id as teacher_id
             FROM {$wpdb->prefix}olama_supervisor_visits v
             JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
             JOIN {$wpdb->prefix}olama_subjects sub ON s.subject_id = sub.id
             JOIN {$wpdb->prefix}olama_teacher_assignments ta ON (s.section_id = ta.section_id AND s.subject_id = ta.subject_id)
             JOIN {$wpdb->prefix}olama_teachers t ON ta.teacher_id = t.id
             WHERE v.id = %d",
            $visit_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Updates the status and final score of a visit.
     * 
     * @param int $visit_id
     * @param string $status 'completed', 'approved'
     * @param float $final_score
     * @return bool
     */
    public static function update_visit_completion($visit_id, $status, $final_score)
    {
        global $wpdb;

        return $wpdb->update(
            "{$wpdb->prefix}olama_supervisor_visits",
            [
                'status' => sanitize_text_field($status),
                'final_score' => floatval($final_score)
            ],
            ['id' => intval($visit_id)],
            ['%s', '%f'],
            ['%d']
        );
    }

    /**
     * Retrieves upcoming supervisor visits for a specific teacher.
     * 
     * @param int $teacher_id
     * @param int $limit
     * @return array
     */
    public static function get_teacher_upcoming_visits($teacher_id, $limit = 5)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT v.*, s.day_name, s.period_number, sub.subject_name, sec.section_name, g.grade_name, u.display_name as supervisor_name
             FROM {$wpdb->prefix}olama_supervisor_visits v
             JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
             JOIN {$wpdb->prefix}olama_subjects sub ON s.subject_id = sub.id
             JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
             JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
             JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
             JOIN {$wpdb->prefix}olama_teacher_assignments ta ON (s.section_id = ta.section_id AND s.subject_id = ta.subject_id)
             WHERE ta.teacher_id = %d AND v.status = 'planned' AND v.visit_date >= CURDATE()
             ORDER BY v.visit_date ASC
             LIMIT %d",
            $teacher_id,
            $limit
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Retrieves recent completed supervisor visits for a specific teacher.
     * 
     * @param int $teacher_id
     * @param int $limit
     * @return array
     */
    public static function get_teacher_completed_visits($teacher_id, $limit = 5)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT v.*, s.day_name, s.period_number, sub.subject_name, sec.section_name, g.grade_name, u.display_name as supervisor_name
             FROM {$wpdb->prefix}olama_supervisor_visits v
             JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
             JOIN {$wpdb->prefix}olama_subjects sub ON s.subject_id = sub.id
             JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
             JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
             JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
             JOIN {$wpdb->prefix}olama_teacher_assignments ta ON (s.section_id = ta.section_id AND s.subject_id = ta.subject_id)
             WHERE ta.teacher_id = %d AND v.status = 'completed'
             ORDER BY v.visit_date DESC
             LIMIT %d",
            $teacher_id,
            $limit
        );

        return $wpdb->get_results($sql);
    }
}
