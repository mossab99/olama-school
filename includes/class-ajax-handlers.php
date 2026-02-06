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

        // Plan Review AJAX Handler
        add_action('wp_ajax_olama_handle_plan_approval', array($this, 'handle_plan_approval'));

        // Exam Attachment AJAX Handlers
        add_action('wp_ajax_olama_upload_exam_file', array($this, 'upload_exam_file'));
        add_action('wp_ajax_olama_download_exam_file', array($this, 'download_exam_file'));
        add_action('wp_ajax_olama_get_exam_attachment', array($this, 'get_exam_attachment'));
        add_action('wp_ajax_olama_delete_exam_attachment', array($this, 'delete_exam_attachment'));
        add_action('wp_ajax_olama_save_exam_attachment_comment', array($this, 'save_exam_attachment_comment'));
        add_action('wp_ajax_olama_download_all_exams_zip', array($this, 'download_all_exams_zip'));
        add_action('wp_ajax_olama_get_semester_exams', array($this, 'get_semester_exams'));
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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (
            !Olama_School_Permissions::can('olama_manage_curriculum_list') &&
            !Olama_School_Permissions::can('olama_manage_curriculum_timeline') &&
            !Olama_School_Permissions::can('olama_view_curriculum_timeline') &&
            !Olama_School_Permissions::can('olama_manage_academic_assignment')
        ) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline') && !Olama_School_Permissions::can('olama_view_curriculum_timeline')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

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
        $log_file = dirname(__FILE__) . '/olama_save_debug.log';
        $log_entry = "--- Save Request " . current_time('mysql') . " ---\n";

        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!check_ajax_referer('olama_admin_nonce', 'nonce', false)) {
            file_put_contents($log_file, $log_entry . "Nonce Check Failed\n", FILE_APPEND);
            wp_send_json_error(__('Nonce verification failed.', 'olama-school'));
        }

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $data_json = isset($_POST['timeline_data']) ? $_POST['timeline_data'] : '';
        $log_entry .= "Raw Data (start): " . substr($data_json, 0, 1000) . "\n";
        $log_entry .= "Raw Data Length: " . strlen($data_json) . "\n";

        $data = json_decode(stripslashes($data_json), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log_entry .= "JSON Decode Error: " . json_last_error_msg() . "\n";
            file_put_contents($log_file, $log_entry, FILE_APPEND);
            wp_send_json_error(__('Invalid data format.', 'olama-school'));
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'olama_curriculum_units';
        $lessons_table = $wpdb->prefix . 'olama_curriculum_lessons';

        $total_items = 0;
        $success_items = 0;
        $errors = array();

        $log_details = "";
        foreach ($data as $unit) {
            if (!isset($unit['id']))
                continue;

            $unit_id = intval($unit['id']);
            $raw_start = $unit['start_date'] ?? 'missing';
            $raw_end = $unit['end_date'] ?? 'missing';

            $unit_start = !empty($unit['start_date']) && $unit['start_date'] !== 'undefined' ? Olama_School_Helpers::sanitize_date($unit['start_date']) : null;
            $unit_end = !empty($unit['end_date']) && $unit['end_date'] !== 'undefined' ? Olama_School_Helpers::sanitize_date($unit['end_date']) : null;

            $res = $wpdb->update(
                $units_table,
                array('start_date' => $unit_start, 'end_date' => $unit_end),
                array('id' => $unit_id)
            );

            $log_details .= "U$unit_id: RAW($raw_start, $raw_end) -> DB($unit_start, $unit_end) | Result: $res\n";
            $success_items++;
            $total_items++;

            if (!empty($unit['lessons']) && is_array($unit['lessons'])) {
                foreach ($unit['lessons'] as $lesson) {
                    if (!isset($lesson['id']))
                        continue;

                    $lesson_id = intval($lesson['id']);
                    $l_raw_start = $lesson['start_date'] ?? 'missing';
                    $l_raw_end = $lesson['end_date'] ?? 'missing';

                    $lesson_start = !empty($lesson['start_date']) && $lesson['start_date'] !== 'undefined' ? Olama_School_Helpers::sanitize_date($lesson['start_date']) : null;
                    $lesson_end = !empty($lesson['end_date']) && $lesson['end_date'] !== 'undefined' ? Olama_School_Helpers::sanitize_date($lesson['end_date']) : null;
                    $periods = isset($lesson['periods']) ? intval($lesson['periods']) : 1;

                    $res = $wpdb->update(
                        $lessons_table,
                        array('start_date' => $lesson_start, 'end_date' => $lesson_end, 'periods' => $periods),
                        array('id' => $lesson_id)
                    );

                    $log_details .= "  L$lesson_id: RAW($l_raw_start, $l_raw_end) -> DB($lesson_start, $lesson_end) | Result: $res\n";
                    $success_items++;
                    $total_items++;
                }
            }
        }

        $log_entry .= $log_details;
        $log_entry .= "Summary: Processed $total_items items. Success recorded for $success_items updates.\n";
        if (!empty($errors)) {
            $log_entry .= "First Error: " . $errors[0] . "\n";
        }
        file_put_contents($log_file, $log_entry, FILE_APPEND);

        if ($success_items === 0 && $total_items > 0) {
            wp_send_json_error(array(
                'message' => __('No records were saved.', 'olama-school'),
                'details' => $errors
            ));
        }

        wp_send_json_success(sprintf(__('Successfully saved %d items.', 'olama-school'), $success_items));
    }

    public function bulk_approve_plans()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_approve_plans')) {
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
            "UPDATE {$wpdb->prefix}olama_plans SET status = 'approved' 
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
        $academic_year_id = isset($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : 0;

        if (!$grade_id) {
            wp_send_json_error(__('Invalid Grade ID', 'olama-school'));
        }

        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $sections = Olama_School_Section::get_by_grade($grade_id, $academic_year_id);
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

        // Delete units themselves
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}olama_curriculum_units 
             WHERE id IN ($placeholders)",
            $unit_ids
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
        while (ob_get_level()) {
            ob_end_clean();
        }
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'olama-school')));
        }

        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $year_id = isset($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : 0;
        $semester_id = isset($_POST['semester_id']) ? intval($_POST['semester_id']) : 0;
        $grade_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;

        if (!$year_id) {
            wp_send_json_error(array('message' => Olama_School_Helpers::translate('Missing parameters.')));
        }

        $settings = get_option('olama_school_settings', array());
        $stored_password = $settings['deletion_password'] ?? '';

        if (empty($stored_password)) {
            wp_send_json_error(array('message' => Olama_School_Helpers::translate('Please set a deletion password in General Settings first.')));
        }

        if ($password !== $stored_password) {
            wp_send_json_error(array('message' => Olama_School_Helpers::translate('Invalid deletion password.')));
        }

        global $wpdb;

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        // Delete cascaded: Questions -> Lessons -> Units for the selected year
        $wpdb->query($wpdb->prepare(
            "DELETE q FROM {$wpdb->prefix}olama_curriculum_questions q
             JOIN {$wpdb->prefix}olama_curriculum_lessons l ON q.lesson_id = l.id
             JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
             JOIN {$wpdb->prefix}olama_semesters s ON u.semester_id = s.id
             WHERE s.academic_year_id = %d",
            $year_id
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE l FROM {$wpdb->prefix}olama_curriculum_lessons l
             JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
             JOIN {$wpdb->prefix}olama_semesters s ON u.semester_id = s.id
             WHERE s.academic_year_id = %d",
            $year_id
        ));

        $wpdb->query($wpdb->prepare(
            "DELETE u FROM {$wpdb->prefix}olama_curriculum_units u
             JOIN {$wpdb->prefix}olama_semesters s ON u.semester_id = s.id
             WHERE s.academic_year_id = %d",
            $year_id
        ));

        if ($wpdb->last_error) {
            wp_send_json_error(array(
                'message' => __('Database error during global wipe:', 'olama-school') . ' ' . $wpdb->last_error
            ));
        }

        $year_name = $wpdb->get_var($wpdb->prepare("SELECT year_name FROM {$wpdb->prefix}olama_academic_years WHERE id = %d", $year_id));
        $semester_name = $semester_id ? $wpdb->get_var($wpdb->prepare("SELECT semester_name FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id)) : Olama_School_Helpers::translate('All');
        $grade_name = $grade_id ? $wpdb->get_var($wpdb->prepare("SELECT grade_name FROM {$wpdb->prefix}olama_grades WHERE id = %d", $grade_id)) : Olama_School_Helpers::translate('All');

        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('curriculum_year_wipe', sprintf('YEAR CURRICULUM WIPE: All units, lessons, and questions were deleted for year: %s', $year_name));
        }

        $msg = Olama_School_Helpers::translate('Curriculum wipe for Year: {year}, Semester: {semester}, Grade: {grade} completed successfully!');
        $msg = str_replace(['{year}', '{semester}', '{grade}'], [$year_name, $semester_name, $grade_name], $msg);

        wp_send_json_success(array('message' => $msg));
    }

    /**
     * AJAX handler for plan approval/rejection
     */
    public function handle_plan_approval()
    {
        try {
            while (ob_get_level()) {
                ob_end_clean();
            }

            if (!wp_verify_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '', 'olama_admin_nonce')) {
                wp_send_json_error(__('Session expired. Please refresh the page.', 'olama-school'));
            }

            if (!Olama_School_Permissions::can('olama_approve_plans') && !current_user_can('olama_manage_plans')) {
                wp_send_json_error(__('Unauthorized access.', 'olama-school'));
            }

            global $wpdb;
            $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
            $feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';

            if (!$plan_id) {
                wp_send_json_error(__('Invalid plan ID.', 'olama-school'));
            }

            $allowed_statuses = array('draft', 'approved', 'submitted', 'needs_edit', 'edited');
            if (!in_array($status, $allowed_statuses)) {
                wp_send_json_error(__('Invalid status.', 'olama-school'));
            }

            $data = array('status' => $status);

            if (!empty($feedback)) {
                $current_plan = $wpdb->get_row($wpdb->prepare("SELECT supervisor_feedback FROM {$wpdb->prefix}olama_plans WHERE id = %d", $plan_id));
                $existing_feedback = $current_plan ? $current_plan->supervisor_feedback : '';
                $new_feedback = "[" . date('Y-m-d H:i') . "] " . $feedback;
                $data['supervisor_feedback'] = $existing_feedback ? $existing_feedback . "\n" . $new_feedback : $new_feedback;
            }

            // Update plan with explicit LIMIT 1 for safety to prevent unintended bulk changes
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}olama_plans 
                 SET status = %s, supervisor_feedback = %s, updated_at = %s
                 WHERE id = %d LIMIT 1",
                $status,
                $data['supervisor_feedback'] ?? null,
                current_time('mysql'),
                $plan_id
            ));

            if ($updated !== false) {
                // Log activity for audit trail
                if (class_exists('Olama_School_Logger')) {
                    Olama_School_Logger::log('plan_status_update', sprintf(
                        'Plan ID %d status updated to %s by user %d',
                        $plan_id,
                        $status,
                        get_current_user_id()
                    ));
                }
                wp_send_json_success(__('Plan status updated successfully.', 'olama-school'));
            } else {
                wp_send_json_error(__('Failed to update plan status.', 'olama-school'));
            }
        } catch (\Throwable $th) {
            error_log('Olama Review Crash: ' . $th->getMessage());
            wp_send_json_error('Server Error: ' . $th->getMessage());
        }
    }

    // ==========================================
    // Exam Attachment Handlers
    // ==========================================

    public function upload_exam_file()
    {
        if (!check_ajax_referer('olama_save_exam', 'nonce', false)) {
            wp_send_json_error(__('Session expired or security check failed. Please refresh.', 'olama-school'));
        }

        if (!isset($_POST['exam_id']) || empty($_FILES['exam_file'])) {
            wp_send_json_error(__('Missing parameters', 'olama-school'));
        }

        $exam_id = intval($_POST['exam_id']);
        $result = Olama_School_Exam_Attachment::handle_upload($exam_id, $_FILES['exam_file']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('File uploaded successfully', 'olama-school'));
    }

    public function delete_exam_attachment()
    {
        if (!check_ajax_referer('olama_save_exam', 'nonce', false)) {
            wp_send_json_error(__('Session expired or security check failed. Please refresh.', 'olama-school'));
        }

        if (empty($_POST['exam_id'])) {
            wp_send_json_error(__('Missing exam ID', 'olama-school'));
        }

        $exam_id = intval($_POST['exam_id']);
        $result = Olama_School_Exam_Attachment::delete_attachment($exam_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('File deleted successfully', 'olama-school'));
    }

    public function download_exam_file()
    {
        if (!isset($_GET['exam_id']) || !isset($_GET['_wpnonce'])) {
            wp_die(__('Missing parameters', 'olama-school'));
        }

        $exam_id = intval($_GET['exam_id']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'olama_download_file_' . $exam_id)) {
            wp_die(__('Security check failed', 'olama-school'));
        }

        Olama_School_Exam_Attachment::stream_file($exam_id);
    }

    public function get_exam_attachment()
    {
        if (!check_ajax_referer('olama_save_exam', 'nonce', false)) {
            wp_send_json_error(__('Session expired or security check failed. Please refresh.', 'olama-school'));
        }
        $exam_id = intval($_POST['exam_id']);
        $info = Olama_School_Exam_Attachment::get_attachment_info($exam_id);

        if ($info) {
            $info->download_url = add_query_arg(
                array(
                    'action' => 'olama_download_exam_file',
                    'exam_id' => $exam_id,
                    '_wpnonce' => wp_create_nonce('olama_download_file_' . $exam_id)
                ),
                admin_url('admin-ajax.php')
            );
        }

        wp_send_json_success($info);
    }

    public function save_exam_attachment_comment()
    {
        check_ajax_referer('olama_save_exam', 'nonce');

        if (!Olama_School_Permissions::can('manage_options') && !current_user_can('editor')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $exam_id = intval($_POST['exam_id']);
        $status = sanitize_text_field($_POST['file_status']);
        $comment = sanitize_textarea_field($_POST['supervisor_comments']);

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}olama_exam_attachments",
            array('file_status' => $status, 'supervisor_comments' => $comment),
            array('exam_id' => $exam_id)
        );

        wp_send_json_success(__('Comments saved successfully', 'olama-school'));
    }

    public function download_all_exams_zip()
    {
        if (!Olama_School_Permissions::can('manage_options')) {
            wp_die(__('Unauthorized', 'olama-school'));
        }

        $filters = array(
            'academic_year_id' => isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0,
            'semester_id' => isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0,
            'semester_exam_id' => isset($_GET['semester_exam_id']) ? intval($_GET['semester_exam_id']) : 0,
            'grade_id' => isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0,
        );

        Olama_School_Exam_Attachment::download_all_approved_zip($filters);
    }

    public function get_semester_exams()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $semester_id = intval($_POST['semester_id']);
        $exams = Olama_School_Academic::get_semester_exams($semester_id);
        wp_send_json_success($exams);
    }
}