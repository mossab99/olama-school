<?php
/**
 * Olama School AJAX Handlers Class
 * Handles all AJAX requests for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Ajax_Handlers
{
    /**
     * Constructor - Register all AJAX handlers
     */
    public function __construct()
    {
        // Curriculum AJAX Handlers
        add_action('wp_ajax_olama_save_curriculum_unit', array($this, 'save_curriculum_unit'));
        add_action('wp_ajax_olama_get_curriculum_units', array($this, 'get_curriculum_units'));
        add_action('wp_ajax_olama_delete_curriculum_unit', array($this, 'delete_curriculum_unit'));

        add_action('wp_ajax_olama_save_curriculum_lesson', array($this, 'save_curriculum_lesson'));
        add_action('wp_ajax_olama_get_curriculum_lessons', array($this, 'get_curriculum_lessons'));
        add_action('wp_ajax_olama_delete_curriculum_lesson', array($this, 'delete_curriculum_lesson'));

        add_action('wp_ajax_olama_save_curriculum_question', array($this, 'save_curriculum_question'));
        add_action('wp_ajax_olama_get_curriculum_questions', array($this, 'get_curriculum_questions'));
        add_action('wp_ajax_olama_delete_curriculum_question', array($this, 'delete_curriculum_question'));
        add_action('wp_ajax_olama_clear_curriculum', array($this, 'clear_curriculum'));
        add_action('wp_ajax_olama_clear_grade_curriculum', array($this, 'clear_grade_curriculum'));
        add_action('wp_ajax_olama_force_clear_all_curriculum', array($this, 'force_clear_all_curriculum'));

        add_action('wp_ajax_olama_get_scheduled_subjects', array($this, 'get_scheduled_subjects'));
        add_action('wp_ajax_olama_get_subjects_by_grade', array($this, 'get_subjects_by_grade'));
        add_action('wp_ajax_olama_delete_plan', array($this, 'delete_plan'));

        // Timeline AJAX Handlers
        add_action('wp_ajax_olama_get_timeline_data', array($this, 'get_timeline_data'));
        add_action('wp_ajax_olama_save_timeline_dates', array($this, 'save_timeline_dates'));
        add_action('wp_ajax_olama_bulk_approve_plans', array($this, 'bulk_approve_plans'));

        // Bulk Upload AJAX Handler
        add_action('wp_ajax_olama_bulk_upload_curriculum', array($this, 'bulk_upload_curriculum'));

        // Teacher Assignment AJAX Handlers
        add_action('wp_ajax_olama_get_teacher_assignments', array($this, 'get_teacher_assignments'));
        add_action('wp_ajax_olama_get_teacher_summary', array($this, 'get_teacher_summary'));
        add_action('wp_ajax_olama_get_sections_by_grade', array($this, 'get_sections_by_grade'));
        add_action('wp_ajax_olama_toggle_teacher_assignment', array($this, 'toggle_teacher_assignment'));
    }

    // ==========================================
    // Curriculum Unit Handlers
    // ==========================================

    public function save_curriculum_unit()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $received_data = array(
            'grade_id' => isset($_POST['grade_id']) ? $_POST['grade_id'] : 'NOT SET',
            'subject_id' => isset($_POST['subject_id']) ? $_POST['subject_id'] : 'NOT SET',
            'semester_id' => isset($_POST['semester_id']) ? $_POST['semester_id'] : 'NOT SET',
            'unit_name' => isset($_POST['unit_name']) ? $_POST['unit_name'] : 'NOT SET',
            'unit_number' => isset($_POST['unit_number']) ? $_POST['unit_number'] : 'NOT SET',
        );

        $result = Olama_School_Unit::save_unit($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => is_array($result) ? $result['id'] : $result,
                'message' => __('Unit saved successfully', 'olama-school'),
                'debug_received' => $received_data,
                'save_result' => $result
            ));
        }
    }

    public function get_curriculum_units()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $subject_id = intval($_REQUEST['subject_id']);
        $grade_id = intval($_REQUEST['grade_id']);
        $semester_id = intval($_REQUEST['semester_id']);
        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        wp_send_json_success($units);
    }

    public function delete_curriculum_unit()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Unit::delete_unit(intval($_POST['id']));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    // ==========================================
    // Curriculum Lesson Handlers
    // ==========================================

    public function save_curriculum_lesson()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $received_data = array(
            'unit_id' => isset($_POST['unit_id']) ? $_POST['unit_id'] : 'NOT SET',
            'lesson_title' => isset($_POST['lesson_title']) ? $_POST['lesson_title'] : 'NOT SET',
            'lesson_number' => isset($_POST['lesson_number']) ? $_POST['lesson_number'] : 'NOT SET',
        );

        $result = Olama_School_Lesson::save_lesson($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => is_array($result) ? $result['id'] : $result,
                'message' => __('Lesson saved successfully', 'olama-school'),
                'debug_received' => $received_data,
                'save_result' => $result
            ));
        }
    }

    public function get_curriculum_lessons()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $lessons = Olama_School_Lesson::get_lessons(intval($_REQUEST['unit_id']));
        wp_send_json_success($lessons);
    }

    public function delete_curriculum_lesson()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Lesson::delete_lesson(intval($_POST['id']));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    // ==========================================
    // Curriculum Question Handlers
    // ==========================================

    public function save_curriculum_question()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Question_Bank::save_question($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('Question saved successfully', 'olama-school')
            ));
        }
    }

    public function get_curriculum_questions()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $questions = Olama_School_Question_Bank::get_questions(intval($_REQUEST['lesson_id']));

        // Map fields for compatibility
        $normalized = array_map(function ($q) {
            $q->question_text = $q->question;
            $q->answer_text = $q->answer;
            return $q;
        }, $questions);

        wp_send_json_success($normalized);
    }

    public function delete_curriculum_question()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $id = intval($_POST['id']);
        if ($id > 0) {
            Olama_School_Question_Bank::delete_question($id);
            wp_send_json_success();
        }
        wp_send_json_error(__('Invalid ID', 'olama-school'));
    }

    // ==========================================
    // Subject and Schedule Handlers
    // ==========================================

    public function get_subjects_by_grade()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $grade_id = intval($_REQUEST['grade_id']);
        $subjects = Olama_School_Subject::get_by_grade($grade_id, true);
        wp_send_json_success($subjects);
    }

    public function get_scheduled_subjects()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $section_id = intval($_GET['section_id']);
        $day_name = sanitize_text_field($_GET['day_name']);

        $semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

        if (!$semester_id) {
            $active_semester = Olama_School_Academic::get_active_semester();
            $semester_id = $active_semester ? $active_semester->id : 0;
        }

        $subjects = Olama_School_Schedule::get_unique_subjects_for_day($section_id, $day_name, $semester_id);
        wp_send_json_success($subjects);
    }

    public function delete_plan()
    {
        check_ajax_referer('olama_save_plan', 'nonce');
        $plan_id = intval($_POST['plan_id']);
        if ($plan_id > 0) {
            Olama_School_Plan::delete_plan($plan_id);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    // ==========================================
    // Timeline Handlers
    // ==========================================

    public function get_timeline_data()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $semester_id = intval($_REQUEST['semester_id']);
        $grade_id = intval($_REQUEST['grade_id']);
        $subject_id = intval($_REQUEST['subject_id']);

        if (!$semester_id || !$grade_id || !$subject_id) {
            wp_send_json_error(__('Missing parameters.', 'olama-school'));
        }

        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        $timeline_data = array();

        foreach ($units as $unit) {
            $lessons = Olama_School_Lesson::get_lessons($unit->id);
            $timeline_data[] = array(
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_name' => $unit->unit_name,
                'start_date' => $unit->start_date,
                'end_date' => $unit->end_date,
                'formatted_start_date' => Olama_School_Helpers::format_date($unit->start_date),
                'formatted_end_date' => Olama_School_Helpers::format_date($unit->end_date),
                'lessons' => array_map(function ($lesson) {
                    return array(
                        'id' => $lesson->id,
                        'lesson_number' => $lesson->lesson_number,
                        'lesson_title' => $lesson->lesson_title,
                        'periods' => $lesson->periods,
                        'start_date' => $lesson->start_date,
                        'end_date' => $lesson->end_date,
                        'formatted_start_date' => Olama_School_Helpers::format_date($lesson->start_date),
                        'formatted_end_date' => Olama_School_Helpers::format_date($lesson->end_date),
                    );
                }, $lessons)
            );
        }

        wp_send_json_success($timeline_data);
    }

    public function save_timeline_dates()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $data_json = isset($_POST['timeline_data']) ? $_POST['timeline_data'] : '';
        $data = json_decode(stripslashes($data_json), true);

        if (!$data || !is_array($data)) {
            wp_send_json_error(__('Invalid data format.', 'olama-school'));
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'olama_curriculum_units';
        $lessons_table = $wpdb->prefix . 'olama_curriculum_lessons';

        foreach ($data as $unit) {
            $wpdb->update(
                $units_table,
                array(
                    'start_date' => !empty($unit['start_date']) ? Olama_School_Helpers::sanitize_date($unit['start_date']) : null,
                    'end_date' => !empty($unit['end_date']) ? Olama_School_Helpers::sanitize_date($unit['end_date']) : null
                ),
                array('id' => intval($unit['id']))
            );

            if (!empty($unit['lessons']) && is_array($unit['lessons'])) {
                foreach ($unit['lessons'] as $lesson) {
                    $wpdb->update(
                        $lessons_table,
                        array(
                            'start_date' => !empty($lesson['start_date']) ? Olama_School_Helpers::sanitize_date($lesson['start_date']) : null,
                            'end_date' => !empty($lesson['end_date']) ? Olama_School_Helpers::sanitize_date($lesson['end_date']) : null,
                            'periods' => isset($lesson['periods']) ? intval($lesson['periods']) : 1
                        ),
                        array('id' => intval($lesson['id']))
                    );
                }
            }
        }

        wp_send_json_success(__('Timeline dates saved successfully.', 'olama-school'));
    }

    public function bulk_approve_plans()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : '';

        if (!$section_id || !$week_start) {
            wp_send_json_error(__('Invalid parameters', 'olama-school'));
        }

        global $wpdb;
        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}olama_plans SET status = 'published' 
             WHERE section_id = %d AND plan_date >= %s AND plan_date <= %s",
            $section_id,
            $week_start,
            $week_end
        ));

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Database error', 'olama-school'));
        }
    }

    // ==========================================
    // Teacher Assignment Handlers
    // ==========================================

    public function get_sections_by_grade()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $grade_id = intval($_POST['grade_id']);
        if (!$grade_id) {
            wp_send_json_error(__('Invalid Grade ID', 'olama-school'));
        }
        $active_year = Olama_School_Academic::get_active_year();
        $sections = Olama_School_Section::get_by_grade($grade_id, $active_year ? $active_year->id : 0);
        wp_send_json_success($sections);
    }

    public function get_teacher_summary()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $teacher_id = intval($_POST['teacher_id']);
        if (!$teacher_id) {
            wp_send_json_error(__('Invalid Teacher ID', 'olama-school'));
        }
        $active_year = Olama_School_Academic::get_active_year();
        $assignments = Olama_School_Teacher::get_all_assignments($teacher_id, $active_year ? $active_year->id : 0);
        wp_send_json_success($assignments);
    }

    public function get_teacher_assignments()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $teacher_id = intval($_POST['teacher_id']);
        $section_id = intval($_POST['section_id']);
        $grade_id = intval($_POST['grade_id']);

        if (!$teacher_id || !$section_id || !$grade_id) {
            wp_send_json_error(__('Invalid parameters', 'olama-school'));
        }

        $active_year = Olama_School_Academic::get_active_year();
        $assigned_subjects = Olama_School_Teacher::get_assigned_subjects($teacher_id, $section_id, $active_year ? $active_year->id : 0);
        $all_grade_subjects = Olama_School_Subject::get_by_grade($grade_id, true);

        wp_send_json_success(array(
            'assigned' => $assigned_subjects,
            'all' => $all_grade_subjects
        ));
    }

    public function toggle_teacher_assignment()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $teacher_id = intval($_POST['teacher_id']);
        $section_id = intval($_POST['section_id']);
        $subject_id = intval($_POST['subject_id']);
        $grade_id = intval($_POST['grade_id']);

        if (!$teacher_id || !$section_id || !$subject_id || !$grade_id) {
            wp_send_json_error(__('Invalid parameters', 'olama-school'));
        }

        $active_year = Olama_School_Academic::get_active_year();
        $result = Olama_School_Teacher::toggle_assignment($teacher_id, $section_id, $subject_id, $grade_id, $active_year ? $active_year->id : 0);

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Database error', 'olama-school'));
        }
    }

    // ==========================================
    // Bulk Upload Handler
    // ==========================================

    public function bulk_upload_curriculum()
    {
        // Clean any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Start fresh output buffering
        ob_start();

        check_ajax_referer('olama_bulk_upload_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('olama_manage_curriculum')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to import curriculum data.', 'olama-school')
            ));
        }

        // Validate parameters
        $semester_id = isset($_POST['semester_id']) ? intval($_POST['semester_id']) : 0;
        $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;

        if (!$semester_id || !$grade_id) {
            wp_send_json_error(array(
                'message' => __('Please select both semester and grade.', 'olama-school')
            ));
        }

        // Validate file upload
        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error(array(
                'message' => __('Please upload a valid file.', 'olama-school')
            ));
        }

        // Process the bulk import
        $result = Olama_School_Importer::import_bulk_curriculum(
            $semester_id,
            $grade_id,
            $_FILES['file']
        );

        // Clean all output buffers before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    // ==========================================
    // Clear Curriculum Handler
    // ==========================================

    public function clear_curriculum()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('olama_manage_curriculum')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to delete curriculum data.', 'olama-school')
            ));
        }

        // Validate parameters
        $semester_id = isset($_REQUEST['semester_id']) ? intval($_REQUEST['semester_id']) : 0;
        $grade_id = isset($_REQUEST['grade_id']) ? intval($_REQUEST['grade_id']) : 0;
        $subject_id = isset($_REQUEST['subject_id']) ? intval($_REQUEST['subject_id']) : 0;

        if (!$semester_id || !$grade_id || !$subject_id) {
            wp_send_json_error(array(
                'message' => __('Please select semester, grade, and subject.', 'olama-school')
            ));
        }

        global $wpdb;

        // Get all unit IDs for this subject
        $unit_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_curriculum_units 
             WHERE semester_id = %d AND grade_id = %d AND subject_id = %d",
            $semester_id,
            $grade_id,
            $subject_id
        ));

        if (empty($unit_ids)) {
            wp_send_json_success(array(
                'message' => __('No curriculum data found to delete.', 'olama-school')
            ));
            return;
        }

        // Delete all lessons for these units
        $placeholders = implode(',', array_fill(0, count($unit_ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_lessons 
             WHERE unit_id IN ($placeholders)",
            $unit_ids
        ));

        // Delete all questions for lessons in these units (if applicable)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_questions 
             WHERE lesson_id IN (
                 SELECT id FROM {$wpdb->prefix}olama_curriculum_lessons 
                 WHERE unit_id IN ($placeholders)
             )",
            $unit_ids
        ));

        // Delete all units
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_units 
             WHERE semester_id = %d AND grade_id = %d AND subject_id = %d",
            $semester_id,
            $grade_id,
            $subject_id
        ));

        // Log activity
        if (class_exists('Olama_School_Logger')) {
            $subject = $wpdb->get_var($wpdb->prepare(
                "SELECT subject_name FROM {$wpdb->prefix}olama_subjects WHERE id = %d",
                $subject_id
            ));
            Olama_School_Logger::log('curriculum_cleared', sprintf(
                'Curriculum cleared for subject: %s (Semester: %d, Grade: %d)',
                $subject,
                $semester_id,
                $grade_id
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully deleted all curriculum data. %d unit(s) and their lessons were removed.', 'olama-school'), count($unit_ids))
        ));
    }

    // ==========================================
    // Clear Grade Curriculum Handler
    // ==========================================

    public function clear_grade_curriculum()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('olama_manage_curriculum')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to delete curriculum data.', 'olama-school')
            ));
        }

        // Validate parameters (subject not required for grade-level clear)
        $semester_id = isset($_REQUEST['semester_id']) ? intval($_REQUEST['semester_id']) : 0;
        $grade_id = isset($_REQUEST['grade_id']) ? intval($_REQUEST['grade_id']) : 0;

        if (!$semester_id || !$grade_id) {
            wp_send_json_error(array(
                'message' => __('Please select semester and grade.', 'olama-school')
            ));
        }

        global $wpdb;

        // Get all unit IDs for this grade and semester (across ALL subjects)
        $unit_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_curriculum_units 
             WHERE semester_id = %d AND grade_id = %d",
            $semester_id,
            $grade_id
        ));

        if (empty($unit_ids)) {
            wp_send_json_success(array(
                'message' => __('No curriculum data found to delete.', 'olama-school')
            ));
            return;
        }

        // Delete all lessons for these units
        $placeholders = implode(',', array_fill(0, count($unit_ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_lessons 
             WHERE unit_id IN ($placeholders)",
            $unit_ids
        ));

        // Delete all questions for lessons in these units
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_questions 
             WHERE lesson_id IN (
                 SELECT id FROM {$wpdb->prefix}olama_curriculum_lessons 
                 WHERE unit_id IN ($placeholders)
             )",
            $unit_ids
        ));

        // Delete all units
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_units 
             WHERE semester_id = %d AND grade_id = %d",
            $semester_id,
            $grade_id
        ));

        // Log activity
        if (class_exists('Olama_School_Logger')) {
            $grade = $wpdb->get_var($wpdb->prepare(
                "SELECT grade_name FROM {$wpdb->prefix}olama_grades WHERE id = %d",
                $grade_id
            ));
            Olama_School_Logger::log('grade_curriculum_cleared', sprintf(
                'Grade curriculum cleared for: %s (Semester: %d)',
                $grade,
                $semester_id
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Grade curriculum cleared successfully! %d unit(s) across all subjects were removed.', 'olama-school'), count($unit_ids))
        ));
    }

    /**
     * Force Clear ALL Curriculum Data (Global)
     */
    public function force_clear_all_curriculum()
    {
        // Clean any existing output buffers to prevent them from breaking the JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }

        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Only administrators can perform a global wipe.', 'olama-school')));
        }

        global $wpdb;

        // Attempt to increase execution time for large deletions
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        // We use DELETE instead of TRUNCATE for better compatibility with different DB users
        $q_res = $wpdb->query("DELETE FROM {$wpdb->prefix}olama_curriculum_questions");
        $l_res = $wpdb->query("DELETE FROM {$wpdb->prefix}olama_curriculum_lessons");
        $u_res = $wpdb->query("DELETE FROM {$wpdb->prefix}olama_curriculum_units");

        if ($wpdb->last_error) {
            wp_send_json_error(array(
                'message' => __('Database error during global wipe:', 'olama-school') . ' ' . $wpdb->last_error
            ));
        }

        // Log the activity
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('curriculum_global_wipe', 'GLOBAL CURRICULUM WIPE: All units, lessons, and questions were deleted.');
        }

        wp_send_json_success(array('message' => __('Global curriculum wipe completed successfully!', 'olama-school')));
    }
}