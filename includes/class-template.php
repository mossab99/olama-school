<?php
/**
 * Template Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Template
{

    /**
     * Get templates by grade and subject
     */
    public static function get_templates($grade_id, $subject_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_templates WHERE grade_id = %d AND subject_id = %d",
            $grade_id,
            $subject_id
        ));
    }

    /**
     * Save template
     */
    public static function save_template($data)
    {
        global $wpdb;
        return $wpdb->insert(
            "{$wpdb->prefix}olama_templates",
            array(
                'template_name' => sanitize_text_field($data['template_name']),
                'grade_id' => intval($data['grade_id']),
                'subject_id' => intval($data['subject_id']),
                'template_data' => maybe_serialize($data['template_data']),
                'teacher_id' => get_current_user_id()
            )
        );
    }

    /**
     * Get template by ID
     */
    public static function get_template($id)
    {
        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_templates WHERE id = %d",
            $id
        ));
        if ($template) {
            $template->template_data = maybe_unserialize($template->template_data);
        }
        return $template;
    }
}