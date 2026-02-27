<?php
/**
 * Evaluation Manager (Admin UI Logic)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Manager
{
    public function render_page()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
        $selected_year = Olama_School_Academic::get_year($selected_year_id);
        $selected_year_name = $selected_year ? $selected_year->year_name : '';

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));
        $selected_semester = Olama_School_Academic::get_semester($selected_semester_id);
        $selected_semester_name = $selected_semester ? $selected_semester->semester_name : '';

        $templates = Olama_School_EV_Template::get_templates($selected_grade_id, $selected_year_id, $selected_semester_id);
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

        $current_template = null;
        $curriculum = array();
        if ($selected_template_id) {
            $current_template = Olama_School_EV_Template::get_template($selected_template_id);
            $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($selected_template_id);
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/ev-manager.php';
    }

    /**
     * Handle Curriculum Actions (POST)
     */
    public function handle_actions()
    {
        if (!isset($_POST['olama_ev_action']) || !Olama_School_Permissions::can('olama_manage_evaluation_mgmt')) {
            return;
        }

        check_admin_referer('olama_ev_curriculum_action', 'olama_ev_curriculum_action');

        $action = sanitize_text_field($_POST['olama_ev_action']);
        $result = false;

        switch ($action) {
            case 'save_template':
                $result = Olama_School_EV_Template::save_template($_POST);
                break;
            case 'delete_template':
                $result = Olama_School_EV_Template::delete_template($_POST['id']);
                break;
            case 'save_domain':
                $result = Olama_School_EV_Curriculum::save_domain($_POST);
                break;
            case 'delete_domain':
                $result = Olama_School_EV_Curriculum::delete_domain($_POST['id']);
                break;
            case 'save_category':
                $result = Olama_School_EV_Curriculum::save_category($_POST);
                break;
            case 'delete_category':
                $result = Olama_School_EV_Curriculum::delete_category($_POST['id']);
                break;
            case 'save_indicator':
                $result = Olama_School_EV_Curriculum::save_indicator($_POST);
                break;
            case 'delete_indicator':
                $result = Olama_School_EV_Curriculum::delete_indicator($_POST['id']);
                break;
            case 'remove_all_orphans':
                $count = Olama_School_EV_Record::delete_orphaned_records();
                $result = array(
                    'status' => 'orphans_removed',
                    'count' => $count
                );
                break;
        }

        if ($result !== false) {
            $url = wp_get_referer() ?: admin_url('admin.php?page=olama-school-evaluation&tab=evaluation_mgmt');
            $url = remove_query_arg(array('message', 'id'), $url);

            if (is_array($result) && isset($result['status']) && $result['status'] === 'orphans_removed') {
                $url = admin_url('admin.php?page=olama-school-evaluation&tab=evaluation_mgmt&message=orphans_removed&count=' . $result['count']);
            } else {
                // Ensure we stay on the management tab
                if (strpos($url, 'tab=evaluation_mgmt') === false) {
                    $url = add_query_arg('tab', 'evaluation_mgmt', $url);
                }

                if ($action === 'save_template' && is_numeric($result)) {
                    $url = add_query_arg('template_id', $result, $url);
                } elseif (!empty($_POST['template_id'])) {
                    $url = add_query_arg('template_id', intval($_POST['template_id']), $url);
                }

                if ($action === 'delete_template') {
                    $url = remove_query_arg('template_id', $url);
                }

                $url = add_query_arg('message', 'ev_success', $url);
            }
            wp_redirect($url);
            exit;
        }
    }
}
