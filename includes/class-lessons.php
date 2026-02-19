<?php
/**
 * Curriculum Lesson Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Lesson
{
    /**
     * Get lessons by unit ID
     */
    public static function get_lessons($unit_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum_lessons 
            WHERE unit_id = %d 
            ORDER BY CAST(lesson_number AS UNSIGNED) ASC, lesson_number ASC",
            $unit_id
        ));
    }

    /**
     * Get all lessons for a subject and grade (via Units)
     */
    public static function get_all_by_subject_and_grade($subject_id, $grade_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.unit_number, u.unit_name 
            FROM {$wpdb->prefix}olama_curriculum_lessons l
            JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
            WHERE u.subject_id = %d AND u.grade_id = %d
            ORDER BY CAST(u.unit_number AS UNSIGNED) ASC, CAST(l.lesson_number AS UNSIGNED) ASC",
            $subject_id,
            $grade_id
        ));
    }

    /**
     * Add or update a lesson
     */
    public static function save_lesson($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_curriculum_lessons";

        $lesson_data = array(
            'unit_id' => intval($data['unit_id']),
            'lesson_number' => sanitize_text_field($data['lesson_number']),
            'lesson_title' => sanitize_text_field($data['lesson_title']),
            'video_url' => esc_url_raw($data['video_url'] ?? ''),
            'periods' => intval($data['periods'] ?? 1),
        );

        // Only update dates if explicitly provided or if it's a new lesson
        if (isset($data['start_date']) || empty($data['id'])) {
            $lesson_data['start_date'] = !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null;
        }
        if (isset($data['end_date']) || empty($data['id'])) {
            $lesson_data['end_date'] = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null;
        }

        // Check for duplicate lesson number
        $duplicate_check = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table 
            WHERE lesson_number = %s 
            AND unit_id = %d AND id != %d",
            $lesson_data['lesson_number'],
            $lesson_data['unit_id'],
            isset($data['id']) ? intval($data['id']) : 0
        ));

        if ($duplicate_check) {
            return new WP_Error('duplicate_lesson_number', sprintf(__('Lesson #%s already exists in this unit.', 'olama-school'), $lesson_data['lesson_number']));
        }

        // Suppress errors from outputting HTML and capture them instead
        $wpdb->suppress_errors(true);

        if (!empty($data['id'])) {
            $result = $wpdb->update($table, $lesson_data, array('id' => intval($data['id'])));
            return array(
                'id' => intval($data['id']),
                'result' => $result,
                'error' => $wpdb->last_error,
                'query' => $wpdb->last_query,
                'data' => $lesson_data
            );
        } else {
            $result = $wpdb->insert($table, $lesson_data);
            return array(
                'id' => $wpdb->insert_id,
                'result' => $result,
                'error' => $wpdb->last_error,
                'query' => $wpdb->last_query,
                'data' => $lesson_data
            );
        }
    }

    /**
     * Delete a lesson (only if it has no questions)
     */
    public static function delete_lesson($id)
    {
        global $wpdb;
        $id = intval($id);

        // Check if lesson has questions
        $question_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_curriculum_questions WHERE lesson_id = %d",
            $id
        ));

        if ($question_count > 0) {
            return new WP_Error(
                'has_questions',
                sprintf(
                    __('Cannot delete this lesson. It has %d question(s). Please delete all questions first.', 'olama-school'),
                    $question_count
                )
            );
        }

        return $wpdb->delete("{$wpdb->prefix}olama_curriculum_lessons", array('id' => $id));
    }

    /**
     * Get single lesson
     */
    public static function get_lesson($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum_lessons WHERE id = %d",
            $id
        ));
    }
}