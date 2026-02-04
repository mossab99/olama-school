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
            'supervisor_comments' => 'sanitize_textarea_field',
            'exam_material_json' => 'wp_unslash', // JSON data, needs special handling
            'status' => 'sanitize_text_field'
        );

        // Fetch existing data if needed for validation
        $val_date = isset($data['exam_date']) ? $data['exam_date'] : ($existing ? $existing['exam_date'] : '');
        $val_grade = isset($data['grade_id']) ? $data['grade_id'] : ($existing ? $existing['grade_id'] : 0);
        $val_status = isset($data['status']) ? $data['status'] : ($existing ? $existing['status'] : 'draft');

        // Date validation: Prevent duplicate subjects for same grade on same date
        if ($val_date && $val_grade) {
            $sanitized_val_date = Olama_School_Helpers::sanitize_date($val_date);

            // For now, we allow multiple subjects in 'draft' or 'not completed' status on the same date,
            // but we STRICTLY block more than one 'approved' exam per grade per date.
            // Actually, the user says "the system allowed two approved exams". 
            // Let's block same-date subjects if ANY of them are to be approved, or if another is already approved.

            $query = "SELECT id, status FROM {$wpdb->prefix}olama_exams 
                     WHERE grade_id = %d AND exam_date = %s AND id != %d";
            $conflicts = $wpdb->get_results($wpdb->prepare($query, $val_grade, $sanitized_val_date, $exam_id));

            foreach ($conflicts as $conflict) {
                // If the current save is for 'approved' status, or if a conflict is already 'approved'
                if ($val_status === 'approved' || $conflict->status === 'approved') {
                    return new WP_Error('duplicate_date', Olama_School_Helpers::translate('This grade already has an approved exam on this date. Please change the date before approving.'));
                }
            }
        }

        foreach ($keys as $key => $sanitizer) {
            if (isset($data[$key])) {
                $value = $data[$key];
                if ($key === 'exam_material_json') {
                    // Handle JSON data with unslashing
                    if (is_string($value)) {
                        $value = wp_unslash($value);
                    }
                    $fields[$key] = is_string($value) ? $value : json_encode($value);
                } elseif ($sanitizer === 'intval') {
                    $fields[$key] = intval($value);
                } elseif (is_callable($sanitizer)) {
                    $fields[$key] = $sanitizer($value);
                } else {
                    $fields[$key] = $value;
                }
            } elseif ($existing && array_key_exists($key, $existing)) {
                $fields[$key] = $existing[$key];
            } elseif ($key === 'status') {
                $fields[$key] = 'draft';
            }
        }

        // Mandatory material validation for Approval status
        if ($val_status === 'approved') {
            $desc = isset($fields['description']) ? trim($fields['description']) : '';
            $json_str = isset($fields['exam_material_json']) ? $fields['exam_material_json'] : '';
            $material = json_decode($json_str, true);

            // Check curriculum items in JSON
            $has_curriculum = !empty($material['curriculum_items']);

            // Check booklets and teacher notes (favoring JSON structure but falling back to columns)
            $booklets = !empty($material['booklets_notebooks']) ? trim($material['booklets_notebooks']) : (isset($fields['notebook_material']) ? trim($fields['notebook_material']) : '');
            $notes = !empty($material['teacher_notes']) ? trim($material['teacher_notes']) : (isset($fields['teacher_notes']) ? trim($fields['teacher_notes']) : '');

            $missing = array();
            if (empty($desc)) {
                $missing[] = Olama_School_Helpers::translate('Exam Description');
            }
            if (!$has_curriculum) {
                $missing[] = Olama_School_Helpers::translate('Curriculum Material');
            }
            if (empty($booklets)) {
                $missing[] = Olama_School_Helpers::translate('Booklets & Notebooks');
            }
            if (empty($notes)) {
                $missing[] = Olama_School_Helpers::translate('Teacher Notes');
            }

            if (!empty($missing)) {
                return new WP_Error(
                    'missing_material',
                    Olama_School_Helpers::translate('Approval denied! Please fill the required fields first:') . ' ' . implode(', ', $missing)
                );
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
