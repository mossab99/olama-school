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
    public static function get_exams($year_id, $semester_id, $grade_id, $subject_id = 0, $semester_exam_id = 0)
    {
        global $wpdb;
        $query = "SELECT e.*, se.room_number as master_room 
                  FROM {$wpdb->prefix}olama_exams e
                  LEFT JOIN {$wpdb->prefix}olama_semester_exams se ON e.semester_exam_id = se.id
                  WHERE e.academic_year_id = %d AND e.semester_id = %d AND e.grade_id = %d";
        $params = array($year_id, $semester_id, $grade_id);

        if ($subject_id > 0) {
            $query .= " AND e.subject_id = %d";
            $params[] = $subject_id;
        }

        if ($semester_exam_id > 0) {
            $query .= " AND e.semester_exam_id = %d";
            $params[] = $semester_exam_id;
        }

        $query .= " ORDER BY e.exam_date ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Save an exam
     */
    public static function save_exam($data)
    {
        global $wpdb;

        $exam_id = !empty($data['id']) ? intval($data['id']) : 0;
        $existing = null;
        if ($exam_id) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_exams WHERE id = %d", $exam_id), ARRAY_A);
        }

        $fields = array();
        $keys = array(
            'academic_year_id' => 'intval',
            'semester_id' => 'intval',
            'semester_exam_id' => 'intval',
            'grade_id' => 'intval',
            'subject_id' => 'intval',
            'evaluation_type' => 'sanitize_text_field',
            'exam_date' => 'sanitize_text_field',
            'room_number' => 'sanitize_text_field',
            'description' => 'sanitize_textarea_field',
            'student_book_material' => 'sanitize_textarea_field',
            'workbook_material' => 'sanitize_textarea_field',
            'exercise_book_material' => 'sanitize_textarea_field',
            'notebook_material' => 'sanitize_textarea_field',
            'teacher_notes' => 'sanitize_textarea_field',
            'exam_material_json' => 'wp_unslash', // JSON data, needs special handling
            'status' => 'sanitize_text_field'
        );

        foreach ($keys as $key => $sanitizer) {
            if (isset($data[$key]) && $data[$key] !== '') {
                if ($key === 'exam_material_json') {
                    // Validate and sanitize JSON data
                    $json_data = is_string($data[$key]) ? $data[$key] : json_encode($data[$key]);
                    $fields[$key] = $json_data;
                } elseif ($sanitizer === 'intval') {
                    $fields[$key] = intval($data[$key]);
                } else {
                    $fields[$key] = $sanitizer($data[$key]);
                }
            } elseif ($existing && isset($existing[$key])) {
                $fields[$key] = $existing[$key];
            } elseif ($key === 'status') {
                $fields[$key] = 'draft';
            }
        }

        if ($exam_id) {
            $result = $wpdb->update(
                "{$wpdb->prefix}olama_exams",
                $fields,
                array('id' => $exam_id)
            );
            // Return true for 0 rows (no changes), check for actual errors
            if ($result === false) {
                error_log('Olama save_exam error: ' . $wpdb->last_error);
                return new WP_Error('db_error', $wpdb->last_error ?: 'Unknown database error');
            }
            return $result !== false ? true : $result;
        } else {
            $result = $wpdb->insert(
                "{$wpdb->prefix}olama_exams",
                $fields
            );
            if ($result === false) {
                error_log('Olama save_exam insert error: ' . $wpdb->last_error);
                return new WP_Error('db_error', $wpdb->last_error ?: 'Unknown database error');
            }
            return $result;
        }
    }

    /**
     * Get exams assigned to a teacher
     */
    public static function get_teacher_exams($teacher_id, $academic_year_id, $semester_exam_id)
    {
        global $wpdb;
        $query = "SELECT e.*, s.subject_name, g.grade_name 
                  FROM {$wpdb->prefix}olama_exams e
                  JOIN {$wpdb->prefix}olama_subjects s ON e.subject_id = s.id
                  JOIN {$wpdb->prefix}olama_grades g ON e.grade_id = g.id
                  WHERE e.academic_year_id = %d 
                  AND e.semester_exam_id = %d
                  AND e.subject_id IN (
                      SELECT DISTINCT subject_id 
                      FROM {$wpdb->prefix}olama_teacher_assignments 
                      WHERE teacher_id = %d AND academic_year_id = %d
                  )
                  ORDER BY e.exam_date ASC";

        return $wpdb->get_results($wpdb->prepare($query, $academic_year_id, $semester_exam_id, $teacher_id, $academic_year_id));
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
