<?php
/**
 * School Evaluation Curriculum Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Curriculum
{
    /**
     * Domains CRUD
     */
    public static function get_domains($template_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_domains WHERE template_id = %d ORDER BY sort_order ASC",
            $template_id
        ));
    }

    public static function save_domain($data)
    {
        global $wpdb;
        $fields = array(
            'template_id' => intval($data['template_id']),
            'title_ar' => sanitize_text_field($data['title_ar']),
            'context_type' => sanitize_text_field($data['context_type'] ?? 'student'),
            'sort_order' => intval($data['sort_order'] ?? 0)
        );

        if (!empty($data['id'])) {
            return $wpdb->update("{$wpdb->prefix}olama_ev_domains", $fields, array('id' => intval($data['id'])));
        }
        return $wpdb->insert("{$wpdb->prefix}olama_ev_domains", $fields);
    }

    public static function delete_domain($id)
    {
        global $wpdb;
        // Delete categories and indicators first (Cascading logic in code)
        $categories = self::get_categories($id);
        foreach ($categories as $cat) {
            self::delete_category($cat->id);
        }
        return $wpdb->delete("{$wpdb->prefix}olama_ev_domains", array('id' => intval($id)));
    }

    /**
     * Categories CRUD
     */
    public static function get_categories($domain_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_categories WHERE domain_id = %d ORDER BY sort_order ASC",
            $domain_id
        ));
    }

    public static function save_category($data)
    {
        global $wpdb;
        $fields = array(
            'domain_id' => intval($data['domain_id']),
            'title_ar' => sanitize_text_field($data['title_ar']),
            'sort_order' => intval($data['sort_order'] ?? 0)
        );

        if (!empty($data['id'])) {
            return $wpdb->update("{$wpdb->prefix}olama_ev_categories", $fields, array('id' => intval($data['id'])));
        }
        return $wpdb->insert("{$wpdb->prefix}olama_ev_categories", $fields);
    }

    public static function delete_category($id)
    {
        global $wpdb;
        // Delete indicators first
        $wpdb->delete("{$wpdb->prefix}olama_ev_indicators", array('category_id' => intval($id)));
        return $wpdb->delete("{$wpdb->prefix}olama_ev_categories", array('id' => intval($id)));
    }

    /**
     * Indicators CRUD
     */
    public static function get_indicators($category_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_ev_indicators WHERE category_id = %d ORDER BY sort_order ASC",
            $category_id
        ));
    }

    public static function save_indicator($data)
    {
        global $wpdb;
        $fields = array(
            'category_id' => intval($data['category_id']),
            'indicator_text' => sanitize_textarea_field($data['indicator_text']),
            'max_score' => intval($data['max_score'] ?? 5),
            'weight' => floatval($data['weight'] ?? 1.00),
            'is_critical' => !empty($data['is_critical']) ? 1 : 0,
            'context_type' => sanitize_text_field($data['context_type'] ?? 'student'),
            'sort_order' => intval($data['sort_order'] ?? 0)
        );

        if (!empty($data['id'])) {
            return $wpdb->update("{$wpdb->prefix}olama_ev_indicators", $fields, array('id' => intval($data['id'])));
        }
        return $wpdb->insert("{$wpdb->prefix}olama_ev_indicators", $fields);
    }

    public static function delete_indicator($id)
    {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}olama_ev_indicators", array('id' => intval($id)));
    }

    /**
     * Bulk Data Retrieval for Evaluation Form
     */
    public static function get_full_curriculum($template_id)
    {
        $domains = self::get_domains($template_id);
        foreach ($domains as &$domain) {
            $domain->categories = self::get_categories($domain->id);
            foreach ($domain->categories as &$category) {
                $category->indicators = self::get_indicators($category->id);
            }
        }
        return $domains;
    }
}
