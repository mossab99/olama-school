<?php
/**
 * Evaluation Form (Teacher UI Logic)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Form
{
    public function render_page($context = null)
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;

        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : $active_year_id;
        $selected_year = Olama_School_Academic::get_year($selected_year_id);
        $selected_year_name = $selected_year ? $selected_year->year_name : '';

        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));
        $selected_semester = Olama_School_Academic::get_semester($selected_semester_id);
        $selected_semester_name = $selected_semester ? $selected_semester->semester_name : '';

        // Context handling: passed $context takes priority over $_GET
        $context_type = $context ?: (isset($_GET['context']) ? sanitize_text_field($_GET['context']) : 'student');
        $visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;
        $visit = null;
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

        if ($context_type === 'supervisor' && $visit_id) {
            global $wpdb;
            $visit = $wpdb->get_row($wpdb->prepare(
                "SELECT v.*, s.academic_year_id, s.semester_id, s.teacher_id, t.template_name 
                 FROM {$wpdb->prefix}olama_supervisor_visits v
                 JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
                 LEFT JOIN {$wpdb->prefix}olama_ev_records r ON r.related_entity_id = v.id AND r.related_entity_type = 'supervisor_visit'
                 LEFT JOIN {$wpdb->prefix}olama_ev_templates t ON r.template_id = t.id
                 WHERE v.id = %d",
                $visit_id
            ));

            if ($visit) {
                $selected_year_id = $visit->academic_year_id;
                $selected_semester_id = $visit->semester_id;
                // If template_id not in GET, try to get from record linked to visit
                if (!$selected_template_id) {
                    $ev_rec = $wpdb->get_row($wpdb->prepare(
                        "SELECT template_id FROM {$wpdb->prefix}olama_ev_records WHERE related_entity_id = %d AND related_entity_type = 'supervisor_visit'",
                        $visit_id
                    ));
                    if ($ev_rec) {
                        $selected_template_id = $ev_rec->template_id;
                    }
                }
            }
        }

        // Get sections ONLY for the selected academic year
        $sections = Olama_School_Section::get_sections_by_year($selected_year_id);
        $selected_section_id = (isset($_GET['section_id']) && !empty($_GET['section_id'])) ? intval($_GET['section_id']) : (!empty($sections) ? $sections[0]->id : 0);

        // Fetch grade_id from selected section for template filtering
        $grade_id = 0;
        $section = Olama_School_Section::get_section($selected_section_id);
        if ($section) {
            $grade_id = $section->grade_id;
        }

        $students = array();
        $templates = array();
        $evaluation_statuses = array();

        if ($context_type === 'supervisor') {
            // Fetch supervisor templates for the current grade/year/semester
            $templates = Olama_School_EV_Template::get_templates($grade_id, $selected_year_id, $selected_semester_id, 'supervisor');
        } elseif ($selected_section_id && $context_type === 'student') {
            $students = Olama_School_Student::get_students(array('section_id' => $selected_section_id, 'academic_year_id' => $selected_year_id));

            // Fetch student templates based on section's grade
            if ($grade_id) {
                $templates = Olama_School_EV_Template::get_templates($grade_id, $selected_year_id, 0, 'student');
            }

            if ($selected_template_id) {
                $evaluation_statuses = Olama_School_EV_Record::get_student_evaluation_statuses($selected_year_id, $selected_semester_id, $selected_template_id);
            }
        }

        $selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
        $curriculum = array();
        $evaluation = null;
        $scores = array();

        if ($selected_template_id && ($selected_student_id || ($context_type === 'supervisor' && $visit_id))) {
            $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($selected_template_id);
            $evaluation = Olama_School_EV_Record::get_evaluation($selected_student_id, $selected_year_id, $selected_semester_id, $selected_template_id, $context_type, $visit_id);
            if ($evaluation) {
                $scores = Olama_School_EV_Record::get_scores($evaluation->id);
            }
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/ev-form.php';
    }

    /**
     * Handle Evaluation Save (POST)
     */
    public function handle_save()
    {
        if (!isset($_POST['olama_ev_save_eval']) || !is_user_logged_in()) {
            return;
        }

        check_admin_referer('olama_ev_save', 'olama_ev_save');

        $evaluation_id = Olama_School_EV_Record::save_evaluation($_POST);

        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            foreach ($_POST['scores'] as $indicator_id => $data) {
                $score = isset($data['score']) ? $data['score'] : null;
                $notes = isset($data['notes']) ? $data['notes'] : '';
                Olama_School_EV_Record::save_score($evaluation_id, $indicator_id, $score, $notes);
            }
        }

        $redirect_url = remove_query_arg('message', wp_get_referer());
        $redirect_url = add_query_arg('message', 'ev_eval_saved', $redirect_url);

        if (isset($_POST['status']) && $_POST['status'] === 'published') {
            $redirect_url = admin_url('admin.php?action=ev_print_report&evaluation_id=' . $evaluation_id);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * AJAX Autosave
     */
    public function ajax_autosave()
    {
        check_ajax_referer('olama_kg_evaluation_nonce', 'nonce');

        if (!isset($_POST['evaluation_data'])) {
            wp_send_json_error('No data');
        }

        $data = $_POST['evaluation_data'];
        $evaluation_id = Olama_School_EV_Record::save_evaluation($data);

        if (isset($data['scores']) && is_array($data['scores'])) {
            foreach ($data['scores'] as $score_entry) {
                Olama_School_EV_Record::save_score(
                    $evaluation_id,
                    $score_entry['indicator_id'],
                    $score_entry['score'],
                    $score_entry['notes'] ?? ''
                );
            }
        }

        wp_send_json_success(array('evaluation_id' => $evaluation_id));
    }
}
