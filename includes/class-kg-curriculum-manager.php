<?php
/**
 * KG Curriculum Manager (Admin UI Logic)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_KG_Curriculum_Manager
{
    public function render_page()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

        $grades = Olama_School_Grade::get_grades();
        $kg_grades = array();
        foreach ($grades as $g) {
            if (stripos($g->grade_name, 'KG') !== false || stripos($g->grade_level, 'KG') !== false) {
                $kg_grades[] = $g;
            }
        }
        if (empty($kg_grades))
            $kg_grades = $grades;

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($kg_grades) ? $kg_grades[0]->id : 0);

        $templates = Olama_School_KG_Template::get_templates($selected_grade_id, $selected_year_id);
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

        $current_template = null;
        $curriculum = array();
        if ($selected_template_id) {
            $current_template = Olama_School_KG_Template::get_template($selected_template_id);
            $curriculum = Olama_School_KG_Curriculum::get_full_curriculum($selected_template_id);
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/kg-curriculum.php';
    }

    /**
     * Handle Curriculum Actions (POST)
     */
    public function handle_actions()
    {
        if (!isset($_POST['olama_kg_action']) || !current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('olama_kg_curriculum_action', 'olama_kg_curriculum_action');

        $action = sanitize_text_field($_POST['olama_kg_action']);
        $result = false;

        switch ($action) {
            case 'save_template':
                $result = Olama_School_KG_Template::save_template($_POST);
                break;
            case 'delete_template':
                $result = Olama_School_KG_Template::delete_template($_POST['id']);
                break;
            case 'save_domain':
                $result = Olama_School_KG_Curriculum::save_domain($_POST);
                break;
            case 'delete_domain':
                $result = Olama_School_KG_Curriculum::delete_domain($_POST['id']);
                break;
            case 'save_category':
                $result = Olama_School_KG_Curriculum::save_category($_POST);
                break;
            case 'delete_category':
                $result = Olama_School_KG_Curriculum::delete_category($_POST['id']);
                break;
            case 'save_indicator':
                $result = Olama_School_KG_Curriculum::save_indicator($_POST);
                break;
            case 'delete_indicator':
                $result = Olama_School_KG_Curriculum::delete_indicator($_POST['id']);
                break;
        }

        if ($result) {
            $url = wp_get_referer();
            $url = remove_query_arg(array('message', 'id'), $url);

            if ($action === 'save_template' && is_numeric($result)) {
                $url = add_query_arg('template_id', $result, $url);
            }
            if ($action === 'delete_template') {
                $url = remove_query_arg('template_id', $url);
            }

            $url = add_query_arg('message', 'kg_success', $url);
            wp_redirect($url);
            exit;
        }
    }
}
