<?php
/**
 * Exam Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Exam
{
    /**
     * Get exams based on filters
     */
    public static function get_exams($year_id, $semester_id, $grade_id, $subject_id = 0)
    {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}olama_exams WHERE academic_year_id = %d AND semester_id = %d AND grade_id = %d";
        $params = array($year_id, $semester_id, $grade_id);

        if ($subject_id > 0) {
            $query .= " AND subject_id = %d";
            $params[] = $subject_id;
        }

        $query .= " ORDER BY exam_date ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Save an exam
     */
    public static function save_exam($data)
    {
        global $wpdb;

        $fields = array(
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'grade_id' => intval($data['grade_id']),
            'subject_id' => intval($data['subject_id']),
            'evaluation_type' => sanitize_text_field($data['evaluation_type']),
            'exam_date' => sanitize_text_field($data['exam_date']),
            'description' => sanitize_textarea_field($data['description']),
            'student_book_material' => sanitize_textarea_field($data['student_book_material']),
            'workbook_material' => sanitize_textarea_field($data['workbook_material'] ?? ''),
            'exercise_book_material' => sanitize_textarea_field($data['exercise_book_material'] ?? ''),
            'notebook_material' => sanitize_textarea_field($data['notebook_material'] ?? ''),
            'teacher_notes' => sanitize_textarea_field($data['teacher_notes']),
        );

        if (!empty($data['id'])) {
            return $wpdb->update(
                "{$wpdb->prefix}olama_exams",
                $fields,
                array('id' => intval($data['id']))
            );
        } else {
            return $wpdb->insert(
                "{$wpdb->prefix}olama_exams",
                $fields
            );
        }
    }

    /**
     * Delete an exam
     */
    public static function delete_exam($exam_id)
    {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}olama_exams", array('id' => intval($exam_id)));
    }

    /**
     * Get a single exam
     */
    public static function get_exam($exam_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_exams WHERE id = %d",
            $exam_id
        ));
    }
}
