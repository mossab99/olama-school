<?php
/**
 * KG Evaluation Template Model
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_KG_Template
{
    public static function get_templates($grade_id, $academic_year_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_kg_templates 
             WHERE grade_id = %d AND academic_year_id = %d 
             ORDER BY created_at DESC",
            $grade_id,
            $academic_year_id
        ));
    }

    public static function get_template($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_kg_templates WHERE id = %d",
            $id
        ));
    }

    public static function save_template($data)
    {
        global $wpdb;
        $fields = array(
            'academic_year_id' => intval($data['academic_year_id']),
            'grade_id' => intval($data['grade_id']),
            'semester_id' => intval($data['semester_id'] ?? 0),
            'template_name' => sanitize_text_field($data['template_name']),
        );

        if (!empty($data['id'])) {
            $wpdb->update("{$wpdb->prefix}olama_kg_templates", $fields, array('id' => intval($data['id'])));
            return intval($data['id']);
        }

        $wpdb->insert("{$wpdb->prefix}olama_kg_templates", $fields);
        return $wpdb->insert_id;
    }

    public static function delete_template($id)
    {
        global $wpdb;
        // Should we delete domains? Yes, or re-link. User likely wants cascading delete.
        $domains = Olama_School_KG_Curriculum::get_domains($id);
        foreach ($domains as $domain) {
            Olama_School_KG_Curriculum::delete_domain($domain->id);
        }
        return $wpdb->delete("{$wpdb->prefix}olama_kg_templates", array('id' => intval($id)));
    }
}
