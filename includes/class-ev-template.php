<?php
/**
 * School Evaluation Template Model
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Template
{
    public static function get_templates($grade_id, $academic_year_id, $semester_id = 0, $context_type = 'student')
    {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}olama_ev_templates 
                  WHERE grade_id = %d AND academic_year_id = %d AND context_type = %s";
        $params = array($grade_id, $academic_year_id, $context_type);

        if ($semester_id) {
            $query .= " AND semester_id = %d";
            $params[] = $semester_id;
        }

        $query .= " ORDER BY created_at DESC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    public static function get_template($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d",
            $id
        ));
    }

    public static function save_template($data)
    {
        global $wpdb;

        $score_input = isset($data['score_config']) ? $data['score_config'] : array();

        // Filter out empty labels and limit to 5
        $score_filtered = array_filter($score_input, function ($label) {
            return !empty(trim($label));
        });
        $score_filtered = array_values($score_filtered); // Reset keys
        $score_filtered = array_slice($score_filtered, 0, 5);

        $final_config = array();
        $total = count($score_filtered);
        foreach ($score_filtered as $index => $label) {
            // Highest label gets highest numeric value (starts from 1 up to N)
            $final_config[$total - $index] = $label;
        }

        $fields = array(
            'academic_year_id' => intval($data['academic_year_id']),
            'grade_id' => intval($data['grade_id']),
            'semester_id' => intval($data['semester_id'] ?? 0),
            'template_name' => sanitize_text_field($data['template_name']),
            'context_type' => sanitize_text_field($data['context_type'] ?? 'student'),
            'score_config' => !empty($final_config) ? maybe_serialize($final_config) : null,
        );

        if (!empty($data['id'])) {
            $wpdb->update("{$wpdb->prefix}olama_ev_templates", $fields, array('id' => intval($data['id'])));
            return intval($data['id']);
        }

        $wpdb->insert("{$wpdb->prefix}olama_ev_templates", $fields);
        return $wpdb->insert_id;
    }

    public static function get_default_score_config()
    {
        return array(
            3 => 'Mastered',
            2 => 'Partially Mastered',
            1 => 'Not Mastered'
        );
    }

    public static function get_score_config($template_id)
    {
        $template = self::get_template($template_id);
        if ($template && !empty($template->score_config)) {
            $config = maybe_unserialize($template->score_config);
            if (is_array($config)) {
                krsort($config); // Sort key descending (highest score first)
                return $config;
            }
        }
        return self::get_default_score_config();
    }

    public static function delete_template($id)
    {
        global $wpdb;
        // Delete child structures
        $domains = Olama_School_EV_Curriculum::get_domains($id);
        foreach ($domains as $domain) {
            Olama_School_EV_Curriculum::delete_domain($domain->id);
        }
        return $wpdb->delete("{$wpdb->prefix}olama_ev_templates", array('id' => intval($id)));
    }

    public static function copy_template($data)
    {
        global $wpdb;

        $source_id = intval($data['source_template_id']);
        $source_template = self::get_template($source_id);

        if (!$source_template) {
            return false;
        }

        // 1. Create new template
        $new_template_data = array(
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'grade_id' => intval($data['destination_grade_id']),
            'template_name' => sanitize_text_field($data['new_template_name']),
            'context_type' => sanitize_text_field($data['context_type'] ?? 'student'),
            'score_config' => $source_template->score_config // Copy existing config
        );

        $wpdb->insert("{$wpdb->prefix}olama_ev_templates", $new_template_data);
        $new_template_id = $wpdb->insert_id;

        if (!$new_template_id) {
            return false;
        }

        // 2. Fetch and duplicate structure
        $domains = Olama_School_EV_Curriculum::get_domains($source_id);

        foreach ($domains as $domain) {
            $domain_data = array(
                'template_id' => $new_template_id,
                'title_ar' => $domain->title_ar,
                'context_type' => $domain->context_type,
                'sort_order' => $domain->sort_order
            );
            $wpdb->insert("{$wpdb->prefix}olama_ev_domains", $domain_data);
            $new_domain_id = $wpdb->insert_id;

            $categories = Olama_School_EV_Curriculum::get_categories($domain->id);
            foreach ($categories as $category) {
                $category_data = array(
                    'domain_id' => $new_domain_id,
                    'title_ar' => $category->title_ar,
                    'sort_order' => $category->sort_order
                );
                $wpdb->insert("{$wpdb->prefix}olama_ev_categories", $category_data);
                $new_category_id = $wpdb->insert_id;

                $indicators = Olama_School_EV_Curriculum::get_indicators($category->id);
                foreach ($indicators as $indicator) {
                    $indicator_data = array(
                        'category_id' => $new_category_id,
                        'indicator_text' => $indicator->indicator_text,
                        'max_score' => $indicator->max_score,
                        'weight' => $indicator->weight,
                        'is_critical' => $indicator->is_critical,
                        'context_type' => $indicator->context_type,
                        'sort_order' => $indicator->sort_order
                    );
                    $wpdb->insert("{$wpdb->prefix}olama_ev_indicators", $indicator_data);
                }
            }
        }

        return $new_template_id;
    }
}
