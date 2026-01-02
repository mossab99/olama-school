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

        add_action('wp_ajax_olama_get_scheduled_subjects', array($this, 'get_scheduled_subjects'));
        add_action('wp_ajax_olama_get_subjects_by_grade', array($this, 'get_subjects_by_grade'));
        add_action('wp_ajax_olama_delete_plan', array($this, 'delete_plan'));

        // Timeline AJAX Handlers
        add_action('wp_ajax_olama_get_timeline_data', array($this, 'get_timeline_data'));
        add_action('wp_ajax_olama_save_timeline_dates', array($this, 'save_timeline_dates'));
        add_action('wp_ajax_olama_bulk_approve_plans', array($this, 'bulk_approve_plans'));

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
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $subject_id = intval($_GET['subject_id']);
        $grade_id = intval($_GET['grade_id']);
        $semester_id = intval($_GET['semester_id']);
        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        wp_send_json_success($units);
    }

    public function delete_curriculum_unit()
    {
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
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $lessons = Olama_School_Lesson::get_lessons(intval($_GET['unit_id']));
        wp_send_json_success($lessons);
    }

    public function delete_curriculum_lesson()
    {
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
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $questions = Olama_School_Question_Bank::get_questions(intval($_GET['lesson_id']));

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
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $id = intval($_POST['id']);
        if ($id > 0) {
            Olama_School_Question_Bank::delete_question($id);
            wp_send_json_success();
        }
        wp_send_json_error('Invalid ID');
    }

    // ==========================================
    // Subject and Schedule Handlers
    // ==========================================

    public function get_subjects_by_grade()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $grade_id = intval($_GET['grade_id']);
        $subjects = Olama_School_Subject::get_by_grade($grade_id);
        wp_send_json_success($subjects);
    }

    public function get_scheduled_subjects()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $section_id = intval($_GET['section_id']);
        $day_name = sanitize_text_field($_GET['day_name']);

        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : [];
        $semester_id = $semesters[0]->id ?? 0;

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
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $semester_id = intval($_GET['semester_id']);
        $grade_id = intval($_GET['grade_id']);
        $subject_id = intval($_GET['subject_id']);

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
                'lessons' => array_map(function ($lesson) {
                    return array(
                        'id' => $lesson->id,
                        'lesson_number' => $lesson->lesson_number,
                        'lesson_title' => $lesson->lesson_title,
                        'periods' => $lesson->periods,
                        'start_date' => $lesson->start_date,
                        'end_date' => $lesson->end_date
                    );
                }, $lessons)
            );
        }

        wp_send_json_success($timeline_data);
    }

    public function save_timeline_dates()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $data = json_decode(stripslashes($_POST['timeline_data']), true);

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
                    'start_date' => !empty($unit['start_date']) ? $unit['start_date'] : null,
                    'end_date' => !empty($unit['end_date']) ? $unit['end_date'] : null
                ),
                array('id' => intval($unit['id']))
            );

            if (!empty($unit['lessons']) && is_array($unit['lessons'])) {
                foreach ($unit['lessons'] as $lesson) {
                    $wpdb->update(
                        $lessons_table,
                        array(
                            'start_date' => !empty($lesson['start_date']) ? $lesson['start_date'] : null,
                            'end_date' => !empty($lesson['end_date']) ? $lesson['end_date'] : null,
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
            wp_send_json_error('Unauthorized');
        }

        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : '';

        if (!$section_id || !$week_start) {
            wp_send_json_error('Invalid parameters');
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
            wp_send_json_error('Database error');
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
            wp_send_json_error('Invalid Grade ID');
        }
        $sections = Olama_School_Section::get_by_grade($grade_id);
        wp_send_json_success($sections);
    }

    public function get_teacher_summary()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $teacher_id = intval($_POST['teacher_id']);
        if (!$teacher_id) {
            wp_send_json_error('Invalid Teacher ID');
        }
        $assignments = Olama_School_Teacher::get_all_assignments($teacher_id);
        wp_send_json_success($assignments);
    }

    public function get_teacher_assignments()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $teacher_id = intval($_POST['teacher_id']);
        $section_id = intval($_POST['section_id']);
        $grade_id = intval($_POST['grade_id']);

        if (!$teacher_id || !$section_id || !$grade_id) {
            wp_send_json_error('Invalid parameters');
        }

        $assigned_subjects = Olama_School_Teacher::get_assigned_subjects($teacher_id, $section_id);
        $all_grade_subjects = Olama_School_Subject::get_by_grade($grade_id);

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
            wp_send_json_error('Invalid parameters');
        }

        $result = Olama_School_Teacher::toggle_assignment($teacher_id, $section_id, $subject_id, $grade_id);

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Database error');
        }
    }
}
