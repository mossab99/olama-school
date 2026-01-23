<?php
/**
 * KG Evaluation Form (Teacher UI Logic)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_KG_Evaluation_Form
{
    public function render_page()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;

        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : $active_year_id;
        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : (!empty($semesters) ? $semesters[0]->id : 0);

        $sections = Olama_School_Section::get_sections();
        $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : (!empty($sections) ? $sections[0]->id : 0);

        $students = array();
        if ($selected_section_id) {
            $students = Olama_School_Student::get_students(array('section_id' => $selected_section_id, 'academic_year_id' => $selected_year_id));
        }
        $selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

        $templates = array();
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

        $curriculum = array();
        $evaluation = null;
        $scores = array();

        if ($selected_student_id) {
            $enrollment = Olama_School_Student::get_student_enrollment($selected_student_id, $selected_year_id);
            if ($enrollment) {
                // Fetch templates for this grade
                $templates = Olama_School_KG_Template::get_templates($enrollment->grade_id, $selected_year_id);

                if ($selected_template_id) {
                    $curriculum = Olama_School_KG_Curriculum::get_full_curriculum($selected_template_id);
                    $evaluation = Olama_School_KG_Evaluation::get_evaluation($selected_student_id, $selected_year_id, $selected_semester_id, $selected_template_id);
                    if ($evaluation) {
                        $scores = Olama_School_KG_Evaluation::get_scores($evaluation->id);
                    }
                }
            }
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/kg-evaluation-form.php';
    }

    /**
     * Handle Evaluation Save (POST)
     */
    public function handle_save()
    {
        if (!isset($_POST['olama_kg_save_eval']) || !is_user_logged_in()) {
            return;
        }

        check_admin_referer('olama_kg_evaluation_save', 'olama_kg_evaluation_save');

        $evaluation_id = Olama_School_KG_Evaluation::save_evaluation($_POST);

        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            foreach ($_POST['scores'] as $indicator_id => $data) {
                $score = isset($data['score']) ? $data['score'] : null;
                $notes = isset($data['notes']) ? $data['notes'] : '';
                Olama_School_KG_Evaluation::save_score($evaluation_id, $indicator_id, $score, $notes);
            }
        }

        $redirect_url = remove_query_arg('message', wp_get_referer());
        $redirect_url = add_query_arg('message', 'kg_eval_saved', $redirect_url);
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
        $evaluation_id = Olama_School_KG_Evaluation::save_evaluation($data);

        if (isset($data['scores']) && is_array($data['scores'])) {
            foreach ($data['scores'] as $score_entry) {
                Olama_School_KG_Evaluation::save_score(
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
