<?php
/**
 * Curriculum Question Bank Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Question_Bank
{
    /**
     * Get questions by lesson ID
     */
    public static function get_questions($lesson_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum_questions 
            WHERE lesson_id = %d ORDER BY id ASC",
            $lesson_id
        ));
    }

    /**
     * Add or update a question
     */
    public static function save_question($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_curriculum_questions";

        $question_data = array(
            'lesson_id' => intval($data['lesson_id']),
            'question_number' => sanitize_text_field($data['question_number'] ?? ''),
            'question' => sanitize_textarea_field($data['question'] ?? $data['question_text'] ?? ''),
            'answer' => sanitize_textarea_field($data['answer'] ?? $data['answer_text'] ?? ''),
        );

        // Check for duplicate question number
        if (!empty($question_data['question_number'])) {
            $duplicate_check = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table 
                WHERE question_number = %s 
                AND lesson_id = %d AND id != %d",
                $question_data['question_number'],
                $question_data['lesson_id'],
                isset($data['id']) ? intval($data['id']) : 0
            ));

            if ($duplicate_check) {
                return new WP_Error('duplicate_question_number', sprintf(__('Question #%s already exists in this lesson.', 'olama-school'), $question_data['question_number']));
            }
        }

        if (!empty($data['id'])) {
            $result = $wpdb->update($table, $question_data, array('id' => intval($data['id'])));
            return intval($data['id']);
        } else {
            $result = $wpdb->insert($table, $question_data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete a question
     */
    public static function delete_question($id)
    {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}olama_curriculum_questions", array('id' => intval($id)));
    }
}