<?php
/**
 * Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->maybe_update_db();
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_schedule_save'));
        add_action('admin_init', array($this, 'handle_plan_load_save'));
        add_action('admin_init', array($this, 'handle_office_hours_save'));
        add_action('admin_init', array($this, 'handle_exam_save'));
        add_action('admin_init', array($this, 'handle_stationary_save'));
        add_action('admin_init', array($this, 'handle_academic_calendar_actions'));
        add_action('admin_init', array($this, 'handle_subject_actions'));
        add_action('admin_init', array($this, 'handle_backup_restore_actions'));
        add_action('admin_init', array($this, 'handle_teacher_settings_save'));
        add_action('admin_init', array($this, 'handle_kg_curriculum_actions'));
        add_action('admin_init', array($this, 'handle_kg_evaluation_save'));
        add_action('admin_init', array($this, 'handle_kg_report_print'));
        add_action('admin_init', array($this, 'handle_family_actions'));
        add_action('admin_init', array($this, 'handle_lesson_planner_actions'));
        add_action('admin_init', array($this, 'handle_attendance_save'));
        add_action('admin_init', array($this, 'handle_cleaning_save'));
        add_action('admin_init', array($this, 'handle_cleaning_config_save'));
        add_action('wp_ajax_olama_save_attendance', array($this, 'ajax_save_attendance'));
        add_action('wp_ajax_olama_mark_all_present', array($this, 'ajax_mark_all_present'));
        add_action('wp_ajax_olama_kg_autosave', array($this, 'ajax_kg_autosave'));
        add_action('wp_ajax_olama_save_exam', array($this, 'ajax_save_exam'));
        add_action('wp_ajax_olama_get_semesters', array($this, 'ajax_get_semesters'));
        add_action('wp_ajax_olama_get_subjects', array($this, 'ajax_get_subjects'));
        add_action('wp_ajax_olama_bulk_add_exam_subjects', array($this, 'ajax_bulk_add_exam_subjects'));
        add_action('wp_ajax_olama_get_student_history', array($this, 'ajax_get_enrollment_history'));
        add_action('wp_ajax_olama_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_olama_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_olama_get_family_students', array($this, 'ajax_get_family_students'));
        add_action('wp_ajax_olama_get_units', array($this, 'ajax_get_units'));
        add_action('wp_ajax_olama_get_lessons', array($this, 'ajax_get_lessons'));
        add_action('wp_ajax_olama_get_ev_progress_students', array($this, 'ajax_get_ev_progress_students'));
        add_action('wp_ajax_olama_get_student_evaluation', array($this, 'ajax_get_student_evaluation'));
        add_action('wp_ajax_olama_approve_evaluation', array($this, 'ajax_approve_evaluation'));
        add_action('wp_ajax_olama_save_supervisor_comments', array($this, 'ajax_save_supervisor_comments'));
        add_action('wp_ajax_olama_bulk_approve_evaluations', array($this, 'ajax_bulk_approve_evaluations'));
        add_action('wp_ajax_olama_upload_backup_chunk', array($this, 'ajax_upload_backup_chunk'));
        add_action('wp_ajax_olama_initiate_restore', array($this, 'ajax_restore_database'));
        add_action('admin_init', array($this, 'restrict_teacher_access'));
        add_action('admin_post_olama_save_office_hours', array($this, 'handle_office_hours_save'));
        add_action('admin_bar_menu', array($this, 'clean_teacher_admin_bar'), 999);

        // Whitelabel Footer
        add_filter('admin_footer_text', array($this, 'whitelabel_footer'));
        add_filter('update_footer', array($this, 'whitelabel_footer'), 11);
    }

    /**
     * Whitelabel footer text
     */
    public function whitelabel_footer($text)
    {
        if (isset($_GET['page']) && strpos($_GET['page'], 'olama-school') !== false) {
            return '';
        }
        return $text;
    }

    /**
     * Check if DB needs update
     */
    private function maybe_update_db()
    {
        static $already_checked = false;
        if ($already_checked) {
            return;
        }
        $already_checked = true;

        $installed_ver = get_option('olama_school_db_version');
        if ($installed_ver !== OLAMA_SCHOOL_VERSION) {
            $olama_db = new Olama_School_DB();
            $olama_db->create_tables();
            update_option('olama_school_db_version', OLAMA_SCHOOL_VERSION);
        }
    }

    /**
     * Handle CSV Export
     */
    public function handle_export()
    {
        if (isset($_POST['olama_export']) && $_POST['olama_export'] === 'true') {
            Olama_School_Exporter::export_plans_csv();
        }

        // Handle Curriculum Export
        if (isset($_POST['olama_export_curriculum']) && $_POST['olama_export_curriculum'] === 'true') {
            Olama_School_Exporter::export_curriculum_csv(
                $_POST['semester_id'] ?? 0,
                $_POST['grade_id'] ?? 0,
                $_POST['subject_id'] ?? 0
            );
        }

        // Handle Subjects Export
        if (isset($_POST['olama_export_subjects']) && $_POST['olama_export_subjects'] === 'true') {
            Olama_School_Exporter::export_subjects_csv();
        }

        // Handle Grade/Section Export
        if (isset($_POST['olama_export_grades']) && $_POST['olama_export_grades'] === 'true') {
            Olama_School_Exporter::export_grades_sections_csv();
        }

        // Handle Students Export
        if (isset($_POST['olama_export_students']) && $_POST['olama_export_students'] === 'true') {
            Olama_School_Exporter::export_students_enrollment_csv();
        }

        if (isset($_FILES['olama_import_file'])) {
            $type = isset($_POST['olama_import_type']) ? $_POST['olama_import_type'] : '';
            error_log('Olama Import: File detected. Type: ' . $type);
            error_log('Olama Import: POST data: ' . print_r($_POST, true));

            if ($type === 'students') {
                Olama_School_Importer::import_students_csv();
            } elseif ($type === 'curriculum') {
                Olama_School_Importer::import_curriculum_csv(
                    $_POST['semester_id'] ?? 0,
                    $_POST['grade_id'] ?? 0,
                    $_POST['subject_id'] ?? 0
                );
            } elseif ($type === 'subjects') {
                Olama_School_Importer::import_subjects_csv();
            } elseif ($type === 'grades') {
                Olama_School_Importer::import_grades_sections_csv();
            } elseif ($type === 'families' || isset($_POST['olama_import_families'])) {
                Olama_School_Importer::import_families_csv();
            } elseif ($type === 'students_enrollment' || isset($_POST['olama_import_students_enrollment'])) {
                Olama_School_Importer::import_students_enrollment_csv();
            } elseif ($type === 'plans' || !isset($_POST['olama_import_type'])) {
                // Default to plans for legacy support if not otherwise handled
                Olama_School_Importer::import_plans_csv();
            }
        }
        // Handle Schedule Export
        if (isset($_POST['olama_export_schedule']) && check_admin_referer('olama_export_schedule')) {
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $grade_id = intval($_POST['grade_id']);
            $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'normal');

            if ($section_id && $semester_id) {
                $schedule = Olama_School_Schedule::get_schedule($section_id, $semester_id, $schedule_type);
                $subjects = Olama_School_Subject::get_by_grade($grade_id);
                $subject_map = array();
                foreach ($subjects as $subj) {
                    $subject_map[$subj->id] = array(
                        'name' => $subj->subject_name,
                        'code' => $subj->subject_code
                    );
                }

                // Generate CSV
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="schedule_export_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, array('Day', 'Period', 'Subject', 'Subject Code'));

                foreach ($schedule as $day => $periods) {
                    foreach ($periods as $period_num => $item) {
                        if ($item && isset($subject_map[$item->subject_id])) {
                            fputcsv($output, array(
                                $day,
                                $period_num,
                                $subject_map[$item->subject_id]['name'],
                                $subject_map[$item->subject_id]['code']
                            ));
                        }
                    }
                }

                fclose($output);
                exit;
            }
        }
    }

    /**
     * Handle Plan Load Settings Save
     */
    public function handle_plan_load_save()
    {
        if (isset($_POST['olama_save_plan_load']) && check_admin_referer('olama_save_plan_load', 'olama_plan_load_nonce')) {
            if (!Olama_School_Permissions::can('olama_manage_plans_load')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $grade_limits = $_POST['grade_limit'] ?? [];
            $subject_limits = $_POST['subject_limit'] ?? [];
            $errors = [];

            $grade_daily_max = $_POST['grade_daily_max'] ?? [];
            // 1. Update Grade Limits and fetch for constraint check
            $current_grade_limits = [];
            foreach ($grade_limits as $grade_id => $limit) {
                $grade_id = intval($grade_id);
                $limit = intval($limit);
                $daily = $grade_daily_max[$grade_id] ?? [];

                // Fetch existing grade to preserve other fields (like periods_count)
                $existing_grade = Olama_School_Grade::get_grade($grade_id);
                if ($existing_grade) {
                    Olama_School_Grade::update_grade($grade_id, array(
                        'grade_name' => $existing_grade->grade_name,
                        'grade_level' => $existing_grade->grade_level,
                        'periods_count' => $existing_grade->periods_count,
                        'max_weekly_plans' => $limit,
                        'max_sun' => intval($daily['sun'] ?? 0),
                        'max_mon' => intval($daily['mon'] ?? 0),
                        'max_tue' => intval($daily['tue'] ?? 0),
                        'max_wed' => intval($daily['wed'] ?? 0),
                        'max_thu' => intval($daily['thu'] ?? 0),
                    ));
                    $current_grade_limits[$grade_id] = $limit;
                }
            }

            // 2. Validate and Update Subject Limits with individual & sum constraints
            $grade_subject_sums = [];
            foreach ($subject_limits as $subject_id => $limit) {
                $subject_id = intval($subject_id);
                $limit = intval($limit);
                $subject = Olama_School_Subject::get_subject($subject_id);

                if ($subject) {
                    $grade_id = $subject->grade_id;
                    $grade_limit = $current_grade_limits[$grade_id] ?? 0;

                    if ($grade_limit > 0) {
                        // Individual check
                        if ($limit > $grade_limit) {
                            $errors[] = sprintf(__('Subject "%s" limit (%d) was reduced to match Grade limit (%d).', 'olama-school'), $subject->subject_name, $limit, $grade_limit);
                            $limit = $grade_limit;
                        }

                        // Sum check (running total)
                        $grade_subject_sums[$grade_id] = ($grade_subject_sums[$grade_id] ?? 0) + $limit;
                        if ($grade_subject_sums[$grade_id] > $grade_limit) {
                            $excess = $grade_subject_sums[$grade_id] - $grade_limit;
                            $adjusted_limit = max(0, $limit - $excess);
                            $errors[] = sprintf(__('Total limits for grade exceeded capacity. Adjusted "%s" to %d.', 'olama-school'), $subject->subject_name, $adjusted_limit);
                            $limit = $adjusted_limit;
                            $grade_subject_sums[$grade_id] = $grade_limit; // Cap the sum
                        }
                    }

                    Olama_School_Subject::update_subject($subject_id, array(
                        'subject_name' => $subject->subject_name,
                        'subject_code' => $subject->subject_code,
                        'grade_id' => $subject->grade_id,
                        'color_code' => $subject->color_code,
                        'max_weekly_plans' => $limit
                    ));
                }
            }

            $redirect_url = admin_url('admin.php?page=olama-school-plans&tab=load');

            if (!empty($errors)) {
                set_transient('olama_plan_load_errors', $errors, 45);
                $redirect_url = add_query_arg('message', 'plan_load_warning', $redirect_url);
            } else {
                $redirect_url = add_query_arg('message', 'plan_load_saved', $redirect_url);
            }

            if (!empty($_POST['manage_grade_id'])) {
                $redirect_url = add_query_arg('manage_grade', intval($_POST['manage_grade_id']), $redirect_url);
            }

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle Schedule Save
     */
    public function handle_schedule_save()
    {
        if (isset($_POST['olama_save_bulk_schedule']) && check_admin_referer('olama_save_bulk_schedule', 'olama_schedule_nonce')) {
            if (!Olama_School_Permissions::can('olama_manage_plans_schedule')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'normal');
            $schedule_data = $_POST['schedule'] ?? [];

            Olama_School_Schedule::save_bulk_schedule($section_id, $semester_id, $schedule_data, $schedule_type);

            // Clear WordPress object cache to ensure fresh data on redirect
            wp_cache_flush();

            $url = add_query_arg(array(
                'grade_id' => intval($_POST['grade_id']),
                'section_id' => $section_id,
                'semester_id' => $semester_id,
                'schedule_type' => $schedule_type,
                'message' => 'schedule_saved'
            ), admin_url('admin.php?page=olama-school-plans&tab=schedule'));

            wp_redirect($url);
            exit;
        }

        // Handle Schedule Cloning
        if (isset($_POST['olama_clone_schedule']) && check_admin_referer('olama_clone_schedule')) {
            if (!Olama_School_Permissions::can('olama_manage_plans_schedule')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $grade_id = intval($_POST['grade_id']);
            $from_type = sanitize_text_field($_POST['from_type']);
            $to_type = sanitize_text_field($_POST['to_type']);

            $result = Olama_School_Schedule::clone_schedule($section_id, $semester_id, $from_type, $to_type);

            wp_cache_flush();

            $redirect_url = admin_url('admin.php?page=olama-school-plans&tab=schedule&grade_id=' . $grade_id . '&section_id=' . $section_id . '&semester_id=' . $semester_id . '&schedule_type=' . $to_type);

            if ($result) {
                wp_redirect(add_query_arg('message', 'clone_success', $redirect_url));
            } else {
                wp_redirect(add_query_arg('message', 'clone_error', $redirect_url));
            }
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_full_schedule' && isset($_GET['section_id']) && isset($_GET['semester_id'])) {
            check_admin_referer('olama_delete_full_schedule');
            if (!Olama_School_Permissions::can('olama_manage_plans_schedule')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $schedule_type = sanitize_text_field($_GET['schedule_type'] ?? 'normal');
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}olama_schedule", array(
                'section_id' => intval($_GET['section_id']),
                'semester_id' => intval($_GET['semester_id']),
                'schedule_type' => $schedule_type
            ));

            wp_redirect(remove_query_arg(array('action', 'section_id', 'semester_id', '_wpnonce', 'schedule_type')));
            exit;
        }
        // Handle Schedule Import
        if (isset($_POST['olama_import_schedule']) && check_admin_referer('olama_import_schedule')) {
            if (!Olama_School_Permissions::can('olama_manage_plans_schedule')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $grade_id = intval($_POST['grade_id']);
            $redirect_url = admin_url('admin.php?page=olama-school-plans&tab=schedule&grade_id=' . $grade_id . '&section_id=' . $section_id . '&semester_id=' . $semester_id);

            if (!isset($_FILES['olama_schedule_file']) || $_FILES['olama_schedule_file']['error'] !== UPLOAD_ERR_OK) {
                wp_redirect(add_query_arg('message', 'import_error_nofile', $redirect_url));
                exit;
            } else {
                $file = $_FILES['olama_schedule_file']['tmp_name'];

                if (($handle = fopen($file, 'r')) !== false) {
                    // Handle UTF-8 BOM
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }

                    // Read header and detect delimiter
                    $header_line = fgets($handle);
                    if (!$header_line) {
                        wp_redirect(add_query_arg('message', 'import_error_invalid', $redirect_url));
                        exit;
                    }

                    $delimiter = ',';
                    if (strpos($header_line, ';') !== false && strpos($header_line, ',') === false) {
                        $delimiter = ';';
                    }

                    $header = str_getcsv($header_line, $delimiter);

                    if (!$header || count($header) < 3) {
                        wp_redirect(add_query_arg('message', 'import_error_invalid', $redirect_url));
                        exit;
                    } else {
                        // Get subjects for mapping
                        $subjects = Olama_School_Subject::get_by_grade($grade_id);
                        $subject_map_by_name = array();
                        $subject_map_by_code = array();
                        foreach ($subjects as $subj) {
                            $subject_map_by_name[Olama_School_Helpers::normalize_arabic($subj->subject_name)] = $subj->id;
                            if ($subj->subject_code) {
                                $subject_map_by_code[Olama_School_Helpers::normalize_arabic($subj->subject_code)] = $subj->id;
                            }
                        }

                        $imported_count = 0;
                        $valid_days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');

                        while (($raw_row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            if (count($raw_row) < 3)
                                continue;

                            // Convert row to UTF-8 if it's not
                            $row = array_map(function ($item) {
                                if (!mb_check_encoding($item, 'UTF-8')) {
                                    return mb_convert_encoding($item, 'UTF-8', 'Windows-1256');
                                }
                                return $item;
                            }, $raw_row);

                            $day_raw = trim($row[0]);
                            $day = Olama_School_Helpers::get_day_translation($day_raw);

                            $period = intval($row[1]);
                            $subject_name = trim($row[2]);
                            $subject_code = isset($row[3]) ? trim($row[3]) : '';

                            if (!$day || empty($subject_name))
                                continue;

                            // Validate period
                            if ($period < 1 || $period > 12)
                                continue;

                            // Find subject ID
                            $subject_id = 0;
                            $subject_name_norm = Olama_School_Helpers::normalize_arabic($subject_name);
                            $subject_code_norm = Olama_School_Helpers::normalize_arabic($subject_code);

                            if (isset($subject_map_by_name[$subject_name_norm])) {
                                $subject_id = $subject_map_by_name[$subject_name_norm];
                            } elseif ($subject_code && isset($subject_map_by_code[$subject_code_norm])) {
                                $subject_id = $subject_map_by_code[$subject_code_norm];
                            }

                            if ($subject_id) {
                                $result = Olama_School_Schedule::save_schedule_item(array(
                                    'semester_id' => $semester_id,
                                    'section_id' => $section_id,
                                    'day_name' => $day,
                                    'period_number' => $period,
                                    'subject_id' => $subject_id,
                                    'schedule_type' => sanitize_text_field($_POST['schedule_type'] ?? 'normal')
                                ));
                                if ($result !== false) {
                                    $imported_count++;
                                }
                            }
                        }

                        fclose($handle);
                        wp_cache_flush();

                        if ($imported_count > 0) {
                            wp_redirect(add_query_arg(array('message' => 'import_success', 'count' => $imported_count), $redirect_url));
                            exit;
                        } else {
                            wp_redirect(add_query_arg('message', 'import_error_nodata', $redirect_url));
                            exit;
                        }
                    }
                } else {
                    wp_redirect(add_query_arg('message', 'import_error_file', $redirect_url));
                    exit;
                }
            }
        }
    }

    /**
     * Handle Exam Save
     */
    public function handle_exam_save()
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (isset($_POST['olama_save_exam']) && check_admin_referer('olama_save_exam', 'olama_exam_nonce_field')) {
            $result = Olama_School_Exam::save_exam($_POST);

            $redirect_url = admin_url('admin.php?page=olama-school-exams&tab=exam_schedule');
            $redirect_url = add_query_arg(array(
                'academic_year_id' => intval($_POST['academic_year_id']),
                'semester_id' => intval($_POST['semester_id']),
                'grade_id' => intval($_POST['grade_id']),
                'subject_id' => intval($_POST['subject_id']),
                'message' => is_wp_error($result) ? 'error' : 'exam_saved'
            ), $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_exam' && isset($_GET['exam_id'])) {
            $exam_id = intval($_GET['exam_id']);
            if (check_admin_referer('olama_delete_exam_' . $exam_id)) {
                Olama_School_Exam::delete_exam($exam_id);
                $redirect_url = remove_query_arg(array('action', 'exam_id', '_wpnonce'), wp_get_referer());
                $redirect_url = add_query_arg('message', 'exam_deleted', $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }
        }
    }

    /**
     * Handle Stationary Save
     */
    public function handle_stationary_save()
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (isset($_POST['olama_save_stationary']) && check_admin_referer('olama_save_stationary', 'olama_stationary_nonce_field')) {
            $result = Olama_School_Stationary::save_stationary($_POST);

            $redirect_url = admin_url('admin.php?page=olama-school-academic&tab=stationary');
            $redirect_url = add_query_arg(array(
                'academic_year_id' => intval($_POST['academic_year_id']),
                'grade_id' => intval($_POST['grade_id']),
                'message' => is_wp_error($result) ? 'error' : 'stationary_saved'
            ), $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle Evaluation Curriculum Actions
     */
    public function handle_kg_curriculum_actions()
    {
        $manager = new Olama_School_EV_Manager();
        $manager->handle_actions();
    }

    /**
     * Handle Evaluation Save
     */
    public function handle_kg_evaluation_save()
    {
        $form = new Olama_School_EV_Form();
        $form->handle_save();
    }

    /**
     * AJAX Evaluation Autosave
     */
    public function ajax_kg_autosave()
    {
        $form = new Olama_School_EV_Form();
        $form->ajax_autosave();
    }

    /**
     * Handle Lesson Planner Actions (Save, Delete)
     */
    public function handle_lesson_planner_actions()
    {
        if (!is_admin() || !Olama_School_Permissions::can('olama_manage_lesson_planner')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'olama_lesson_plans';

        // 1. Schema Check (Self-healing)
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'period_duration'));
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN period_duration tinyint(4) DEFAULT 45 NOT NULL AFTER number_of_classes");
        }

        // 2. Handle POST save
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['olama_lesson_plan_save'])) {
            check_admin_referer('olama_lesson_plan_nonce', 'olama_lesson_plan_nonce');

            $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;

            // Security check: Only allow owners or admins/supervisors to edit
            if ($plan_id > 0) {
                $is_admin_priv = Olama_School_Permissions::can('olama_manage_evaluation_mgmt') || Olama_School_Permissions::can('olama_approve_plans');
                $existing = Olama_School_Lesson_Planner::get_plan($plan_id, $is_admin_priv ? 0 : get_current_user_id());
                if (!$existing) {
                    wp_die(__('You do not have permission to edit this lesson plan.', 'olama-school'));
                }
            }

            $stage_keys = array_keys(Olama_Lesson_Planner_Config::get_stages());
            $stages_data = array();
            $ts_used = array();
            $as_used = array();
            $at_used = array();

            foreach ($stage_keys as $key) {
                if (isset($_POST['stage'][$key])) {
                    $stages_data[$key] = array(
                        'teacher_action' => sanitize_textarea_field($_POST['stage'][$key]['teacher_action'] ?? ''),
                        'learner_action' => sanitize_textarea_field($_POST['stage'][$key]['learner_action'] ?? ''),
                        'teaching_strategy' => sanitize_text_field($_POST['stage'][$key]['teaching_strategy'] ?? ''),
                        'assessment_strategy' => sanitize_text_field($_POST['stage'][$key]['assessment_strategy'] ?? ''),
                        'assessment_tool' => sanitize_text_field($_POST['stage'][$key]['assessment_tool'] ?? ''),
                        'time_minutes' => intval($_POST['stage'][$key]['time_minutes'] ?? 0),
                    );

                    if (!empty($stages_data[$key]['teaching_strategy']))
                        $ts_used[] = $stages_data[$key]['teaching_strategy'];
                    if (!empty($stages_data[$key]['assessment_strategy']))
                        $as_used[] = $stages_data[$key]['assessment_strategy'];
                    if (!empty($stages_data[$key]['assessment_tool']))
                        $at_used[] = $stages_data[$key]['assessment_tool'];
                }
            }

            $plan_data = array(
                'id' => isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0,
                'academic_year_id' => intval($_POST['academic_year_id']),
                'semester_id' => intval($_POST['semester_id']),
                'teacher_id' => ($plan_id > 0 && isset($existing)) ? $existing->teacher_id : get_current_user_id(),
                'subject_id' => intval($_POST['subject_id']),
                'grade_id' => intval($_POST['grade_id']),
                'section_id' => intval($_POST['section_id']),
                'unit_id' => intval($_POST['unit_id'] ?? 0),
                'lesson_id' => intval($_POST['lesson_id'] ?? 0),
                'lesson_title' => sanitize_text_field($_POST['lesson_title']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'number_of_classes' => intval($_POST['number_of_classes'] ?? 1),
                'period_duration' => intval($_POST['period_duration'] ?? 45),
                'prior_learning' => sanitize_textarea_field($_POST['prior_learning']),
                'learning_outcomes' => array(),
                'stages' => $stages_data,
                'teaching_strategies_used' => array_values(array_unique($ts_used)),
                'assessment_strategies_used' => array_values(array_unique($as_used)),
                'assessment_tools_used' => array_values(array_unique($at_used)),
                'resources' => sanitize_textarea_field($_POST['resources']),
                'self_reflection' => sanitize_textarea_field($_POST['self_reflection']),
                'homework' => sanitize_textarea_field($_POST['homework']),
                'status' => sanitize_text_field($_POST['plan_status'] ?? 'draft'),
            );

            if (!empty($_POST['outcome_content'])) {
                foreach ($_POST['outcome_content'] as $idx => $content) {
                    if (!empty($content)) {
                        $plan_data['learning_outcomes'][] = array(
                            'verb' => sanitize_text_field($_POST['outcome_verb'][$idx] ?? ''),
                            'content' => sanitize_textarea_field($content),
                            'level' => sanitize_text_field($_POST['outcome_level'][$idx] ?? ''),
                        );
                    }
                }
            }

            $result = Olama_School_Lesson_Planner::save_plan($plan_data);
            if ($result['result'] !== false) {
                wp_redirect(admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&message=lp_saved'));
                exit;
            } else {
                set_transient('lp_error_' . get_current_user_id(), $result['error'], 60);
                wp_redirect(admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&lp_action=' . ($_POST['plan_id'] ? 'edit&plan_id=' . $_POST['plan_id'] : 'create') . '&lp_err=1'));
                exit;
            }
        }

        // 3. Handle GET delete
        if (isset($_GET['lp_action']) && $_GET['lp_action'] === 'delete' && isset($_GET['plan_id'])) {
            $plan_id = intval($_GET['plan_id']);
            check_admin_referer('olama_lp_delete_' . $plan_id);

            $is_admin_priv = Olama_School_Permissions::can('olama_manage_evaluation_mgmt') || Olama_School_Permissions::can('olama_approve_plans');
            $success = Olama_School_Lesson_Planner::delete_plan($plan_id, $is_admin_priv ? 0 : get_current_user_id());

            if ($success) {
                wp_redirect(admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&message=lp_deleted'));
            } else {
                wp_redirect(admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&message=' . urlencode(__('Error deleting lesson plan or permission denied.', 'olama-school'))));
            }
            exit;
        }
    }

    /**
     * Handle Evaluation Report Print
     */
    public function handle_kg_report_print()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'ev_print_report' && isset($_GET['evaluation_id'])) {
            Olama_School_EV_Report::render_report(intval($_GET['evaluation_id']));
            exit;
        }
    }

    /**
     * Handle Backup and Restore Actions
     */
    public function handle_backup_restore_actions()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // Handle Export
        if (isset($_POST['olama_export_db']) && check_admin_referer('olama_backup_action', 'olama_backup_nonce')) {
            $backup_data = Olama_School_Backup::generate_backup();
            $filename = 'olama-backup-' . current_time('Y-m-d-His') . '.json';

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            if (ob_get_length()) {
                ob_clean();
            }

            echo json_encode($backup_data);
            exit;
        }

        // Handle Restore
        if (isset($_POST['olama_restore_db'])) {
            // Restore is now handled via AJAX for better performance and progress reporting.
            return;
        }

        // Handle Scheduled Backup Settings Save
        if (isset($_POST['olama_save_backup_settings']) && check_admin_referer('olama_backup_settings_action', 'olama_backup_settings_nonce')) {
            $frequency = sanitize_text_field($_POST['olama_backup_frequency'] ?? 'disabled');
            $retention = intval($_POST['olama_backup_retention'] ?? 7);
            $backup_path = sanitize_text_field($_POST['olama_backup_path'] ?? '');

            update_option('olama_backup_frequency', $frequency);
            update_option('olama_backup_retention', $retention);
            update_option('olama_backup_path', $backup_path);

            // Reschedule WP-Cron
            wp_clear_scheduled_hook('olama_scheduled_backup');
            if ($frequency !== 'disabled') {
                $recurrence = ($frequency === 'daily') ? 'daily' : 'weekly';
                wp_schedule_event(time(), $recurrence, 'olama_scheduled_backup');
            }

            wp_redirect(add_query_arg('message', 'settings_saved', admin_url('admin.php?page=olama-school-settings&tab=backup')));
            exit;
        }

        // Handle Manual Save to Server
        if (isset($_POST['olama_manual_save_to_server']) && check_admin_referer('olama_backup_action', 'olama_backup_nonce')) {
            $result = Olama_School_Backup::save_backup_to_server();
            if (is_wp_error($result)) {
                wp_redirect(add_query_arg('error', $result->get_error_message(), admin_url('admin.php?page=olama-school-settings&tab=backup')));
            } else {
                wp_redirect(add_query_arg('message', 'backup_saved', admin_url('admin.php?page=olama-school-settings&tab=backup')));
            }
            exit;
        }
    }

    /**
     * AJAX: Upload Backup Chunk (1MB slices)
     */
    public function ajax_upload_backup_chunk()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'olama-school'));
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'olama_backup_action')) {
            wp_send_json_error(__('Security check failed.', 'olama-school'));
        }

        $chunk = $_FILES['chunk'] ?? null;
        $chunk_index = intval($_POST['chunk_index'] ?? 0);
        $total_chunks = intval($_POST['total_chunks'] ?? 0);
        $filename = sanitize_file_name($_POST['filename'] ?? 'upload.json');
        $upload_id = sanitize_key($_POST['upload_id'] ?? '');

        if (!$chunk || !$upload_id) {
            wp_send_json_error(__('Missing chunk or upload ID.', 'olama-school'));
        }

        // Use a secure temp directory
        $temp_dir = Olama_School_Backup::get_backup_storage_dir() . 'tmp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $temp_file = $temp_dir . 'restore_' . $upload_id . '.json';

        // Append chunk to file
        $out = fopen($temp_file, $chunk_index === 0 ? 'wb' : 'ab');
        $in = fopen($chunk['tmp_name'], 'rb');

        if ($out && $in) {
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
            fclose($in);
            fclose($out);
        } else {
            wp_send_json_error(__('Failed to write chunk to disk.', 'olama-school'));
        }

        wp_send_json_success(array(
            'chunk_index' => $chunk_index,
            'is_last' => ($chunk_index === $total_chunks - 1)
        ));
    }

    /**
     * AJAX: Restore Database (Multi-Stage Processing)
     */
    public function ajax_restore_database()
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'olama_backup_action')) {
            wp_send_json_error(__('Security check failed.', 'olama-school'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'olama-school'));
        }

        $upload_id = sanitize_key($_POST['upload_id'] ?? '');
        $step = sanitize_key($_POST['step'] ?? 'init');
        $temp_file = Olama_School_Backup::get_backup_storage_dir() . 'tmp/restore_' . $upload_id . '.json';

        if (!$upload_id || !file_exists($temp_file)) {
            wp_send_json_error(__('Restoration file not found on server.', 'olama-school'));
        }

        // Increase limits for processing
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');

        $json_data = file_get_contents($temp_file);

        if ($step === 'get_index') {
            $index = Olama_School_Backup::get_restore_index($json_data);
            if (is_wp_error($index)) {
                wp_send_json_error($index->get_error_message());
            }
            wp_send_json_success($index);
        } elseif ($step === 'restore_table') {
            $part_id = $_POST['part_id'] ?? '';
            $table_name = $_POST['table_name'] ?? '';

            if (!$table_name) {
                wp_send_json_error(__('Table name missing.', 'olama-school'));
            }

            $result = Olama_School_Backup::restore_single_table($json_data, $part_id, $table_name);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(sprintf(__('Restored table %s', 'olama-school'), $table_name));
        } elseif ($step === 'finalize') {
            @unlink($temp_file);
            wp_send_json_success(__('Restoration completed successfully!', 'olama-school'));
        }

        wp_send_json_error(__('Invalid step.', 'olama-school'));
    }


    /**
     * Render Backup & Restore Tab Content
     */
    private function render_backup_settings_content()
    {
        if (isset($_GET['restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Database restored successfully!', 'olama-school') . '</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($_GET['error']) . '</p></div>';
        }
        ?>
        <div class="card" style="max-width: 800px; padding: 25px;">
            <h2 style="margin-top:0;"><?php _e('Manual Backup', 'olama-school'); ?></h2>
            <p><?php _e('Generate a full backup of all school data and download it or save it securely to the server.', 'olama-school'); ?>
            </p>

            <form method="post" action="" style="display:inline-block; margin-right: 10px;">
                <?php wp_nonce_field('olama_backup_action', 'olama_backup_nonce'); ?>
                <input type="hidden" name="olama_export_db" value="1" />
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Generate & Download Backup', 'olama-school'); ?>
                </button>
            </form>

            <form method="post" action="" style="display:inline-block;">
                <?php wp_nonce_field('olama_backup_action', 'olama_backup_nonce'); ?>
                <input type="hidden" name="olama_manual_save_to_server" value="1" />
                <button type="submit" class="button">
                    <span class="dashicons dashicons-cloud-upload" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Save Backup to Server', 'olama-school'); ?>
                </button>
            </form>

            <hr style="margin: 30px 0;">

            <h2><?php _e('Scheduled Backups & Retention', 'olama-school'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('olama_backup_settings_action', 'olama_backup_settings_nonce'); ?>
                <input type="hidden" name="olama_save_backup_settings" value="1" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Backup Frequency', 'olama-school'); ?></th>
                        <td>
                            <?php $freq = get_option('olama_backup_frequency', 'disabled'); ?>
                            <select name="olama_backup_frequency">
                                <option value="disabled" <?php selected($freq, 'disabled'); ?>>
                                    <?php _e('Disabled', 'olama-school'); ?>
                                </option>
                                <option value="daily" <?php selected($freq, 'daily'); ?>><?php _e('Daily', 'olama-school'); ?>
                                </option>
                                <option value="weekly" <?php selected($freq, 'weekly'); ?>>
                                    <?php _e('Weekly', 'olama-school'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Retention (Keep latest)', 'olama-school'); ?></th>
                        <td>
                            <input type="number" name="olama_backup_retention"
                                value="<?php echo esc_attr(get_option('olama_backup_retention', 7)); ?>" min="1" max="100" />
                            <p class="description">
                                <?php _e('Number of automated backups to keep on the server.', 'olama-school'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Storage Path', 'olama-school'); ?></th>
                        <td>
                            <input type="text" name="olama_backup_path"
                                value="<?php echo esc_attr(get_option('olama_backup_path', '')); ?>" class="regular-text" />
                            <p class="description">
                                <?php
                                $default_path = Olama_School_Backup::get_backup_storage_dir();
                                printf(__('Current effective path: %s', 'olama-school'), '<code>' . esc_html($default_path) . '</code>');
                                ?><br>
                                <?php _e('Leave empty to use the system default outside the public folder.', 'olama-school'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit"
                        class="button button-secondary"><?php _e('Save Schedule Settings', 'olama-school'); ?></button>
                </p>
            </form>

            <hr style="margin: 30px 0;">

            <h3><?php _e('Server Side Backups', 'olama-school'); ?></h3>
            <div style="background: #f8f9fa; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <?php
                $backup_dir = Olama_School_Backup::get_backup_storage_dir();
                $files = glob($backup_dir . 'olama-*.json');
                if (empty($files)) {
                    _e('No server-side backups found.', 'olama-school');
                } else {
                    echo '<ul style="margin:0; padding-left:20px;">';
                    // Show newest first
                    usort($files, function ($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    foreach ($files as $file) {
                        $filename = basename($file);
                        $size = size_format(filesize($file));
                        $date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                        echo "<li><strong>$filename</strong> ($size) - $date</li>";
                    }
                    echo '</ul>';
                }
                ?>
                <p class="description">
                    <span class="dashicons dashicons-info-outline" style="font-size: 16px; margin-right: 5px;"></span>
                    <?php printf(__('Stored in: %s', 'olama-school'), '<code>' . esc_html($backup_dir) . '</code>'); ?>
                </p>
            </div>

            <hr style="margin: 40px 0;">

            <h2 style="margin-top:0; color: #d63638;"><?php _e('Restore Data', 'olama-school'); ?></h2>
            <p style="color: #d63638; font-weight: 600;">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('WARNING: Restoring data will PERMANENTLY overwrite all current plugin data with the contents of the backup file.', 'olama-school'); ?>
            </p>

            <form id="olama-restore-form" enctype="multipart/form-data">
                <p>
                    <input type="file" id="restore-file" name="backup_file" accept=".json" required />
                </p>
                <button type="button" id="start-restore" class="button"
                    style="background: #fcf0f1; border-color: #d63638; color: #d63638;">
                    <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php _e('Start Optimized Restoration', 'olama-school'); ?>
                </button>
            </form>

            <!-- Progress UI -->
            <div id="restore-progress-container" style="display: none; margin-top: 30px;">
                <h3 id="restore-status"><?php _e('Preparing Restoration...', 'olama-school'); ?></h3>
                <div style="background: #eee; border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 15px;">
                    <div id="restore-progress-bar"
                        style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>

                <div id="restore-log"
                    style="background: #f0f0f1; border: 1px solid #dcdcde; height: 200px; overflow-y: auto; padding: 15px; font-family: monospace; font-size: 12px; color: #1d2327;">
                    <div>[WAITING] Upload a file and click "Start"</div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                const $form = $('#olama-restore-form');
                const $fileInput = $('#restore-file');
                const $startButton = $('#start-restore');
                const $progressContainer = $('#restore-progress-container');
                const $progressBar = $('#restore-progress-bar');
                const $statusText = $('#restore-status');
                const $log = $('#restore-log');

                function addLog(message, type = 'info') {
                    const colors = { info: '#1d2327', success: '#008a20', error: '#d63638' };
                    const time = new Date().toLocaleTimeString();
                    $log.append(`<div style="color: ${colors[type]}">[${time}] ${message}</div>`);
                    $log.scrollTop($log[0].scrollHeight);
                }

                $startButton.on('click', function () {
                    const file = $fileInput[0].files[0];
                    if (!file) {
                        alert('<?php _e('Please select a backup file.', 'olama-school'); ?>');
                        return;
                    }

                    if (!confirm('<?php _e('Are you absolutely sure? All current data will be lost.', 'olama-school'); ?>')) {
                        return;
                    }

                    $startButton.prop('disabled', true);
                    $fileInput.prop('disabled', true);
                    $progressContainer.show();
                    $log.empty();

                    const upload_id = Date.now() + '-' + Math.floor(Math.random() * 1000);
                    const chunk_size = 1024 * 1024; // 1MB chunks
                    const total_chunks = Math.ceil(file.size / chunk_size);
                    const nonce = '<?php echo wp_create_nonce("olama_backup_action"); ?>';

                    addLog('<?php _e('Starting chunked upload...', 'olama-school'); ?>');

                    function uploadChunk(index) {
                        const start = index * chunk_size;
                        const end = Math.min(start + chunk_size, file.size);
                        const chunk = file.slice(start, end);

                        const formData = new FormData();
                        formData.append('action', 'olama_upload_backup_chunk');
                        formData.append('chunk', chunk);
                        formData.append('chunk_index', index);
                        formData.append('total_chunks', total_chunks);
                        formData.append('upload_id', upload_id);
                        formData.append('filename', file.name);
                        formData.append('nonce', nonce);

                        const progress = Math.round((index / total_chunks) * 100);
                        $progressBar.css('width', progress + '%');
                        $statusText.text('<?php _e('Uploading...', 'olama-school'); ?> ' + progress + '%');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function (response) {
                                if (response.success) {
                                    if (index + 1 < total_chunks) {
                                        uploadChunk(index + 1);
                                    } else {
                                        addLog('<?php _e('Upload complete! Preparing database...', 'olama-school'); ?>', 'success');
                                        startRestore();
                                    }
                                } else {
                                    addLog('<?php _e('Upload Error:', 'olama-school'); ?> ' + response.data, 'error');
                                    resetUI();
                                }
                            },
                            error: function () {
                                addLog('<?php _e('Network error during upload.', 'olama-school'); ?>', 'error');
                                resetUI();
                            }
                        });
                    }

                    function startRestore() {
                        addLog('<?php _e('Analyzing backup file...', 'olama-school'); ?>');
                        $statusText.text('<?php _e('Preparing database restoration...', 'olama-school'); ?>');

                        $.post(ajaxurl, {
                            action: 'olama_initiate_restore',
                            upload_id: upload_id,
                            step: 'get_index',
                            nonce: nonce
                        }, function (response) {
                            if (response.success) {
                                const index = response.data;
                                addLog('<?php _e('Backup analysis complete.', 'olama-school'); ?> ' + index.length + ' tables found.', 'success');
                                restoreTables(index, 0);
                            } else {
                                addLog('<?php _e('Index Error:', 'olama-school'); ?> ' + response.data, 'error');
                                resetUI();
                            }
                        }).fail(function () {
                            addLog('<?php _e('Server timeout during analysis.', 'olama-school'); ?>', 'error');
                            resetUI();
                        });
                    }

                    function restoreTables(index, i) {
                        if (i >= index.length) {
                            finalizeRestore();
                            return;
                        }

                        const item = index[i];
                        const progress = Math.round((i / index.length) * 100);
                        $progressBar.css('width', progress + '%').css('background', '#2271b1');
                        $statusText.text('<?php _e('Restoring table:', 'olama-school'); ?> ' + item.table + ' (' + (i + 1) + '/' + index.length + ')');

                        $.post(ajaxurl, {
                            action: 'olama_initiate_restore',
                            upload_id: upload_id,
                            step: 'restore_table',
                            part_id: item.part,
                            table_name: item.table,
                            nonce: nonce
                        }, function (response) {
                            if (response.success) {
                                addLog(response.data);
                                restoreTables(index, i + 1);
                            } else {
                                addLog('<?php _e('Error in table', 'olama-school'); ?> ' + item.table + ': ' + response.data, 'error');
                                resetUI();
                            }
                        }).fail(function () {
                            // Automatically retry once if it's a network glitch
                            addLog('<?php _e('Network glitch on table:', 'olama-school'); ?> ' + item.table + '. Retrying...', 'error');
                            setTimeout(() => restoreTables(index, i), 1000);
                        });
                    }

                    function finalizeRestore() {
                        $progressBar.css('width', '100%');
                        $statusText.text('<?php _e('Finalizing restoration...', 'olama-school'); ?>');

                        $.post(ajaxurl, {
                            action: 'olama_initiate_restore',
                            upload_id: upload_id,
                            step: 'finalize',
                            nonce: nonce
                        }, function (response) {
                            addLog(response.data, 'success');
                            alert(response.data);
                            location.reload();
                        });
                    }

                    function resetUI() {
                        $startButton.prop('disabled', false);
                        $fileInput.prop('disabled', false);
                    }

                    uploadChunk(0);
                });
            });
        </script>
        <?php
    }

    /**
     * Handle Teacher Settings Save (Language only)
     */
    public function handle_teacher_settings_save()
    {
        if (isset($_POST['olama_teacher_save']) && check_admin_referer('olama_teacher_settings_save', 'olama_teacher_settings_nonce')) {
            if (!Olama_School_Permissions::can('olama_view_dashboard')) {
                wp_die(__('Unauthorized access.', 'olama-school'));
            }

            $current_settings = get_option('olama_school_settings', array());
            $new_lang = sanitize_text_field($_POST['olama_school_settings']['default_lang'] ?? 'ar');

            // Validate language
            if (!in_array($new_lang, ['ar', 'en'])) {
                $new_lang = 'ar';
            }

            $current_settings['default_lang'] = $new_lang;
            update_option('olama_school_settings', $current_settings);

            wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=olama-school-settings')));
            exit;
        }
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('Olama School', 'olama-school'),
            __('Olama School', 'olama-school'),
            'olama_view_dashboard',
            'olama-school',
            array($this, 'render_dashboard_page'),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Dashboard'),
            Olama_School_Helpers::translate('Dashboard'),
            'olama_view_dashboard',
            'olama-school',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Reports'),
            Olama_School_Helpers::translate('Reports'),
            'olama_access_reports',
            'olama-school-reports',
            array($this, 'render_reports_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Weekly Plan Management'),
            Olama_School_Helpers::translate('Weekly Plan Management'),
            'olama_access_plans_mgmt',
            'olama-school-plans',
            array($this, 'render_weekly_plan_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Follow Up'),
            Olama_School_Helpers::translate('Follow Up'),
            'olama_access_followup',
            'olama-school-follow-up',
            array($this, 'render_follow_up_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Academic Management'),
            Olama_School_Helpers::translate('Academic Management'),
            'olama_access_academic_mgmt',
            'olama-school-academic',
            array($this, 'render_academic_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Curriculum Management'),
            Olama_School_Helpers::translate('Curriculum Management'),
            'olama_access_curriculum_mgmt',
            'olama-school-curriculum',
            array($this, 'render_curriculum_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Exam Management'),
            Olama_School_Helpers::translate('Exam Management'),
            'olama_access_exams_mgmt',
            'olama-school-exams',
            array($this, 'render_exam_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Evaluation'),
            Olama_School_Helpers::translate('Evaluation'),
            'olama_access_evaluation',
            'olama-school-evaluation',
            array($this, 'render_evaluation_page')
        );

        add_submenu_page(
            'olama-school',
            __('Academic Supervision', 'olama-school'),
            __('Academic Supervision', 'olama-school'),
            'olama_access_supervision',
            'olama-school-supervision',
            array($this, 'render_academic_supervision_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Users & Permissions'),
            Olama_School_Helpers::translate('Users & Permissions'),
            'olama_access_users_mgmt',
            'olama-school-users',
            array($this, 'render_users_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Transportation'),
            Olama_School_Helpers::translate('Transportation'),
            'olama_access_transport_mgmt',
            'olama-school-transport',
            array($this, 'render_transport_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Settings'),
            Olama_School_Helpers::translate('Settings'),
            'olama_access_settings_mgmt',
            'olama-school-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('olama_school_settings_group', 'olama_school_settings');
        register_setting('olama_notifications_group', 'olama_admin_email');
        register_setting('olama_notifications_group', 'olama_enable_notifs');
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin pages
        if (strpos($hook, 'olama-school') === false) {
            return;
        }

        wp_enqueue_style('olama-admin-style', OLAMA_SCHOOL_URL . 'assets/css/admin.css', array(), time());
        wp_enqueue_style('jquery-ui-datepicker-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

        if (Olama_School_Helpers::is_arabic()) {
            wp_enqueue_style('olama-admin-rtl', OLAMA_SCHOOL_URL . 'assets/css/admin-rtl.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('olama-admin-script', OLAMA_SCHOOL_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-datepicker'), time(), true);

        wp_localize_script('olama-admin-script', 'olamaAdmin', array(
            'dateFormat' => 'dd-mm-yy',
            'isArabic' => Olama_School_Helpers::is_arabic(),
            'adminNonce' => wp_create_nonce('olama_admin_nonce'),
        ));

        $page = $_GET['page'] ?? '';

        if ($page === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-script', OLAMA_SCHOOL_URL . 'assets/js/plan.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            $active_year = Olama_School_Academic::get_active_year();
            $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

            // Calculate semester_id based on selected date to ensure AJAX works correctly
            $today_val = Olama_School_Helpers::get_active_week_start();
            $week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;

            $school_days = Olama_School_Helpers::get_school_days();
            $active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : ($school_days[0] ?? 'Sunday');

            $days_map = array_flip($school_days);
            $offset = $days_map[$active_day] ?? 0;
            $selected_date = date('Y-m-d', strtotime($week_start . " +$offset days"));

            $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
            $semester_id = 0;
            $selected_date_ts = strtotime($selected_date);

            foreach ($semesters as $sem) {
                if ($selected_date_ts >= strtotime($sem->start_date) && $selected_date_ts <= strtotime($sem->end_date)) {
                    $semester_id = $sem->id;
                    break;
                }
            }
            if (!$semester_id && !empty($semesters)) {
                $semester_id = $semesters[0]->id;
            }

            wp_localize_script('olama-plan-script', 'olamaPlan', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_curriculum_nonce'),
                'savePlanNonce' => wp_create_nonce('olama_save_plan'),
                'semesterId' => $semester_id,
                'i18n' => array(
                    'selectSubject' => Olama_School_Helpers::translate('Please select a subject.'),
                    'selectUnit' => Olama_School_Helpers::translate('Please select a unit.'),
                    'selectLesson' => Olama_School_Helpers::translate('Please select a lesson.'),
                    'noUnits' => Olama_School_Helpers::translate('No units found.'),
                    'selectLesson' => Olama_School_Helpers::translate('Select Lesson'),
                    'noLessons' => Olama_School_Helpers::translate('No lessons found.'),
                    'noQuestions' => Olama_School_Helpers::translate('No questions found for this lesson.'),
                    'currentStatus' => Olama_School_Helpers::translate('Current Status'),
                    'published' => Olama_School_Helpers::translate('Published'),
                    'draft' => Olama_School_Helpers::translate('Draft'),
                    'revertToDraft' => Olama_School_Helpers::translate('Revert to Draft'),
                    'saveAsDraft' => Olama_School_Helpers::translate('Save as Draft'),
                    'updatePlan' => Olama_School_Helpers::translate('Update Plan'),
                    'loading' => Olama_School_Helpers::translate('Loading...'),
                    'errorLoadingUnits' => Olama_School_Helpers::translate('Error loading units'),
                    'errorLoadingLessons' => Olama_School_Helpers::translate('Error loading lessons'),
                    'loadingQuestions' => Olama_School_Helpers::translate('Loading questions...'),
                    'errorLoadingQuestions' => Olama_School_Helpers::translate('Error loading questions'),
                    'confirmDelete' => Olama_School_Helpers::translate('Are you sure you want to delete this plan?'),
                    'deletePlanError' => Olama_School_Helpers::translate('An error occurred while deleting the plan.'),
                    'failedDelete' => Olama_School_Helpers::translate('Failed to delete plan.'),
                    'noPlansToday' => Olama_School_Helpers::translate('No plans saved for today yet.'),
                    'onTime' => Olama_School_Helpers::translate('On-time'),
                    'delayedBy' => Olama_School_Helpers::translate('Delayed by %d days'),
                    'bypassBy' => Olama_School_Helpers::translate('Bypass by %d days'),
                    'approve' => Olama_School_Helpers::translate('Approve'),
                    'requestEdits' => Olama_School_Helpers::translate('Request Edits'),
                    'submitRevision' => Olama_School_Helpers::translate('Submit Revision'),
                    'needsRevision' => Olama_School_Helpers::translate('Needs Revision'),
                    'edited' => Olama_School_Helpers::translate('Edited'),
                    'sending' => Olama_School_Helpers::translate('Sending...'),
                    'approving' => Olama_School_Helpers::translate('Approving...'),
                    'enterFeedback' => Olama_School_Helpers::translate('Please enter some feedback.'),
                    'atLeastOneHomework' => Olama_School_Helpers::translate('Please enter at least one homework (Student Book, Workbook, Notebook, or Booklet/Worksheet).'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum') {
            $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

            // If tab is missing, determine what it will default to in render_curriculum_management_page
            if (empty($current_tab)) {
                if (Olama_School_Permissions::can('olama_manage_curriculum_list')) {
                    $current_tab = 'curriculum';
                } elseif (Olama_School_Permissions::can('olama_manage_curriculum_timeline') || Olama_School_Permissions::can('olama_view_curriculum_timeline')) {
                    $current_tab = 'timeline';
                } elseif (Olama_School_Permissions::can('olama_manage_curriculum_upload')) {
                    $current_tab = 'bulk_upload';
                } elseif (Olama_School_Permissions::can('olama_manage_curriculum_analysis')) {
                    $current_tab = 'analysis';
                }
            }

            if ($current_tab === 'timeline') {
                wp_enqueue_style('olama-timeline-style', OLAMA_SCHOOL_URL . 'assets/css/timeline.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
                wp_enqueue_script('olama-timeline-script', OLAMA_SCHOOL_URL . 'assets/js/timeline.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
                wp_localize_script('olama-timeline-script', 'olamaTimeline', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('olama_admin_nonce'),
                    'curriculumNonce' => wp_create_nonce('olama_curriculum_nonce'),
                    'canManage' => Olama_School_Permissions::can('olama_manage_curriculum_timeline'),
                    'i18n' => array(
                        'selectSubject' => Olama_School_Helpers::translate('Select Subject'),
                        'loading' => Olama_School_Helpers::translate('Loading...'),
                        'saving' => Olama_School_Helpers::translate('Saving...'),
                        'error' => Olama_School_Helpers::translate('An error occurred.'),
                        'dateInvalid' => Olama_School_Helpers::translate('Start date cannot be after end date.'),
                        'outsideSemester' => Olama_School_Helpers::translate('Dates must be within the semester range.'),
                        'unitsOverlap' => Olama_School_Helpers::translate('Unit dates cannot overlap.'),
                        'lessonOutsideUnit' => Olama_School_Helpers::translate('Lesson dates must be within unit dates.'),
                        'confirmClear' => Olama_School_Helpers::translate('Are you sure you want to clear all dates? This will remove all start and end dates for the current view.'),
                        'noUnitsFound' => Olama_School_Helpers::translate('No units found for this selection.'),
                        'unit' => Olama_School_Helpers::translate('Unit'),
                        'unitStart' => Olama_School_Helpers::translate('Unit Start'),
                        'unitEnd' => Olama_School_Helpers::translate('Unit End'),
                        'lessonTitle' => Olama_School_Helpers::translate('Lesson Title'),
                        'periods' => Olama_School_Helpers::translate('Periods'),
                        'startDate' => Olama_School_Helpers::translate('Start Date'),
                        'endDate' => Olama_School_Helpers::translate('End Date'),
                        'fixErrors' => Olama_School_Helpers::translate('Please fix validation errors before saving.'),
                        'loadTimeline' => Olama_School_Helpers::translate('Load Timeline'),
                        'saveAllDates' => Olama_School_Helpers::translate('Save All Dates'),
                    )
                ));
            } else {
                // Default curriculum assets
                wp_enqueue_style('olama-curriculum-style', OLAMA_SCHOOL_URL . 'assets/css/curriculum.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
                wp_enqueue_script('olama-curriculum-script', OLAMA_SCHOOL_URL . 'assets/js/curriculum.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
                $settings = get_option('olama_school_settings', array());
                wp_localize_script('olama-curriculum-script', 'olamaCurriculum', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('olama_curriculum_nonce'),
                    'isDeletionPasswordSet' => !empty($settings['deletion_password']),
                    'i18n' => array(
                        'selectSubject' => Olama_School_Helpers::translate('Select Subject'),
                        'noUnits' => Olama_School_Helpers::translate('No units found for this subject.'),
                        'noLessons' => Olama_School_Helpers::translate('No lessons found for this unit.'),
                        'noQuestions' => Olama_School_Helpers::translate('No questions found for this lesson.'),
                        'edit' => Olama_School_Helpers::translate('Edit'),
                        'delete' => Olama_School_Helpers::translate('Delete'),
                        'confirmDelete' => Olama_School_Helpers::translate('Are you sure you want to delete this item?'),
                        'unitNumberRequired' => Olama_School_Helpers::translate('Unit number is required'),
                        'unitNameRequired' => Olama_School_Helpers::translate('Unit name is required'),
                        'unitExists' => Olama_School_Helpers::translate('Unit # already exists.'),
                        'errorDeletingUnit' => Olama_School_Helpers::translate('Error deleting unit'),
                        'errorLoadingLessons' => Olama_School_Helpers::translate('Error loading lessons.'),
                        'errorConnection' => Olama_School_Helpers::translate('Error connecting to server.'),
                        'periodsLabel' => Olama_School_Helpers::translate('(%d periods)'),
                        'noTitle' => Olama_School_Helpers::translate('(No Title)'),
                        'lessonNumberRequired' => Olama_School_Helpers::translate('Lesson number is required'),
                        'lessonTitleRequired' => Olama_School_Helpers::translate('Lesson title is required'),
                        'noUnitSelected' => Olama_School_Helpers::translate('No unit selected'),
                        'lessonExists' => Olama_School_Helpers::translate('Lesson # already exists in this unit.'),
                        'errorSavingLesson' => Olama_School_Helpers::translate('Error saving lesson'),
                        'questionNumberRequired' => Olama_School_Helpers::translate('Question number is required'),
                        'questionTextRequired' => Olama_School_Helpers::translate('Question text is required'),
                        'questionExists' => Olama_School_Helpers::translate('Question # already exists in this lesson.'),
                        'confirmClearCurriculum' => Olama_School_Helpers::translate('Are you sure you want to delete ALL units and lessons for "{subject}"? This action cannot be undone!'),
                        'deleting' => Olama_School_Helpers::translate('Deleting...'),
                        'curriculumCleared' => Olama_School_Helpers::translate('Curriculum cleared successfully!'),
                        'errorClearingCurriculum' => Olama_School_Helpers::translate('Error clearing curriculum.'),
                        'selectAll' => Olama_School_Helpers::translate('Please select semester, grade, and subject.'),
                        'securityError' => Olama_School_Helpers::translate('SECURITY ERROR: Admin Deletion Password not found.\nPlease navigate to Settings > General and set a deletion password before attempting this action.'),
                        'securityAuth' => Olama_School_Helpers::translate('SECURITY AUTHORIZATION REQUIRED: Please enter the Admin Deletion Password:'),
                        'criticalWarning' => Olama_School_Helpers::translate('CRITICAL WARNING: This will delete ALL curriculum data (Units, Lessons, Questions) for the selected year: {year}. This action is IRREVERSIBLE!\n\nAre you absolutely sure?'),
                        'finalConfirmation' => Olama_School_Helpers::translate('FINAL CONFIRMATION: To proceed, please type "DELETE" in the box below:'),
                        'wipeCancelledPassword' => Olama_School_Helpers::translate('Wipe cancelled. Password is required.'),
                        'wipeCancelledConfirm' => Olama_School_Helpers::translate('Wipe cancelled. Final confirmation mismatched.'),
                        'selectYearFirst' => Olama_School_Helpers::translate('Please select an academic year first.'),
                        'globalWipeSuccess' => Olama_School_Helpers::translate('Global curriculum wipe completed successfully!'),
                        'errorPerformingWipe' => Olama_School_Helpers::translate('Error performing global wipe.'),
                    )
                ));
            }
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum' && isset($_GET['tab']) && $_GET['tab'] === 'bulk_upload') {
            wp_enqueue_script('olama-bulk-upload-script', OLAMA_SCHOOL_URL . 'assets/js/bulk-upload.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-bulk-upload-script', 'olamaBulkUpload', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_bulk_upload_nonce'),
                'i18n' => array(
                    'selectBoth' => Olama_School_Helpers::translate('Please select both semester and grade'),
                    'selectFile' => Olama_School_Helpers::translate('Please select a file to upload'),
                    'uploading' => Olama_School_Helpers::translate('Uploading and processing...'),
                    'success' => Olama_School_Helpers::translate('Upload completed successfully'),
                    'error' => Olama_School_Helpers::translate('An error occurred during upload'),
                    'processingSubjects' => Olama_School_Helpers::translate('Processing subjects...'),
                    'subject' => Olama_School_Helpers::translate('Subject'),
                    'unitsImported' => Olama_School_Helpers::translate('Units Imported'),
                    'lessonsImported' => Olama_School_Helpers::translate('Lessons Imported'),
                    'status' => Olama_School_Helpers::translate('Status'),
                    'errors' => Olama_School_Helpers::translate('Errors'),
                    'totalSubjects' => Olama_School_Helpers::translate('Total Subjects Processed'),
                    'totalUnits' => Olama_School_Helpers::translate('Total Units Imported'),
                    'totalLessons' => Olama_School_Helpers::translate('Total Lessons Imported'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum' && isset($_GET['tab']) && $_GET['tab'] === 'analysis') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-list-script', OLAMA_SCHOOL_URL . 'assets/js/plan-list.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-plan-list-script', 'olamaPlanList', array(
                'isSupervisor' => current_user_can('olama_manage_plans'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'i18n' => array(
                    'details' => Olama_School_Helpers::translate('Plan Details'),
                    'subject' => Olama_School_Helpers::translate('Subject'),
                    'unit' => Olama_School_Helpers::translate('Unit'),
                    'lesson' => Olama_School_Helpers::translate('Lesson'),
                    'customTopic' => Olama_School_Helpers::translate('Topic'),
                    'homework' => Olama_School_Helpers::translate('Homework'),
                    'homeworkSB' => Olama_School_Helpers::translate('Homework (Student Book)'),
                    'homeworkEB' => Olama_School_Helpers::translate('Homework (Exercise Book)'),
                    'homeworkNB' => Olama_School_Helpers::translate('Homework (Notebook)'),
                    'homeworkWS' => Olama_School_Helpers::translate('Homework (Worksheet)'),
                    'teacherNotes' => Olama_School_Helpers::translate('Teacher Notes'),
                    'supervisorFeedback' => Olama_School_Helpers::translate('Supervisor Feedback'),
                    'status' => Olama_School_Helpers::translate('Status'),
                    'draft' => Olama_School_Helpers::translate('Draft'),
                    'submitted' => Olama_School_Helpers::translate('Submitted'),
                    'approved' => Olama_School_Helpers::translate('Approved'),
                    'published' => Olama_School_Helpers::translate('Approved'), // Legacy
                    'noDetails' => Olama_School_Helpers::translate('Click on a plan to see details.'),
                    'confirmBulkApprove' => Olama_School_Helpers::translate('Are you sure you want to approve (publish) all plans for this week and section?'),
                    'bulkApproveSuccess' => Olama_School_Helpers::translate('All plans have been approved successfully.'),
                    'onTime' => Olama_School_Helpers::translate('On-time'),
                    'delayedBy' => Olama_School_Helpers::translate('Delayed by %d days'),
                    'bypassBy' => Olama_School_Helpers::translate('Bypass by %d days'),
                    'loading' => Olama_School_Helpers::translate('Loading...'),
                    'approving' => Olama_School_Helpers::translate('Approving...'),
                    'approve' => Olama_School_Helpers::translate('Approve'),
                    'requestEdits' => Olama_School_Helpers::translate('Request Edits'),
                    'enterFeedback' => Olama_School_Helpers::translate('Please enter some feedback.'),
                    'sending' => Olama_School_Helpers::translate('Sending...'),
                    'errorOccurred' => Olama_School_Helpers::translate('Error occurred'),
                    'communicationError' => Olama_School_Helpers::translate('Communication error'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-follow-up') {
            wp_enqueue_script('olama-shifts-script', OLAMA_SCHOOL_URL . 'assets/js/shifts.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-shifts-script', 'olamaShifts', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'i18n' => array(
                    'selectTeacher' => __('Select Teacher', 'olama-school'),
                    'selectLocation' => __('Select Location', 'olama-school'),
                    'selectSlot' => __('Select Time Slot', 'olama-school'),
                    'confirmDelete' => __('Are you sure you want to delete this assignment?', 'olama-school'),
                    'saving' => __('Saving...', 'olama-school'),
                    'error' => __('Server Error', 'olama-school'),
                    'conflict' => __('Teacher double-booked!', 'olama-school'),
                    'copied' => __('Bulk copy successful', 'olama-school'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-academic' && isset($_GET['tab']) && $_GET['tab'] === 'assign_teachers') {
            wp_enqueue_script('olama-teacher-assignment-script', OLAMA_SCHOOL_URL . 'assets/js/teacher-assignment.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-teacher-assignment-script', 'olamaAssignment', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'curriculumNonce' => wp_create_nonce('olama_curriculum_nonce'),
                'i18n' => array(
                    'selectTeacher' => __('Please select a teacher first.', 'olama-school'),
                    'selectGrade' => __('Please select a grade first.', 'olama-school'),
                    'selectSection' => __('Please select a section first.', 'olama-school'),
                    'loading' => __('Loading...', 'olama-school'),
                    'saving' => __('Saving...', 'olama-school'),
                    'error' => __('An error occurred.', 'olama-school'),
                )
            ));
        }

        // Attendance AJAX Localization
        wp_localize_script('olama-admin-script', 'olama_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('olama_admin_nonce'),
        ));

        // Enqueue print stylesheet for weekly schedule
        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-plans' && isset($_GET['tab']) && $_GET['tab'] === 'schedule') {

            wp_enqueue_style('olama-schedule-print', OLAMA_SCHOOL_URL . 'assets/css/schedule-print.css', array(), OLAMA_SCHOOL_VERSION, 'print');
        }
    }


    /**
     * Render unified Academic Management page with tabs
     */
    public function render_academic_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calendar';

        $tabs = array(
            'calendar' => array('label' => __('Academic Calendar', 'olama-school'), 'cap' => 'olama_manage_academic_calendar'),
            'grades' => array('label' => __('Grades & Sections', 'olama-school'), 'cap' => 'olama_manage_academic_grades'),
            'subjects' => array('label' => __('Subjects', 'olama-school'), 'cap' => 'olama_manage_academic_subjects'),
            'assign_teachers' => array('label' => __('Assign Teachers to Subjects', 'olama-school'), 'cap' => 'olama_manage_academic_assignment'),
            'stationary' => array('label' => Olama_School_Helpers::translate('Stationary'), 'cap' => 'olama_manage_academic_stationary'),
            'office_hours' => array('label' => __('Office Hours', 'olama-school'), 'cap' => 'olama_manage_academic_office_hours'),
        );

        // Filter tabs by capability
        $allowed_tabs = array();
        foreach ($tabs as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php _e('Academic Management', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $id => $tab): ?>
                    <a href="?page=olama-school-academic&tab=<?php echo $id; ?>"
                        class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'calendar':
                        $this->render_academic_page_content();
                        break;
                    case 'grades':
                        $this->render_grades_page_content();
                        break;
                    case 'subjects':
                        $this->render_subjects_page_content();
                        break;
                    case 'assign_teachers':
                        $this->render_teacher_assignments_page_content();
                        break;
                    case 'stationary':
                        $this->render_stationary_page_content();
                        break;
                    case 'office_hours':
                        $this->render_teacher_office_hours_page_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render academic structure page content (Calendar)
     */
    /**
     * Handle Academic Calendar actions before output starts
     */
    public function handle_academic_calendar_actions()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'olama-school-academic') {
            return;
        }

        $selected_year_id = isset($_GET['manage_year']) ? intval($_GET['manage_year']) : 0;
        $base_url = admin_url('admin.php?page=olama-school-academic');
        if ($selected_year_id) {
            $base_url = add_query_arg('manage_year', $selected_year_id, $base_url);
        }

        // Handle Actions
        if (isset($_GET['action']) && isset($_GET['year_id'])) {
            $year_id = intval($_GET['year_id']);
            if ($_GET['action'] === 'activate' && check_admin_referer('olama_activate_year_' . $year_id)) {
                Olama_School_Academic::activate_year($year_id);
                wp_redirect(add_query_arg('olama_msg', 'year_activated', $base_url));
                exit;
            }
            if ($_GET['action'] === 'delete' && check_admin_referer('olama_delete_year_' . $year_id)) {
                $force = isset($_GET['force']) && $_GET['force'] === '1';
                $result = Olama_School_Academic::delete_year($year_id, $force);
                if (is_wp_error($result)) {
                    wp_redirect(add_query_arg(array(
                        'olama_msg' => 'error',
                        'olama_err' => urlencode($result->get_error_message()),
                        'olama_err_code' => 'year_dependency',
                        'err_id' => $year_id
                    ), $base_url));
                } else {
                    wp_redirect(add_query_arg('olama_msg', 'year_deleted', admin_url('admin.php?page=olama-school-academic')));
                }
                exit;
            }
        }

        // Handle Add Year
        if (isset($_POST['add_year']) && check_admin_referer('olama_add_year')) {
            Olama_School_Academic::add_year(array(
                'year_name' => sanitize_text_field($_POST['year_name']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['end_date']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ));
            wp_redirect(add_query_arg('olama_msg', 'year_added', $base_url));
            exit;
        }

        // Handle Update Year
        if (isset($_POST['update_year']) && check_admin_referer('olama_update_year')) {
            $year_id = intval($_POST['edit_year_id']);
            Olama_School_Academic::update_year($year_id, array(
                'year_name' => sanitize_text_field($_POST['edit_year_name']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['edit_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['edit_end_date']),
                'is_active' => isset($_POST['edit_is_active']) ? 1 : 0,
            ));
            wp_redirect(add_query_arg('olama_msg', 'year_updated', $base_url));
            exit;
        }

        // Handle Add Semester
        if (isset($_POST['add_semester']) && check_admin_referer('olama_add_semester')) {
            $result = Olama_School_Academic::add_semester(array(
                'academic_year_id' => intval($_POST['semester_year_id']),
                'semester_name' => sanitize_text_field($_POST['semester_name']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['sem_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['sem_end_date']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ));

            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode($result->get_error_message())), $base_url));
            } else {
                wp_redirect(add_query_arg('olama_msg', 'semester_added', $base_url));
            }
            exit;
        }

        // Handle Update Semester
        if (isset($_POST['update_semester']) && check_admin_referer('olama_update_semester')) {
            $sem_id = intval($_POST['edit_semester_id']);
            $result = Olama_School_Academic::update_semester($sem_id, array(
                'semester_name' => sanitize_text_field($_POST['edit_semester_name']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['edit_sem_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['edit_sem_end_date']),
                'is_active' => isset($_POST['edit_is_active']) ? 1 : 0,
            ));

            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode($result->get_error_message())), $base_url));
            } else {
                wp_redirect(add_query_arg('olama_msg', 'semester_updated', $base_url));
            }
            exit;
        }

        // Handle Delete Semester
        if (isset($_GET['action']) && isset($_GET['semester_id'])) {
            $sem_id = intval($_GET['semester_id']);
            if ($_GET['action'] === 'activate_semester' && check_admin_referer('olama_activate_semester_' . $sem_id)) {
                Olama_School_Academic::activate_semester($sem_id);
                wp_redirect(add_query_arg('olama_msg', 'semester_activated', $base_url));
                exit;
            }
            if ($_GET['action'] === 'delete_semester' && check_admin_referer('olama_delete_semester_' . $sem_id)) {
                $force = isset($_GET['force']) && $_GET['force'] === '1';
                $result = Olama_School_Academic::delete_semester($sem_id, $force);
                if (is_wp_error($result)) {
                    wp_redirect(add_query_arg(array(
                        'olama_msg' => 'error',
                        'olama_err' => urlencode($result->get_error_message()),
                        'olama_err_code' => 'semester_dependency',
                        'err_id' => $sem_id
                    ), $base_url));
                } else {
                    wp_redirect(add_query_arg('olama_msg', 'semester_deleted', $base_url));
                }
                exit;
            }
        }

        // Handle Add Event
        if (isset($_POST['add_event']) && check_admin_referer('olama_add_event')) {
            $result = Olama_School_Academic::add_event(array(
                'academic_year_id' => intval($_POST['event_year_id']),
                'event_description' => sanitize_textarea_field($_POST['event_description']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['event_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['event_end_date']),
            ));

            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode($result->get_error_message())), $base_url));
            } else {
                wp_redirect(add_query_arg('olama_msg', 'event_added', $base_url));
            }
            exit;
        }

        // Handle Update Event
        if (isset($_POST['update_event']) && check_admin_referer('olama_update_event')) {
            $event_id = intval($_POST['edit_event_id']);
            $result = Olama_School_Academic::update_event($event_id, array(
                'event_description' => sanitize_textarea_field($_POST['edit_event_description']),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['edit_event_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['edit_event_end_date']),
            ));

            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode($result->get_error_message())), $base_url));
            } else {
                wp_redirect(add_query_arg('olama_msg', 'event_updated', $base_url));
            }
            exit;
        }

        // Handle Delete Event
        if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['event_id'])) {
            $event_id = intval($_GET['event_id']);
            if (check_admin_referer('olama_delete_event_' . $event_id)) {
                Olama_School_Academic::delete_event($event_id);
                wp_redirect(add_query_arg('olama_msg', 'event_deleted', $base_url));
                exit;
            }
        }
        // Handle Add Semester Exam
        if (isset($_POST['add_semester_exam']) && check_admin_referer('olama_add_semester_exam')) {
            $result = Olama_School_Academic::add_semester_exam(array(
                'semester_id' => intval($_POST['exam_semester_id']),
                'grade_id' => !empty($_POST['exam_grade_id']) ? intval($_POST['exam_grade_id']) : null,
                'exam_name' => sanitize_text_field($_POST['exam_name']),
                'room_number' => sanitize_text_field($_POST['exam_room_number'] ?? ''),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['exam_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['exam_end_date']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ));

            if ($result) {
                wp_redirect(add_query_arg(array('olama_msg' => 'semester_exam_added', 'manage_semester' => intval($_POST['exam_semester_id'])), $base_url));
            } else {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode(__('Failed to add semester exam.', 'olama-school'))), $base_url));
            }
            exit;
        }

        // Handle Update Semester Exam
        if (isset($_POST['update_semester_exam']) && check_admin_referer('olama_update_semester_exam')) {
            $exam_id = intval($_POST['edit_exam_id']);
            $result = Olama_School_Academic::update_semester_exam($exam_id, array(
                'grade_id' => !empty($_POST['edit_grade_id']) ? intval($_POST['edit_grade_id']) : null,
                'exam_name' => sanitize_text_field($_POST['edit_exam_name']),
                'room_number' => sanitize_text_field($_POST['edit_room_number'] ?? ''),
                'start_date' => Olama_School_Helpers::sanitize_date($_POST['edit_exam_start_date']),
                'end_date' => Olama_School_Helpers::sanitize_date($_POST['edit_exam_end_date']),
                'is_active' => isset($_POST['edit_is_active']) ? 1 : 0,
            ));

            if ($result !== false) {
                wp_redirect(add_query_arg(array('olama_msg' => 'semester_exam_updated', 'manage_semester' => intval($_POST['manage_semester'])), $base_url));
            } else {
                wp_redirect(add_query_arg(array('olama_msg' => 'error', 'olama_err' => urlencode(__('Failed to update semester exam.', 'olama-school'))), $base_url));
            }
            exit;
        }

        // Handle Delete Semester Exam
        if (isset($_GET['action']) && $_GET['action'] === 'delete_semester_exam' && isset($_GET['exam_id'])) {
            $exam_id = intval($_GET['exam_id']);
            if (check_admin_referer('olama_delete_semester_exam_' . $exam_id)) {
                Olama_School_Academic::delete_semester_exam($exam_id);
                wp_redirect(add_query_arg(array('olama_msg' => 'semester_exam_deleted', 'manage_semester' => intval($_GET['manage_semester'])), $base_url));
                exit;
            }
        }

        // Handle Activate Semester Exam
        if (isset($_GET['action']) && $_GET['action'] === 'activate_semester_exam' && isset($_GET['exam_id'])) {
            $exam_id = intval($_GET['exam_id']);
            if (check_admin_referer('olama_activate_semester_exam_' . $exam_id)) {
                Olama_School_Academic::activate_semester_exam($exam_id);
                wp_redirect(add_query_arg(array('olama_msg' => 'semester_exam_activated', 'manage_semester' => intval($_GET['manage_semester'])), $base_url));
                exit;
            }
        }
    }

    /**
     * Render academic structure page content (Calendar)
     */
    public function render_academic_page_content()
    {
        // Display notices from query params
        if (isset($_GET['olama_msg'])) {
            $msg_type = 'updated';
            $message = '';

            switch ($_GET['olama_msg']) {
                case 'year_activated':
                    $message = __('Academic Year activated.', 'olama-school');
                    break;
                case 'year_deleted':
                    $message = __('Academic Year deleted.', 'olama-school');
                    break;
                case 'year_added':
                    $message = __('Academic Year added successfully.', 'olama-school');
                    break;
                case 'year_updated':
                    $message = __('Academic Year updated successfully.', 'olama-school');
                    break;
                case 'semester_added':
                    $message = __('Semester added successfully.', 'olama-school');
                    break;
                case 'semester_updated':
                    $message = __('Semester updated successfully.', 'olama-school');
                    break;
                case 'semester_deleted':
                    $message = __('Semester deleted.', 'olama-school');
                    break;
                case 'event_added':
                    $message = __('Event added successfully.', 'olama-school');
                    break;
                case 'event_updated':
                    $message = __('Event updated successfully.', 'olama-school');
                    break;
                case 'event_deleted':
                    $message = __('Event deleted.', 'olama-school');
                    break;
                case 'semester_exam_added':
                    $message = __('Semester exam added successfully.', 'olama-school');
                    break;
                case 'semester_exam_updated':
                    $message = __('Semester exam updated successfully.', 'olama-school');
                    break;
                case 'semester_exam_deleted':
                    $message = __('Semester exam deleted.', 'olama-school');
                    break;
                case 'semester_exam_activated':
                    $message = __('Semester exam activated.', 'olama-school');
                    break;
                case 'error':
                    $msg_type = 'error';
                    $message = isset($_GET['olama_err']) ? urldecode($_GET['olama_err']) : __('An error occurred.', 'olama-school');

                    // Check for specific error codes to add force delete links
                    if (isset($_GET['olama_err_code']) && isset($_GET['err_id'])) {
                        $err_id = intval($_GET['err_id']);
                        $base_url = admin_url('admin.php?page=olama-school-academic');
                        if (isset($_GET['manage_year'])) {
                            $base_url = add_query_arg('manage_year', intval($_GET['manage_year']), $base_url);
                        }

                        if ($_GET['olama_err_code'] === 'year_dependency') {
                            $force_url = wp_nonce_url(add_query_arg(array('action' => 'delete', 'year_id' => $err_id, 'force' => 1), $base_url), 'olama_delete_year_' . $err_id);
                            $message .= ' <a href="' . $force_url . '" onclick="return confirm(\'' . esc_js(__('WARNING: This will permanently delete ALL data associated with this year. Are you sure?', 'olama-school')) . '\')">' . __('Force Delete Everything', 'olama-school') . '</a>';
                        } elseif ($_GET['olama_err_code'] === 'semester_dependency') {
                            $force_url = wp_nonce_url(add_query_arg(array('action' => 'delete_semester', 'semester_id' => $err_id, 'force' => 1), $base_url), 'olama_delete_semester_' . $err_id);
                            $message .= ' <a href="' . $force_url . '" onclick="return confirm(\'' . esc_js(__('WARNING: This will permanently delete ALL data associated with this semester. Are you sure?', 'olama-school')) . '\')">' . __('Force Delete Everything', 'olama-school') . '</a>';
                        }
                    }
                    break;
            }

            if ($message) {
                $notice_class = ($msg_type === 'error') ? 'notice notice-error' : 'notice notice-success';
                // Allow HTML in error messages for the "Force Delete" link
                echo '<div class="' . $notice_class . ' is-dismissible"><p>' . Olama_School_Helpers::translate($message) . '</p></div>';
            }
        }

        $selected_year_id = isset($_GET['manage_year']) ? intval($_GET['manage_year']) : 0;
        $years = Olama_School_Academic::get_years();
        $all_grades = Olama_School_Grade::get_grades();
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-calendar.php';
    }

    /**
     * Render grades and sections page content
     */
    public function render_grades_page_content()
    {
        // Handle Grade submission
        if (isset($_POST['add_grade']) && check_admin_referer('olama_add_grade')) {
            $result = Olama_School_Grade::add_grade(array(
                'grade_name' => sanitize_text_field($_POST['grade_name']),
                'grade_level' => intval($_POST['grade_level']),
                'periods_count' => intval($_POST['periods_count']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Grade added successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Grade Update
        if (isset($_POST['edit_grade']) && check_admin_referer('olama_edit_grade')) {
            $grade_id = intval($_POST['grade_id']);
            $result = Olama_School_Grade::update_grade($grade_id, array(
                'grade_name' => sanitize_text_field($_POST['grade_name']),
                'grade_level' => intval($_POST['grade_level']),
                'periods_count' => intval($_POST['periods_count']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Grade updated successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Grade Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_grade' && isset($_GET['grade_id'])) {
            $grade_id = intval($_GET['grade_id']);
            if (check_admin_referer('olama_delete_grade_' . $grade_id)) {
                $result = Olama_School_Grade::delete_grade($grade_id);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>' . __('Grade deleted.', 'olama-school') . '</p></div>';
                }
            }
        }

        // Handle Clear All Grades & Sections
        if (isset($_GET['action']) && $_GET['action'] === 'clear_all_grades') {
            if (check_admin_referer('olama_clear_all_grades')) {
                global $wpdb;

                // Get all grades
                $all_grades = Olama_School_Grade::get_grades();

                // Check if any grade has linked data
                $has_linked_data = false;
                foreach ($all_grades as $grade) {
                    $tables_to_check = array(
                        'olama_sections' => 'sections',
                        'olama_students' => 'students',
                        'olama_subjects' => 'subjects',
                        'olama_curriculum_units' => 'curriculum',
                    );

                    foreach ($tables_to_check as $table => $label) {
                        $count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE grade_id = %d",
                            $grade->id
                        ));
                        if ($count > 0) {
                            $has_linked_data = true;
                            break 2;
                        }
                    }
                }

                if ($has_linked_data) {
                    echo '<div class="error"><p>' . Olama_School_Helpers::translate('Cannot delete grades because some grades have linked data (sections, students, subjects, or curriculum). Please delete dependent data first.') . '</p></div>';
                } else {
                    // Safe to delete all grades
                    foreach ($all_grades as $grade) {
                        $wpdb->delete("{$wpdb->prefix}olama_grades", array('id' => $grade->id));
                    }
                    echo '<div class="updated"><p>' . Olama_School_Helpers::translate('All grades and sections cleared successfully!') . '</p></div>';
                }
            }
        }

        // Handle Section submission
        if (isset($_POST['add_section']) && check_admin_referer('olama_add_section')) {
            $active_year = Olama_School_Academic::get_active_year();
            $result = Olama_School_Section::add_section(array(
                'academic_year_id' => $active_year ? $active_year->id : 0,
                'grade_id' => intval($_POST['grade_id']),
                'section_name' => sanitize_text_field($_POST['section_name']),
                'room_number' => sanitize_text_field($_POST['room_number']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Section added successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Section Update
        if (isset($_POST['edit_section']) && check_admin_referer('olama_edit_section')) {
            $section_id = intval($_POST['section_id']);
            $active_year = Olama_School_Academic::get_active_year();
            $result = Olama_School_Section::update_section($section_id, array(
                'academic_year_id' => $active_year ? $active_year->id : 0,
                'grade_id' => intval($_POST['grade_id']),
                'section_name' => sanitize_text_field($_POST['section_name']),
                'room_number' => sanitize_text_field($_POST['room_number']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Section updated successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Section Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_section' && isset($_GET['section_id'])) {
            $section_id = intval($_GET['section_id']);
            if (check_admin_referer('olama_delete_section_' . $section_id)) {
                $result = Olama_School_Section::delete_section($section_id);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>' . __('Section deleted.', 'olama-school') . '</p></div>';
                }
            }
        }

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['manage_grade']) ? intval($_GET['manage_grade']) : 0;
        $selected_grade = null;
        if ($selected_grade_id) {
            $selected_grade = Olama_School_Grade::get_grade($selected_grade_id);
        }

        // Display import messages
        if ($import_message = get_transient('olama_import_message')) {
            echo '<div class="updated"><p>' . esc_html($import_message) . '</p></div>';
            delete_transient('olama_import_message');
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-grades.php';
    }

    /**
     * Handle Subject Actions (Add, Edit, Delete, Clear)
     */
    public function handle_subject_actions()
    {
        // Handle Subject submission (Add/Edit)
        if (isset($_POST['subject_action_type'])) {
            // Verify nonce
            if (!wp_verify_nonce($_POST['_wpnonce'], 'olama_subject_action')) {
                wp_die(__('Security check failed. Please try again.', 'olama-school'));
            } else {
                $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
                $data = array(
                    'subject_name' => sanitize_text_field($_POST['subject_name']),
                    'subject_code' => sanitize_text_field($_POST['subject_code']),
                    'grade_id' => intval($_POST['grade_id']),
                    'color_code' => sanitize_hex_color($_POST['color_code']),
                    'is_active' => $is_active,
                );

                $success = false;
                if ($_POST['subject_action_type'] === 'edit') {
                    $subject_id = intval($_POST['subject_id']);
                    $result = Olama_School_Subject::update_subject($subject_id, $data);
                    if ($result !== false) {
                        set_transient('olama_subject_msg', __('Subject updated successfully.', 'olama-school'), 30);
                        $success = true;
                    }
                } else {
                    $result = Olama_School_Subject::add_subject($data);
                    if ($result !== false) {
                        set_transient('olama_subject_msg', __('Subject added successfully.', 'olama-school'), 30);
                        $success = true;
                    }
                }

                if ($success) {
                    wp_safe_redirect(admin_url('admin.php?page=olama-school-academic&tab=subjects'));
                    exit;
                } else {
                    set_transient('olama_subject_error', __('Failed to save subject. Please check for errors.', 'olama-school'), 30);
                }
            }
        }

        // Handle Subject delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_subject' && isset($_GET['subject_id'])) {
            $subject_id = intval($_GET['subject_id']);
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'olama_delete_subject_' . $subject_id)) {
                Olama_School_Subject::delete_subject($subject_id);
                set_transient('olama_subject_msg', __('Subject deleted.', 'olama-school'), 30);
                wp_safe_redirect(admin_url('admin.php?page=olama-school-academic&tab=subjects'));
                exit;
            }
        }

        // Handle Clear All Subjects
        if (isset($_GET['action']) && $_GET['action'] === 'clear_all_subjects') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'olama_clear_all_subjects')) {
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->prefix}olama_subjects");
                set_transient('olama_subject_msg', Olama_School_Helpers::translate('All subjects cleared successfully!'), 30);
                wp_safe_redirect(admin_url('admin.php?page=olama-school-academic&tab=subjects'));
                exit;
            }
        }
    }

    public function render_subjects_page_content()
    {
        // Display transient messages
        if ($msg = get_transient('olama_subject_msg')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            delete_transient('olama_subject_msg');
        }

        if ($error = get_transient('olama_subject_error')) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            delete_transient('olama_subject_error');
        }

        $grades = Olama_School_Grade::get_grades();
        $subjects = Olama_School_Subject::get_subjects();

        // Group subjects by grade
        $grouped_subjects = array();
        foreach ($subjects as $subject) {
            $grouped_subjects[$subject->grade_name][] = $subject;
        }

        // Handle Edit Mode
        $edit_subject = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit_subject' && isset($_GET['subject_id'])) {
            $edit_subject = Olama_School_Subject::get_subject(intval($_GET['subject_id']));
        }

        // Display import messages
        if ($import_message = get_transient('olama_import_message')) {
            echo '<div class="updated"><p>' . esc_html($import_message) . '</p></div>';
            delete_transient('olama_import_message');
        }
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-subjects.php';
    }

    /**
     * Render Exam Schedule Tab Content
     */
    public function render_exam_schedule_content()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();

        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $subjects = $selected_grade_id ? Olama_School_Subject::get_subjects_by_grade($selected_grade_id) : array();
        $selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
        $active_exam = Olama_School_Academic::get_active_exam($selected_semester_id);
        $selected_semester_exam_id = ($active_exam ? $active_exam->id : 0);

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/exam-schedule.php';
    }

    /**
     * Render unified Users page
     */
    public function render_users_page()
    {
        // Handle Student Registration
        if (isset($_POST['register_student']) && check_admin_referer('olama_register_student')) {
            $student_id = Olama_School_Student::register_student(array(
                'student_name' => sanitize_text_field($_POST['student_name']),
                'student_uid' => sanitize_text_field($_POST['student_id_number']),
                'family_id' => sanitize_text_field($_POST['family_id'] ?? ''),
            ));
            if ($student_id) {
                echo '<div class="updated"><p>' . __('Student registered successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Student Update
        if (isset($_POST['update_student']) && check_admin_referer('olama_update_student')) {
            $result = Olama_School_Student::update_student(intval($_POST['student_id']), array(
                'student_name' => sanitize_text_field($_POST['student_name']),
                'student_uid' => sanitize_text_field($_POST['student_id_number']),
                'family_id' => sanitize_text_field($_POST['family_id'] ?? ''),
            ));
            if ($result) {
                echo '<div class="updated"><p>' . __('Student information updated.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Student Deletion
        if (isset($_POST['delete_student']) && check_admin_referer('olama_delete_student')) {
            $result = Olama_School_Student::delete_student(intval($_POST['student_id']));
            if ($result) {
                echo '<div class="updated"><p>' . __('Student and all enrollments deleted.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Delete All Students
        if (isset($_POST['delete_all_students']) && check_admin_referer('olama_delete_all_students')) {
            if (!Olama_School_Permissions::can('olama_manage_users_students')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $result = Olama_School_Student::delete_all_students();
            if ($result !== false) {
                echo '<div class="updated"><p>' . __('All students and enrollments deleted successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Student Unenrollment
        if (isset($_POST['unenroll_student']) && check_admin_referer('olama_unenroll_student')) {
            $result = Olama_School_Student::unenroll_student(
                intval($_POST['student_id']),
                intval($_POST['academic_year_id'] ?? 0)
            );
            if ($result) {
                echo '<div class="updated"><p>' . __('Student unenrolled successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Student Enrollment
        if (isset($_POST['enroll_student']) && check_admin_referer('olama_enroll_student')) {
            $result = Olama_School_Student::enroll_student(
                intval($_POST['student_id']),
                intval($_POST['section_id']),
                intval($_POST['academic_year_id'])
            );
            if ($result) {
                echo '<div class="updated"><p>' . __('Student enrolled successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Teacher update
        if (isset($_POST['update_teacher']) && check_admin_referer('olama_update_teacher')) {
            Olama_School_Teacher::update_teacher(intval($_POST['teacher_id']), array(
                'employee_id' => sanitize_text_field($_POST['employee_id']),
                'phone_number' => sanitize_text_field($_POST['phone_number']),
            ));
            echo '<div class="updated"><p>' . __('Teacher information updated.', 'olama-school') . '</p></div>';
        }

        // Handle Family Save
        if (isset($_POST['save_family']) && check_admin_referer('olama_save_family')) {
            $family_id = Olama_School_Family::save_family(array(
                'id' => isset($_POST['family_db_id']) ? intval($_POST['family_db_id']) : 0,
                'family_uid' => sanitize_text_field($_POST['family_uid']),
                'family_name' => sanitize_text_field($_POST['family_name']),
                'mother_mobile' => sanitize_text_field($_POST['mother_mobile']),
                'father_mobile' => sanitize_text_field($_POST['father_mobile']),
                'address' => sanitize_textarea_field($_POST['address']),
            ));

            if (is_wp_error($family_id)) {
                echo '<div class="error"><p>' . $family_id->get_error_message() . '</p></div>';
            } elseif ($family_id) {
                // Process Students if provided
                if (isset($_POST['students']) && is_array($_POST['students'])) {
                    $family_uid = sanitize_text_field($_POST['family_uid']);
                    foreach ($_POST['students'] as $stu_data) {
                        if (empty($stu_data['name']))
                            continue;

                        $student_payload = array(
                            'student_name' => sanitize_text_field($stu_data['name']),
                            'student_uid' => sanitize_text_field($stu_data['uid']),
                            'family_id' => $family_uid,
                            'dob' => sanitize_text_field($stu_data['dob']),
                            'national_id' => sanitize_text_field($stu_data['national_id']),
                            'gender' => sanitize_text_field($stu_data['gender']),
                        );

                        if (!empty($stu_data['db_id'])) {
                            Olama_School_Student::update_student(intval($stu_data['db_id']), $student_payload);
                        } else {
                            Olama_School_Student::register_student($student_payload);
                        }
                    }
                }
                echo '<div class="updated"><p>' . __('Family and students saved successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Family Deletion
        if (isset($_POST['delete_family']) && check_admin_referer('olama_delete_family')) {
            $result = Olama_School_Family::delete_family(intval($_POST['family_id']));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . $result->get_error_message() . '</p></div>';
            } elseif ($result) {
                echo '<div class="updated"><p>' . __('Family deleted successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Delete All Families
        if (isset($_POST['delete_all_families']) && check_admin_referer('olama_delete_all_families')) {
            if (!Olama_School_Permissions::can('olama_manage_users_families')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $result = Olama_School_Family::delete_all_families();
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . $result->get_error_message() . '</p></div>';
            } elseif ($result !== false) {
                echo '<div class="updated"><p>' . __('All families deleted successfully.', 'olama-school') . '</p></div>';
            }
        }

        $tabs_config = array(
            'families' => array('cap' => 'olama_manage_users_families'),
            'students' => array('cap' => 'olama_manage_users_students'),
            'teachers' => array('cap' => 'olama_manage_users_teachers'),
            'permissions' => array('cap' => 'olama_manage_users_permissions'),
            'logs' => array('cap' => 'olama_manage_users_logs'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : array_key_first($allowed_tabs);

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        // Only load data for the active tab to improve performance
        $grades = array();
        $sections = array();
        $students = array();
        $teachers = array();
        $families = array();
        $academic_years = array();

        if ($active_tab === 'families') {
            $families = Olama_School_Family::get_families();
        } elseif ($active_tab === 'students') {
            $grades = Olama_School_Grade::get_grades();
            $sections = Olama_School_Section::get_sections();
            $academic_years = class_exists('Olama_School_Academic') ? Olama_School_Academic::get_years() : array();
            $families = Olama_School_Family::get_families(); // Needed for student registration/edit

            // Fetch registry (year_id = 0)
            $students = Olama_School_Student::get_students(array('academic_year_id' => 0));
        } elseif ($active_tab === 'teachers') {
            $teachers = Olama_School_Teacher::get_teachers();
        }
        // Permissions and logs tabs don't need user data

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/admin-users.php';
    }

    /**
     * Render Transportation Management page
     */
    public function render_transport_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'buses';

        $allowed_tabs = array(
            'buses' => Olama_School_Helpers::translate('Buses'),
            'assignments' => Olama_School_Helpers::translate('Student Assignments'),
        );

        if (!Olama_School_Permissions::can('olama_access_transport_mgmt')) {
            wp_die(__('Unauthorized access', 'olama-school'));
        }

        if ($active_tab === 'buses') {
            $buses = Olama_School_Bus::get_buses();
            $drivers = Olama_School_Bus::get_available_drivers();
            $companions = Olama_School_Bus::get_available_companions();
        } elseif ($active_tab === 'assignments') {
            $buses = Olama_School_Bus::get_buses();
            $years = Olama_School_Academic::get_years();
            $active_year = Olama_School_Academic::get_active_year();
            $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
            $selected_bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : 0;
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/admin-transport-buses.php';
    }

    /**
     * Render unified Curriculum Management page with tabs
     */
    public function render_curriculum_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'curriculum';

        $tabs_config = array(
            'curriculum' => array('label' => Olama_School_Helpers::translate('Curriculum'), 'cap' => 'olama_manage_curriculum_list'),
            'timeline' => array(
                'label' => Olama_School_Helpers::translate('Timeline'),
                'cap' => array('olama_manage_curriculum_timeline', 'olama_view_curriculum_timeline')
            ),
            'bulk_upload' => array('label' => Olama_School_Helpers::translate('Bulk Upload'), 'cap' => 'olama_manage_curriculum_upload'),
            'analysis' => array('label' => Olama_School_Helpers::translate('Analysis'), 'cap' => 'olama_manage_curriculum_analysis'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            $caps = (array) $tab['cap'];
            $has_access = false;
            foreach ($caps as $cap) {
                if (Olama_School_Permissions::can($cap)) {
                    $has_access = true;
                    break;
                }
            }
            if ($has_access) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        // Validate and sanitize base navigation parameters
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
        if (!$selected_year_id && class_exists('Olama_School_Academic')) {
            $active_year = Olama_School_Academic::get_active_year();
            $selected_year_id = $active_year ? $active_year->id : 0;
        }

        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
        if ($selected_year_id && $selected_semester_id) {
            $semesters = Olama_School_Academic::get_semesters($selected_year_id);
            $valid_sem = false;
            foreach ($semesters as $s) {
                if ($s->id == $selected_semester_id) {
                    $valid_sem = true;
                    break;
                }
            }
            if (!$valid_sem) {
                $selected_semester_id = 0;
            }
        }

        $base_params = array(
            'academic_year_id' => $selected_year_id,
            'semester_id' => $selected_semester_id,
            'grade_id' => isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0,
        );

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php echo Olama_School_Helpers::translate('Curriculum Management'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $tab_slug => $tab_data): ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge(array('page' => 'olama-school-curriculum', 'tab' => $tab_slug), array_filter($base_params)), admin_url('admin.php'))); ?>"
                        class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'timeline':
                        $this->render_timeline_page_content();
                        break;
                    case 'bulk_upload':
                        $this->render_bulk_upload_page_content();
                        break;
                    case 'analysis':
                        $this->render_curriculum_analysis_page_content();
                        break;
                    case 'curriculum':
                        $this->render_curriculum_page_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render unified Exam Management page with tabs
     */
    public function render_exam_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'exam_schedule';

        $tabs_config = array(
            'exam_schedule' => array('label' => Olama_School_Helpers::translate('Exam Schedule'), 'cap' => 'olama_manage_exams_schedule'),
            'teacher_exams' => array('label' => Olama_School_Helpers::translate('Teacher Exams'), 'cap' => 'olama_fill_exam_details'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php echo Olama_School_Helpers::translate('Exam Management'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $id => $tab): ?>
                    <a href="?page=olama-school-exams&tab=<?php echo $id; ?>"
                        class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'exam_schedule':
                        $this->render_exam_schedule_content();
                        break;
                    case 'teacher_exams':
                        $this->render_teacher_exams_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Evaluation page with tabs
     */
    public function render_evaluation_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'student_evaluation';

        $tabs_config = array(
            'student_evaluation' => array('label' => Olama_School_Helpers::translate('Student Evaluation'), 'cap' => 'olama_manage_evaluation_students'),
            'evaluation_progress' => array('label' => Olama_School_Helpers::translate('Evaluation Progress'), 'cap' => 'olama_manage_evaluation_students'),
            'evaluation_mgmt' => array('label' => Olama_School_Helpers::translate('Evaluation Management'), 'cap' => 'olama_manage_evaluation_mgmt'),
            'lesson_planner' => array('label' => Olama_School_Helpers::translate('Lesson Planner'), 'cap' => 'olama_manage_lesson_planner'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php echo Olama_School_Helpers::translate('Evaluation'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $tab_slug => $tab_data): ?>
                    <a href="?page=olama-school-evaluation&tab=<?php echo $tab_slug; ?>"
                        class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'evaluation_mgmt':
                        $this->render_evaluation_mgmt_page_content();
                        break;
                    case 'evaluation_progress':
                        $this->render_evaluation_progress_page_content();
                        break;
                    case 'student_evaluation':
                        $this->render_student_evaluation_page_content();
                        break;
                    case 'lesson_planner':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/lesson-planner.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Academic Supervision page with tabs
     */
    public function render_academic_supervision_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'plan_visit';

        $tabs_config = array(
            'plan_visit' => array('label' => __('Plan Visit', 'olama-school'), 'cap' => 'olama_manage_supervision_plan'),
            'complete_plan' => array('label' => __('Complete Plan', 'olama-school'), 'cap' => 'olama_manage_supervision_plan'),
            'assignments' => array('label' => __('Assign Supervisor', 'olama-school'), 'cap' => 'olama_manage_supervision_plan'),
            'reports' => array('label' => __('Reports', 'olama-school'), 'cap' => 'olama_view_supervision_reports'),
            'analytics' => array('label' => __('Analytics', 'olama-school'), 'cap' => 'olama_view_supervision_analytics'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php _e('Academic Supervision', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $tab_slug => $tab_data): ?>
                    <a href="?page=olama-school-supervision&tab=<?php echo $tab_slug; ?>"
                        class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'plan_visit':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/supervision-plan.php';
                        break;
                    case 'complete_plan':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/supervision-complete-plan.php';
                        break;
                    case 'assignments':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/supervision-assignments.php';
                        break;
                    case 'reports':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/supervision-reports.php';
                        break;
                    case 'analytics':
                        include OLAMA_SCHOOL_PATH . 'includes/admin-views/supervision-analytics.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Evaluation Progress Tab Content
     */
    public function render_evaluation_progress_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/ev-progress.php';
    }

    /**
     * AJAX: Get students for evaluation progress (Full list with statuses)
     */
    public function ajax_get_ev_progress_students()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
        $section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

        if (!$template_id || !$section_id) {
            wp_die('Invalid parameters.');
        }

        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        if (!$template)
            wp_die('Template not found.');

        // Get all students in section
        $students = Olama_School_Student::get_students(array(
            'academic_year_id' => $template->academic_year_id,
            'section_id' => $section_id
        ));

        if (empty($students)) {
            echo '<p style="text-align:center; padding:20px;">' . Olama_School_Helpers::translate('No students found in this section.') . '</p>';
            wp_die();
        }

        // Get statuses and record IDs
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id as student_id, r.status, r.id as record_id
             FROM {$wpdb->prefix}olama_ev_records r
             JOIN {$wpdb->prefix}olama_students s ON r.student_uid = s.student_uid
             WHERE r.academic_year_id = %d AND r.semester_id = %d AND r.template_id = %d",
            $template->academic_year_id,
            $template->semester_id,
            $template_id
        ));

        $statuses = array();
        $statuses_ids = array();
        foreach ($results as $row) {
            $statuses[$row->student_id] = $row->status;
            $statuses_ids[$row->student_id] = $row->record_id;
        }

        foreach ($students as $student) {
            $status = $statuses[$student->id] ?? 'none';
            $status_label = Olama_School_Helpers::translate(ucfirst($status ?: 'none'));

            $status_class = $status === 'published' ? 'published' : ($status === 'draft' ? 'draft' : 'none');
            echo '<div class="ev-student-item" data-student-id="' . $student->id . '" data-template-id="' . $template_id . '">';
            echo '<div class="ev-student-info">';
            echo '<span class="ev-student-name">' . esc_html($student->student_name) . '</span>';
            echo '<span class="ev-student-uid">' . esc_html($student->student_uid) . '</span> ';
            echo '<span class="ev-status-badge status-' . $status_class . '">' . $status_label . '</span>';
            echo '</div>';

            echo '<div class="ev-student-actions" style="display:flex; gap:5px;">';
            if ($status === 'published') {
                echo '<a href="' . admin_url('admin.php?action=ev_print_report&evaluation_id=' . ($statuses_ids[$student->id] ?? 0)) . '" target="_blank" class="button button-small v-publish-btn" title="' . Olama_School_Helpers::translate('Publish') . '">';
                echo '<span class="dashicons dashicons-visibility" style="font-size:16px; width:16px; height:16px; margin-top:3px;"></span>';
                echo '</a>';
            } elseif ($status === 'draft') {
                echo '<button type="button" class="button button-small button-primary ev-approve-btn" data-student-id="' . $student->id . '" data-template-id="' . $template_id . '">';
                echo Olama_School_Helpers::translate('Approve');
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';
        }
        wp_die();
    }

    /**
     * AJAX: Get evaluation content for a student
     */
    public function ajax_get_student_evaluation()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $student_id = intval($_GET['student_id']);
        $template_id = intval($_GET['template_id']);

        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));

        if (!$template)
            wp_die('Template not found');

        $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

        if (!$record) {
            echo '<div style="text-align:center; padding:50px; color:#94a3b8;">' . Olama_School_Helpers::translate('No evaluation record found for this student.') . '</div>';
            wp_die();
        }

        // Display evaluation details matching ev-form.php design
        $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($template_id);
        $scores = Olama_School_EV_Record::get_scores($record->id);
        $score_config = Olama_School_EV_Template::get_score_config($template_id);

        echo '<div class="ev-review-wrapper" style="direction: ' . (Olama_School_Helpers::is_arabic() ? 'rtl' : 'ltr') . ';">';
        echo '<div style="margin-bottom:30px; border-bottom:1px solid #e2e8f0; padding-bottom:20px; display: flex; justify-content: space-between; align-items: center;">';
        echo '<div>';
        echo '<h2 style="margin:0; font-size: 1.5em; color: #1e293b;">' . esc_html($template->template_name) . '</h2>';
        echo '<div style="margin-top:10px;"><span class="ev-status-badge status-' . $record->status . '">' . Olama_School_Helpers::translate(ucfirst($record->status)) . '</span></div>';
        echo '</div>';

        if ($record->status !== 'published') {
            echo '<div>';
            echo '<button type="button" class="button button-large button-primary ev-approve-btn" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
            echo '<span class="dashicons dashicons-yes" style="margin-top: 4px; margin-right: 5px;"></span> ';
            echo Olama_School_Helpers::translate('Approve');
            echo '</button>';
            echo '</div>';
        }
        echo '</div>';

        foreach ($curriculum as $domain) {
            echo '<div class="ev-domain-section" style="margin-bottom: 40px;">';
            echo '<div style="background: #1e293b; color: #fff; padding: 12px 25px; border-radius: 8px; margin-bottom: 20px;">';
            echo '<h3 style="margin: 0; color: #fff !important;">' . esc_html($domain->title_ar) . '</h3>';
            echo '</div>';

            foreach ($domain->categories as $category) {
                echo '<div class="ev-category-container" style="margin-bottom: 25px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">';
                echo '<div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0;">';
                echo '<h4 style="margin: 0; color: #475569; font-weight: 600;">' . esc_html($category->title_ar) . '</h4>';
                echo '</div>';

                echo '<table style="width: 100%; border-collapse: collapse;">';
                foreach ($category->indicators as $indicator) {
                    $score_row = $scores[$indicator->id] ?? null;
                    $score_val = $score_row ? $score_row->score : null;
                    $notes = $score_row ? $score_row->notes : '';

                    echo '<tr style="border-bottom: 1px solid #f1f5f9;">';
                    echo '<td style="padding: 15px 20px; width: 40%; font-size: 1.05em; vertical-align: middle;">' . esc_html($indicator->indicator_text) . '</td>';
                    echo '<td style="padding: 15px 20px; vertical-align: middle;">';

                    echo '<div class="ev-scoring-grid" style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: nowrap; align-items: center;">';

                    $total_levels = count($score_config);
                    $i = 0;
                    foreach ($score_config as $val => $label) {
                        $i++;
                        $is_active = ($score_val == $val);

                        // Dynamic color assignment matching ev-form.php
                        $color_class = 'not-mastered';
                        if ($i === 1)
                            $color_class = 'mastered';
                        elseif ($i === $total_levels)
                            $color_class = 'not-mastered';
                        elseif ($i === 2 && $total_levels > 2)
                            $color_class = 'partial';

                        echo '<div class="ev-score-option ' . $color_class . ' ' . ($is_active ? 'active' : '') . '" style="opacity: ' . ($score_val !== null && !$is_active ? '0.4' : '1') . ';">';
                        echo '<span class="ev-circle"></span>';
                        echo '<span class="ev-label">' . esc_html(Olama_School_Helpers::translate($label)) . '</span>';
                        echo '</div>';
                    }
                    echo '</div>';

                    if ($notes) {
                        echo '<div style="font-size:12px; color:#64748b; margin-top:10px; padding: 8px; background: #f8fafc; border-radius: 4px; border-right: 3px solid #6366f1;">';
                        echo '<strong>' . Olama_School_Helpers::translate('Note') . ':</strong> ' . esc_html($notes);
                        echo '</div>';
                    }

                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
            }
            echo '</div>';
        }

        // Supervisor Comments Block
        echo '<div class="ev-supervisor-comments-block" style="margin-top: 40px; padding: 25px; background: #fdf2f2; border: 1px solid #fecaca; border-radius: 12px; border-right: 5px solid #ef4444;">';
        echo '<h3 style="margin: 0 0 15px 0; color: #991b1b; font-size: 1.25em;">' . Olama_School_Helpers::translate('Supervisor Comments') . '</h3>';
        echo '<textarea id="ev-supervisor-comments-text" style="width: 100%; min-height: 120px; border-radius: 8px; border: 1px solid #fecaca; padding: 15px; margin-bottom: 20px;" placeholder="' . Olama_School_Helpers::translate('Write comments for the teacher...') . '">' . esc_textarea($record->supervisor_comments ?? '') . '</textarea>';

        echo '<div style="display: flex; gap: 10px; justify-content: flex-end;">';
        echo '<button type="button" class="button button-large ev-save-comments-btn" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
        echo Olama_School_Helpers::translate('Save Comments');
        echo '</button>';

        if ($record->status !== 'published') {
            echo '<button type="button" class="button button-large button-primary ev-approve-btn" style="background: #ef4444; border-color: #ef4444;" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
            echo Olama_School_Helpers::translate('Approve');
            echo '</button>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>'; // End ev-review-wrapper

        wp_die();
    }

    /**
     * AJAX: Approve evaluation
     */
    public function ajax_approve_evaluation()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
            wp_send_json_error('Unauthorized');
        }

        $student_id = intval($_POST['student_id']);
        $template_id = intval($_POST['template_id']);

        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        if (!$template)
            wp_send_json_error('Template not found');

        $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

        if ($record) {
            $update_data = array('status' => 'published');
            if (isset($_POST['supervisor_comments'])) {
                $update_data['supervisor_comments'] = sanitize_textarea_field($_POST['supervisor_comments']);
            }

            $wpdb->update(
                "{$wpdb->prefix}olama_ev_records",
                $update_data,
                array('id' => $record->id)
            );
            wp_send_json_success();
        } else {
            wp_send_json_error('Record not found');
        }
    }

    /**
     * AJAX: Save supervisor comments
     */
    public function ajax_save_supervisor_comments()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
            wp_send_json_error('Unauthorized');
        }

        $student_id = intval($_POST['student_id']);
        $template_id = intval($_POST['template_id']);
        $comments = sanitize_textarea_field($_POST['supervisor_comments']);

        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        if (!$template)
            wp_send_json_error('Template not found');

        $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

        if ($record) {
            $wpdb->update(
                "{$wpdb->prefix}olama_ev_records",
                array('supervisor_comments' => $comments),
                array('id' => $record->id)
            );
            wp_send_json_success();
        } else {
            wp_send_json_error('Record not found');
        }
    }

    /**
     * AJAX: Bulk approve evaluations
     */
    public function ajax_bulk_approve_evaluations()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
            wp_send_json_error('Unauthorized');
        }

        $template_id = intval($_POST['template_id']);
        $section_id = intval($_POST['section_id']);

        global $wpdb;
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        if (!$template)
            wp_send_json_error('Template not found');

        // Get all draft evaluation records for this template and section
        $students = Olama_School_Student::get_students(array(
            'academic_year_id' => $template->academic_year_id,
            'section_id' => $section_id
        ));

        if (empty($students)) {
            wp_send_json_error('No students found');
        }

        $student_uids = wp_list_pluck($students, 'student_uid');
        $placeholders = implode(',', array_fill(0, count($student_uids), '%s'));

        $query = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}olama_ev_records 
             SET status = 'published' 
             WHERE template_id = %d AND academic_year_id = %d AND semester_id = %d 
             AND status = 'draft' AND student_uid IN ($placeholders)",
            array_merge(array($template_id, $template->academic_year_id, $template->semester_id), $student_uids)
        );

        $result = $wpdb->query($query);
        wp_send_json_success(array('count' => $result));
    }

    /**
     * Render Teacher Exams Tab Content
     */
    public function render_teacher_exams_content()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));

        $active_exam = Olama_School_Academic::get_active_exam($selected_semester_id);
        $selected_exam_id = ($active_exam ? $active_exam->id : 0);

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/teacher-exams.php';
    }

    /**
     * AJAX: Bulk Add Exam Subjects
     */
    public function ajax_bulk_add_exam_subjects()
    {
        global $wpdb;
        check_ajax_referer('olama_save_exam', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_exams_schedule')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $year_id = intval($_POST['academic_year_id']);
        $semester_id = intval($_POST['semester_id']);
        $exam_id = intval($_POST['semester_exam_id']);
        $grade_id = intval($_POST['grade_id']);
        if (!$year_id || !$semester_id || !$exam_id || !$grade_id) {
            wp_send_json_error(__('Missing parameters', 'olama-school'));
        }

        $exam_meta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semester_exams WHERE id = %d", $exam_id));
        $exam_name = $exam_meta ? $exam_meta->exam_name : '';

        $subjects = Olama_School_Subject::get_subjects_by_grade($grade_id);
        $added = 0;

        foreach ($subjects as $subject) {
            // Check if already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_exams WHERE semester_exam_id = %d AND subject_id = %d",
                $exam_id,
                $subject->id
            ));

            if (!$exists) {
                Olama_School_Exam::save_exam(array(
                    'academic_year_id' => $year_id,
                    'semester_id' => $semester_id,
                    'semester_exam_id' => $exam_id,
                    'grade_id' => $grade_id,
                    'subject_id' => $subject->id,
                    'evaluation_type' => $exam_name,
                    'exam_date' => $exam_meta ? $exam_meta->start_date : date('Y-m-d'),
                    'status' => 'draft'
                ));
                $added++;
            }
        }

        wp_send_json_success(array('message' => sprintf(__('%d subjects added to exam.', 'olama-school'), $added)));
    }

    /**
     * AJAX: Get Units for Exam Material selection
     */
    public function ajax_get_units()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline') && !Olama_School_Permissions::can('olama_view_curriculum_timeline') && !Olama_School_Permissions::can('olama_fill_exam_details')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $subject_id = intval($_REQUEST['subject_id']);
        $grade_id = intval($_REQUEST['grade_id']);
        $semester_id = intval($_REQUEST['semester_id']);

        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        wp_send_json_success($units);
    }

    /**
     * AJAX: Get Lessons for Exam Material selection
     */
    public function ajax_get_lessons()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline') && !Olama_School_Permissions::can('olama_view_curriculum_timeline') && !Olama_School_Permissions::can('olama_fill_exam_details')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $lessons = Olama_School_Lesson::get_lessons(intval($_REQUEST['unit_id']));
        wp_send_json_success($lessons);
    }

    /**
     * Render Evaluation Management Content
     */
    public function render_evaluation_mgmt_page_content()
    {
        $manager = new Olama_School_EV_Manager();
        $manager->render_page();
    }

    /**
     * Render Student Evaluation Content
     */
    public function render_student_evaluation_page_content($context = null)
    {
        $form = new Olama_School_EV_Form();
        $form->render_page($context);
    }


    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        $tabs_config = array(
            'general' => array('label' => __('General Settings', 'olama-school'), 'cap' => 'olama_manage_settings_general'),
            'family_gateway' => array('label' => Olama_School_Helpers::translate('Family Gateway'), 'cap' => 'olama_manage_settings_general'),
            'shortcode' => array('label' => __('Shortcode Generator', 'olama-school'), 'cap' => 'olama_manage_settings_shortcode'),
            'backup' => array('label' => __('Backup & Restore', 'olama-school'), 'cap' => 'manage_options'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                <?php _e('Plugin Settings', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $id => $tab): ?>
                    <a href="?page=olama-school-settings&tab=<?php echo $id; ?>"
                        class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'general'): ?>
                    <?php
                    $is_admin = current_user_can('manage_options');
                    $settings = get_option('olama_school_settings', array());
                    ?>
                    <form method="post" action="<?php echo $is_admin ? 'options.php' : ''; ?>">
                        <?php
                        if ($is_admin) {
                            settings_fields('olama_school_settings_group');
                            do_settings_sections('olama_school_settings_group');
                        } else {
                            wp_nonce_field('olama_teacher_settings_save', 'olama_teacher_settings_nonce');
                            echo '<input type="hidden" name="olama_teacher_save" value="1" />';
                        }
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('School Name (Arabic)', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="olama_school_settings[school_name_ar]"
                                        value="<?php echo esc_attr($settings['school_name_ar'] ?? ''); ?>" class="regular-text"
                                        <?php echo !$is_admin ? 'disabled' : ''; ?> />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('School Name (English)', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="olama_school_settings[school_name_en]"
                                        value="<?php echo esc_attr($settings['school_name_en'] ?? ''); ?>" class="regular-text"
                                        <?php echo !$is_admin ? 'disabled' : ''; ?> />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('School Start Day', 'olama-school'); ?>
                                </th>
                                <td>
                                    <select name="olama_school_settings[start_day]" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                        <?php
                                        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
                                        foreach ($days as $day): ?>
                                            <option value="<?php echo strtolower($day); ?>" <?php selected($settings['start_day'] ?? 'monday', strtolower($day)); ?>>
                                                <?php echo $day; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('School Last Day', 'olama-school'); ?>
                                </th>
                                <td>
                                    <select name="olama_school_settings[last_day]" <?php echo !$is_admin ? 'disabled' : ''; ?>>
                                        <?php foreach ($days as $day): ?>
                                            <option value="<?php echo strtolower($day); ?>" <?php selected($settings['last_day'] ?? 'friday', strtolower($day)); ?>>
                                                <?php echo $day; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('Default Language', 'olama-school'); ?>
                                </th>
                                <td>
                                    <select name="olama_school_settings[default_lang]">
                                        <option value="ar" <?php selected($settings['default_lang'] ?? '', 'ar'); ?>>
                                            <?php _e('Arabic', 'olama-school'); ?>
                                        </option>
                                        <option value="en" <?php selected($settings['default_lang'] ?? '', 'en'); ?>>
                                            <?php _e('English', 'olama-school'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('Ramadan Start Date', 'olama-school'); ?>
                                </th>
                                <td>
                                    <input type="text" name="olama_school_settings[ramadan_start]"
                                        value="<?php echo esc_attr($settings['ramadan_start'] ?? ''); ?>" class="olama-datepicker"
                                        <?php echo !$is_admin ? 'disabled' : ''; ?> />
                                    <p class="description">
                                        <?php _e('Dates when the Ramadan schedule will be active.', 'olama-school'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e('Ramadan End Date', 'olama-school'); ?>
                                </th>
                                <td>
                                    <input type="text" name="olama_school_settings[ramadan_end]"
                                        value="<?php echo esc_attr($settings['ramadan_end'] ?? ''); ?>" class="olama-datepicker"
                                        <?php echo !$is_admin ? 'disabled' : ''; ?> />
                                </td>
                            </tr>
                            <?php if ($is_admin): ?>
                                <tr>
                                    <th colspan="2" style="padding-top: 30px;">
                                        <h3 style="margin:0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
                                            <?php _e('Security Settings', 'olama-school'); ?>
                                        </h3>
                                    </th>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php _e('Admin Deletion Password', 'olama-school'); ?>
                                    </th>
                                    <td>
                                        <input type="password" name="olama_school_settings[deletion_password]"
                                            value="<?php echo esc_attr($settings['deletion_password'] ?? ''); ?>"
                                            class="regular-text" />
                                        <p class="description">
                                            <?php _e('Required for the "Force Delete Everything" feature in Curriculum Management.', 'olama-school'); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                <?php elseif ($active_tab === 'family_gateway'): ?>
                    <?php $this->render_family_gateway_settings_content(); ?>
                <?php elseif ($active_tab === 'backup'): ?>
                    <?php $this->render_backup_settings_content(); ?>
                <?php else: ?>
                    <?php $this->render_shortcode_generator_content(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Shortcode Generator Tab Content
     */
    public function render_shortcode_generator_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/shortcode-generator.php';
    }

    /**
     * Render Curriculum Page Content
     */
    public function render_curriculum_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-main.php';
    }

    /**
     * Render Curriculum Timeline Page Content
     */
    public function render_timeline_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-timeline.php';
    }

    /**
     * Render Bulk Upload Page Content
     */
    public function render_bulk_upload_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-bulk-upload.php';
    }

    /**
     * Render Curriculum Analysis Page Content
     */
    public function render_curriculum_analysis_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-analysis.php';
    }

    /**
     * Render Weekly Plan Management (Tabbed)
     */
    public function render_weekly_plan_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';

        // Calculate pending review count — scoped to active year & semester
        global $wpdb;
        $review_count = 0;
        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;
        $active_semester = $active_year_id ? Olama_School_Academic::get_active_semester($active_year_id) : null;
        $active_semester_id = $active_semester ? intval($active_semester->id) : 0;

        if (Olama_School_Permissions::can('olama_approve_plans')) {
            $review_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_plans p
                JOIN {$wpdb->prefix}olama_semesters sem ON p.semester_id = sem.id
                WHERE p.status IN ('submitted', 'needs_edit')
                AND sem.academic_year_id = %d
                AND p.semester_id = %d",
                $active_year_id,
                $active_semester_id
            ));
        } else {
            $review_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_plans p
                JOIN {$wpdb->prefix}olama_semesters sem ON p.semester_id = sem.id
                WHERE p.status = 'needs_edit' AND p.teacher_id = %d
                AND sem.academic_year_id = %d
                AND p.semester_id = %d",
                get_current_user_id(),
                $active_year_id,
                $active_semester_id
            ));
        }

        $review_label = Olama_School_Helpers::translate('Review Queue');
        if ($review_count > 0) {
            $review_label .= ' <span style="background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-right: 5px; font-weight: 700; vertical-align: middle;">' . $review_count . '</span>';
        }

        $tabs_config = array(
            'creation' => array('label' => __('Plan Creation', 'olama-school'), 'cap' => 'olama_create_plans'),
            'list' => array('label' => __('Plan List', 'olama-school'), 'cap' => 'olama_manage_plans_list'),
            'comparison' => array('label' => __('Plan Comparison', 'olama-school'), 'cap' => 'olama_manage_plans_comparison'),
            'schedule' => array('label' => __('Weekly Schedule', 'olama-school'), 'cap' => 'olama_manage_plans_schedule'),
            'data' => array('label' => __('Data Management', 'olama-school'), 'cap' => 'olama_manage_plans_data'),
            'load' => array(
                'label' => __('Plan Load', 'olama-school'),
                'cap' => array('olama_manage_plans_load', 'olama_view_plans_load')
            ),
            'coverage' => array('label' => __('Curriculum Coverage', 'olama-school'), 'cap' => 'olama_manage_plans_coverage'),
            'search' => array('label' => Olama_School_Helpers::translate('Search Plan'), 'cap' => 'olama_manage_plans_list'),
            'review' => array('label' => $review_label, 'cap' => 'olama_access_plans_mgmt'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            $caps = (array) $tab['cap'];
            $has_access = false;
            foreach ($caps as $cap) {
                if (Olama_School_Permissions::can($cap)) {
                    $has_access = true;
                    break;
                }
            }
            if ($has_access) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                <?php _e('Weekly Plan Management', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php
                $base_params = array(
                    'academic_year_id' => isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0,
                    'semester_id' => isset($_GET['semester_id']) ? sanitize_text_field($_GET['semester_id']) : '',
                    'grade_id' => isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0,
                    'section_id' => isset($_GET['section_id']) ? intval($_GET['section_id']) : 0,
                    'plan_month' => isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '',
                    'week_start' => isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '',
                    'subject_id' => isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0,
                );

                foreach ($allowed_tabs as $tab_slug => $tab_data):
                    $url = add_query_arg(array_merge(array('page' => 'olama-school-plans', 'tab' => $tab_slug), array_filter($base_params)), admin_url('admin.php'));
                    ?>
                    <a href="<?php echo esc_url($url); ?>"
                        class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_data['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'creation':
                        $this->render_plan_page_content();
                        break;
                    case 'list':
                        $this->render_plan_list_page_content();
                        break;
                    case 'comparison':
                        $this->render_comparison_page_content();
                        break;
                    case 'schedule':
                        $this->render_schedule_page_content();
                        break;
                    case 'data':
                        $this->render_data_management_page_content();
                        break;
                    case 'load':
                        $this->render_plan_load_page_content();
                        break;
                    case 'coverage':
                        $this->render_curriculum_coverage_page_content();
                        break;
                    case 'review':
                        $this->render_review_queue_page_content();
                        break;
                    case 'search':
                        $this->render_search_plan_page_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Teacher Office Hours Save
     */
    public function handle_office_hours_save()
    {
        if (isset($_POST['olama_save_office_hours']) && check_admin_referer('olama_save_office_hours', 'olama_office_hours_nonce')) {
            if (!Olama_School_Permissions::can('olama_access_academic_mgmt')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }

            $teacher_id = intval($_POST['teacher_id']);
            $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
            $semester_id = intval($_POST['semester_id'] ?? 0);
            $slots = $_POST['slots'] ?? [];

            Olama_School_Teacher::save_office_hours($teacher_id, $slots, $academic_year_id, $semester_id);

            $redirect_url = admin_url('admin.php?page=olama-school-academic&tab=office_hours&teacher_id=' . $teacher_id);
            if ($academic_year_id)
                $redirect_url = add_query_arg('academic_year_id', $academic_year_id, $redirect_url);
            if ($semester_id)
                $redirect_url = add_query_arg('semester_id', $semester_id, $redirect_url);

            $redirect_url = add_query_arg('message', 'office_hours_saved', $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Render Teacher Office Hours Page Content
     */
    public function render_teacher_office_hours_page_content()
    {
        $teachers = Olama_School_Teacher::get_teachers();
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Default to current user if they are a teacher, unless an ID is specified and user is admin
        $selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

        if (!$selected_teacher_id) {
            $user = wp_get_current_user();
            if (in_array('teacher', (array) $user->roles)) {
                $selected_teacher_id = $current_user_id;
            } elseif (!empty($teachers)) {
                $selected_teacher_id = $teachers[0]->ID;
            }
        }

        // Security check: Teachers can only edit their own office hours, Admins can edit anyone's
        if (!$is_admin && $selected_teacher_id !== $current_user_id) {
            $selected_teacher_id = $current_user_id;
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/teacher-office-hours.php';
    }

    /**
     * Render Weekly Plan Creation Content
     */
    public function render_plan_page_content()
    {
        if ((isset($_POST['save_plan']) || isset($_POST['plan_id'])) && check_admin_referer('olama_save_plan', 'olama_plan_nonce')) {
            $data = $_POST;

            // Add semester_id and academic_year_id from GET parameters
            $data['semester_id'] = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
            $data['academic_year_id'] = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;

            // If semester_id is still 0, try to get the active semester
            if (!$data['semester_id']) {
                $active_year = Olama_School_Academic::get_active_year();
                $active_semester = Olama_School_Academic::get_active_semester($active_year ? $active_year->id : 0);
                $data['semester_id'] = $active_semester ? $active_semester->id : 0;
                $data['academic_year_id'] = $active_year ? $active_year->id : 0;
            }

            // Sanitize homework fields and notes
            $data['homework_sb'] = sanitize_textarea_field($data['homework_sb'] ?? '');
            $data['homework_eb'] = sanitize_textarea_field($data['homework_eb'] ?? '');
            $data['homework_nb'] = sanitize_textarea_field($data['homework_nb'] ?? '');
            $data['homework_ws'] = sanitize_textarea_field($data['homework_ws'] ?? '');
            $data['teacher_notes'] = sanitize_textarea_field($data['teacher_notes'] ?? '');
            $data['teacher_response'] = sanitize_textarea_field($data['teacher_response'] ?? '');

            $result = Olama_School_Plan::save_plan($data);
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Weekly plan saved successfully.', 'olama-school') . '</p></div>';
            }
        }

        $grades = Olama_School_Grade::get_grades();


        if (!$grades) {
            echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
            return;
        }

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);



        $selected_section_id = 0;
        if (!empty($sections)) {
            $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);

            // Validate section belongs to the selected grade
            $is_valid_section = false;
            foreach ($sections as $sec) {
                if (intval($sec->id) === $selected_section_id) {
                    $is_valid_section = true;
                    break;
                }
            }

            if (!$is_valid_section) {
                $selected_section_id = intval($sections[0]->id);
            }
        }

        // Academic Infrastructure — year and semester are always locked to active values
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = $active_year ? $active_year->id : 0;

        $current_semesters = [];
        $active_semester = null;
        if ($selected_year_id) {
            $current_semesters = Olama_School_Academic::get_semesters($selected_year_id);
            $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        }
        $selected_semester_id = $active_semester ? intval($active_semester->id) : 0;

        // Validate that the selected semester belongs to the selected year
        $valid_semester = false;
        if ($selected_semester_id > 0) {
            foreach ($current_semesters as $sem) {
                if (intval($sem->id) === $selected_semester_id) {
                    $valid_semester = true;
                    break;
                }
            }
        }
        if (!$valid_semester && !empty($current_semesters)) {
            $selected_semester_id = intval($current_semesters[0]->id);
        }

        // Date logic: Week start dropdown grouped by month
        $all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id);
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key_start = date('Y-m', strtotime($val));
            $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

            // Check if week ends in a different month (cross-month support)
            $week_range = Olama_School_Helpers::get_week_range($val);
            $m_key_end = date('Y-m', strtotime($week_range['end']));
            if ($m_key_end !== $m_key_start) {
                $months_weeks[$m_key_end][] = array('val' => $val, 'label' => $label);
            }
        }

        // Sort months chronologically
        ksort($months_weeks);

        // Determine the month to show
        $selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '';

        // If the current selected month is not valid for this semester, pick the first available
        if (empty($selected_month) || !isset($months_weeks[$selected_month])) {
            $today_month = date('Y-m');
            if (isset($months_weeks[$today_month])) {
                $selected_month = $today_month;
            } elseif (!empty($months_weeks)) {
                $m_keys = array_keys($months_weeks);
                $selected_month = $m_keys[0];
            }
        }

        $current_month_weeks = $months_weeks[$selected_month] ?? array();

        // Determine the week to show
        $week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '';
        $valid_week = false;
        if (!empty($week_start)) {
            foreach ($current_month_weeks as $w) {
                if ($w['val'] === $week_start) {
                    $valid_week = true;
                    break;
                }
            }
        }

        // If not valid, default to the week containing today (smart current-week detection)
        if (!$valid_week && !empty($current_month_weeks)) {
            $today = date('Y-m-d');
            $found_current = false;
            foreach ($current_month_weeks as $w) {
                $w_range = Olama_School_Helpers::get_week_range($w['val']);
                if ($today >= $w_range['start'] && $today <= $w_range['end']) {
                    $week_start = $w['val'];
                    $found_current = true;
                    break;
                }
            }
            if (!$found_current) {
                $week_start = $current_month_weeks[0]['val'];
            }
        }

        $school_days = Olama_School_Helpers::get_school_days();
        $days = array();
        foreach ($school_days as $idx => $day_name) {
            $days[$day_name] = date('Y-m-d', strtotime($week_start . " +$idx days"));
        }

        $active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : ($school_days[0] ?? 'Sunday');
        $selected_date = $days[$active_day] ?? ($days[array_key_first($days)] ?? $week_start);

        // Use the selected semester directly for all queries including subject loading
        // This ensures subjects from the schedule (linked to semester_id) are correctly loaded
        $semester_id = $selected_semester_id;

        $week_range = Olama_School_Helpers::get_week_range($week_start);
        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, $week_range['end']);
        $today_plans = array_filter($all_plans, function ($p) use ($selected_date) {
            return $p->plan_date === $selected_date;
        });

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-creation.php';
    }

    /**
     * Render weekly plan list page (grouped by day)
     */
    /**
     * Render Weekly Plan List Content
     */
    public function render_plan_list_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        if (!$grades) {
            echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
            return;
        }

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);

        $selected_section_id = 0;
        if (!empty($sections)) {
            $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);

            // Validate section belongs to the selected grade
            $is_valid_section = false;
            foreach ($sections as $sec) {
                if (intval($sec->id) === $selected_section_id) {
                    $is_valid_section = true;
                    break;
                }
            }

            if (!$is_valid_section) {
                $selected_section_id = intval($sections[0]->id);
            }
        }

        // Academic Infrastructure — year and semester are always locked to active values
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = $active_year ? $active_year->id : 0;
        $active_semester = null;
        $current_semesters = [];
        if ($selected_year_id) {
            $current_semesters = Olama_School_Academic::get_semesters($selected_year_id);
            $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        }
        $selected_semester_id = $active_semester ? intval($active_semester->id) : 0;

        // Reuse week selection logic
        $all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id);
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key = date('Y-m', strtotime($val));
            $months_weeks[$m_key][] = array('val' => $val, 'label' => $label);
        }

        // Sort months chronologically
        ksort($months_weeks);

        // Determine the month to show — default to current month
        $selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '';
        if (empty($selected_month) || !isset($months_weeks[$selected_month])) {
            $today_month = date('Y-m');
            if (isset($months_weeks[$today_month])) {
                $selected_month = $today_month;
            } elseif (!empty($months_weeks)) {
                $m_keys = array_keys($months_weeks);
                $selected_month = $m_keys[0];
            }
        }

        $current_month_weeks = $months_weeks[$selected_month] ?? array();

        // Determine the week to show — smart current-week detection
        $week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '';
        $valid_week = false;
        if (!empty($week_start)) {
            foreach ($current_month_weeks as $w) {
                if ($w['val'] === $week_start) {
                    $valid_week = true;
                    break;
                }
            }
        }
        if (!$valid_week && !empty($current_month_weeks)) {
            $today = date('Y-m-d');
            $found_current = false;
            foreach ($current_month_weeks as $w) {
                $w_range = Olama_School_Helpers::get_week_range($w['val']);
                if ($today >= $w_range['start'] && $today <= $w_range['end']) {
                    $week_start = $w['val'];
                    $found_current = true;
                    break;
                }
            }
            if (!$found_current) {
                $week_start = $current_month_weeks[0]['val'] ?? '';
            }
        }

        $school_days = Olama_School_Helpers::get_school_days();
        $days = array();
        foreach ($school_days as $idx => $day_name) {
            $days[$day_name] = date('Y-m-d', strtotime($week_start . " +$idx days"));
        }

        $week_range = Olama_School_Helpers::get_week_range($week_start);
        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, $week_range['end']);

        // Group plans by date
        $grouped_plans = array();
        foreach ($days as $day_name => $date) {
            $grouped_plans[$date] = array_filter($all_plans, function ($p) use ($date) {
                return $p->plan_date === $date;
            });
        }
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-list.php';
    }

    /**
     * Render Weekly Search Plan Content
     */
    public function render_search_plan_page_content()
    {
        // Year and semester are always locked to active values
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = $active_year ? $active_year->id : 0;
        $active_semester = null;
        $current_semesters = [];
        if ($selected_year_id) {
            $current_semesters = Olama_School_Academic::get_semesters($selected_year_id);
            $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        }
        $selected_semester_id = $active_semester ? intval($active_semester->id) : 0;

        $all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id);

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-search-plan.php';
    }


    /**
     * Render Weekly Schedule (Form 14)
     */
    public function render_schedule_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-schedule.php';
    }


    /**
     * Render Dashboard (Form 18)
     */
    public function render_dashboard_page()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/dashboard.php';
    }

    /**
     * Render Reports (Forms 16, 19)
     */
    public function render_reports_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'completion';

        $tabs_config = array(
            'completion' => array('label' => __('Plan Completion', 'olama-school'), 'cap' => 'olama_view_reports_summary'),
            'homework' => array('label' => __('Homework Summary', 'olama-school'), 'cap' => 'olama_view_reports_homework'),
        );

        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }

        if (empty($allowed_tabs)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'olama-school'));
        }

        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }

        ?>
        <div class="wrap olama-school-wrap">
            <h1>
                <?php _e('School Reports', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $id => $tab): ?>
                    <a href="?page=olama-school-reports&tab=<?php echo $id; ?>"
                        class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab['label']; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                // Include reports.php which will handle the layout based on $active_tab
                include OLAMA_SCHOOL_PATH . 'includes/admin-views/reports.php';
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Plan Comparison Content
     */
    public function render_comparison_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-comparison.php';
    }

    public function render_permissions_page_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $roles = array(
            'administrator' => __('Administrator', 'olama-school'),
            'editor' => __('Supervisor', 'olama-school'),
            'author' => __('Assistant', 'olama-school'),
            'subscriber' => __('Student/Subscriber', 'olama-school'),
        );

        $capabilities = array(
            'olama_view_plans' => __('View Weekly Plans', 'olama-school'),
            'olama_create_plans' => __('Create Own Plans', 'olama-school'),
            'olama_manage_own_plans' => __('Edit Own Plans', 'olama-school'),
            'olama_approve_plans' => __('Approve Weekly Plans', 'olama-school'),
            'olama_manage_academic_structure' => __('Manage Academic Structure', 'olama-school'),
            'olama_manage_curriculum' => __('Manage Curriculum', 'olama-school'),
            'olama_view_reports' => __('View Reports', 'olama-school'),
            'olama_import_export_data' => __('Import/Export Data', 'olama-school'),
            'olama_view_logs' => __('View Logs', 'olama-school'),
        );

        if (isset($_POST['save_permissions'])) {
            check_admin_referer('olama_save_permissions');
            foreach ($roles as $role_name => $role_label) {
                $role = get_role($role_name);
                if (!$role)
                    continue;

                foreach ($capabilities as $cap => $cap_label) {
                    if (isset($_POST['caps'][$role_name][$cap])) {
                        $role->add_cap($cap);
                    } else {
                        $role->remove_cap($cap);
                    }
                }
            }
            echo '<div class="updated"><p>' . __('Permissions updated successfully.', 'olama-school') . '</p></div>';
        }

        ?>
        <div class="olama-permissions-container">
            <form method="post">
                <?php wp_nonce_field('olama_save_permissions'); ?>
                <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 250px;">
                                    <?php _e('Capability', 'olama-school'); ?>
                                </th>
                                <?php foreach ($roles as $label): ?>
                                    <th>
                                        <?php echo esc_html($label); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($capabilities as $cap => $cap_label): ?>
                                <tr>
                                    <td><strong>
                                            <?php echo esc_html($cap_label); ?>
                                        </strong></td>
                                    <?php foreach ($roles as $role_name => $label):
                                        $role = get_role($role_name);
                                        $has_cap = $role ? $role->has_cap($cap) : false;
                                        ?>
                                        <td>
                                            <input type="checkbox"
                                                name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]" <?php checked($has_cap); ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 20px;">
                    <?php submit_button(__('Save All Permissions', 'olama-school'), 'primary', 'save_permissions'); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render Notifications & Logs Content
     */
    public function render_notifications_page_content()
    {
        global $wpdb;

        // Fetch logs (last 50)
        $logs = $wpdb->get_results("
            SELECT l.*, u.display_name 
            FROM {$wpdb->prefix}olama_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 50
        ");

        ?>
        <div class="olama-logs-container" style="background: #f0f2f5; padding: 20px; border-radius: 12px;">

            <div
                style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h2 style="margin-top: 0;">
                    <?php _e('Recent Activities (Audit Log)', 'olama-school'); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Date/Time', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('User', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Action', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Details', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('IP Address', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs):
                            foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($log->created_at); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->display_name ?: 'System'); ?>
                                    </td>
                                    <td><span class="badge"
                                            style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                            <?php echo esc_html($log->action); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->details); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($log->ip_address); ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5">
                                    <?php _e('No logs found.', 'olama-school'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;">
                    <?php _e('Notification Settings', 'olama-school'); ?>
                </h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('olama_notifications_group');
                    $notif_email = get_option('olama_admin_email', get_option('admin_email'));
                    $enable_notifs = get_option('olama_enable_notifs', 'yes');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('Admin Notification Email', 'olama-school'); ?>
                            </th>
                            <td><input type="email" name="olama_admin_email" value="<?php echo esc_attr($notif_email); ?>"
                                    class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('Enable Email Notifications', 'olama-school'); ?>
                            </th>
                            <td>
                                <select name="olama_enable_notifs">
                                    <option value="yes" <?php selected($enable_notifs, 'yes'); ?>>
                                        <?php _e('Yes', 'olama-school'); ?>
                                    </option>
                                    <option value="no" <?php selected($enable_notifs, 'no'); ?>>
                                        <?php _e('No', 'olama-school'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Data Management (Import/Export)
     */
    /**
     * Render Data Management Content
     */
    public function render_data_management_page_content()
    {
        if (!Olama_School_Permissions::can('olama_manage_plans_data')) {
            echo '<div class="error"><p>' . __('You do not have permission to access this page.', 'olama-school') . '</p></div>';
            return;
        }
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-data.php';
    }

    /**
     * Render Plan Load Tab Content
     */
    public function render_plan_load_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-load.php';
    }



    /**
     * Render Curriculum Coverage Tab Content
     */
    public function render_curriculum_coverage_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-coverage.php';
    }

    /**
     * Render Review Queue Tab Content
     */
    public function render_review_queue_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-review.php';
    }
    /**
     * Render Teacher Assignments Tab Content
     */
    public function render_teacher_assignments_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-assignments.php';
    }

    /**
     * Render Stationary Tab Content
     */
    public function render_stationary_page_content()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $stationary_data = Olama_School_Stationary::get_stationary($selected_year_id, $selected_grade_id);

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/stationary.php';
    }

    /**
     * AJAX Save Exam
     */
    public function ajax_save_exam()
    {
        // Check nonce from various potential sources
        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = $_POST['nonce'];
        } elseif (isset($_POST['olama_exam_nonce_field'])) {
            $nonce = $_POST['olama_exam_nonce_field'];
        } elseif (isset($_POST['olama_material_nonce_field'])) {
            $nonce = $_POST['olama_material_nonce_field'];
        }

        if (empty($nonce) || !wp_verify_nonce($nonce, 'olama_save_exam')) {
            wp_send_json_error(__('Session expired or security check failed. Please refresh the page and try again.', 'olama-school'));
        }

        if (!Olama_School_Permissions::can('olama_manage_exams_schedule') && !Olama_School_Permissions::can('olama_fill_exam_details')) {
            wp_send_json_error(__('Permission denied.', 'olama-school'));
        }

        if (empty($_POST['academic_year_id']) || empty($_POST['semester_id']) || empty($_POST['grade_id']) || empty($_POST['subject_id'])) {
            wp_send_json_error(__('Required fields are missing.', 'olama-school'));
        }

        if (isset($_POST['exam_date'])) {
            $_POST['exam_date'] = Olama_School_Helpers::sanitize_date($_POST['exam_date']);
        }

        $result = Olama_School_Exam::save_exam($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif ($result === false) {
            wp_send_json_error(__('Database error: Could not save exam.', 'olama-school'));
        } else {
            wp_send_json_success(array('message' => Olama_School_Helpers::translate('Exam saved successfully.')));
        }
    }

    /**
     * AJAX Get Semesters for Year
     */
    public function ajax_get_semesters()
    {
        $year_id = intval($_GET['year_id']);
        $semesters = Olama_School_Academic::get_semesters($year_id);
        wp_send_json_success($semesters);
    }

    /**
     * AJAX Get Subjects for Grade
     */
    public function ajax_get_subjects()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');
        $grade_id = intval($_REQUEST['grade_id']);
        $subjects = Olama_School_Subject::get_subjects_by_grade($grade_id, true);
        wp_send_json_success($subjects);
    }

    /**
     * AJAX: Get student enrollment history
     */
    public function ajax_get_enrollment_history()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
        if (!$student_id) {
            wp_send_json_error('Invalid student ID');
        }

        $history = Olama_School_Student::get_enrollment_history($student_id);
        wp_send_json_success($history);
    }

    /**
     * Get Extended Dashboard Stats
     */
    public static function get_dashboard_extended_stats()
    {
        global $wpdb;

        $stats = array();

        // Total Students
        $stats['total_students'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_students");

        // Enrolled Students
        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;

        $stats['enrolled_students'] = 0;
        if ($active_year_id) {
            $stats['enrolled_students'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT student_id) FROM {$wpdb->prefix}olama_student_enrollment WHERE academic_year_id = %d",
                $active_year_id
            ));
        }

        // Enrollment Percentage
        $stats['enrollment_pct'] = $stats['total_students'] > 0 ? round(($stats['enrolled_students'] / $stats['total_students']) * 100) : 0;

        // Plan Compliance
        $stats['plan_compliance'] = 0;
        if ($active_year_id) {
            $total_sections = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE academic_year_id = %d",
                $active_year_id
            ));

            if ($total_sections > 0) {
                $start_of_week = date('Y-m-d', strtotime('last Sunday'));
                $end_of_week = date('Y-m-d', strtotime('next Saturday'));

                $planned_sections = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT section_id) FROM {$wpdb->prefix}olama_plans 
                    WHERE academic_year_id = %d AND plan_date BETWEEN %s AND %s",
                    $active_year_id,
                    $start_of_week,
                    $end_of_week
                ));

                $stats['plan_compliance'] = round(($planned_sections / $total_sections) * 100);
            }
        }

        return $stats;
    }

    /**
     * Get System Alerts
     */
    public static function get_system_alerts()
    {
        global $wpdb;
        $alerts = array();

        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;

        if (!$active_year_id) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => __('No active academic year found.', 'olama-school'),
                'icon' => 'dashicons-warning'
            );
            return $alerts;
        }

        $unassigned_sections = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE academic_year_id = %d AND homeroom_teacher_id IS NULL",
            $active_year_id
        ));

        if ($unassigned_sections > 0) {
            $alerts[] = array(
                'type' => 'error',
                'message' => sprintf(_n('%d section missing teacher.', '%d sections missing teachers.', $unassigned_sections, 'olama-school'), $unassigned_sections),
                'icon' => 'dashicons-admin-users'
            );
        }

        $current_week_start = Olama_School_Helpers::get_active_week_start();
        $next_week_start = date('Y-m-d', strtotime($current_week_start . ' + 7 days'));
        $next_week_range = Olama_School_Helpers::get_week_range($next_week_start);
        $next_week_end = $next_week_range['end'];

        $sections_planned = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_plans WHERE academic_year_id = %d AND plan_date BETWEEN %s AND %s",
            $active_year_id,
            $next_week_start,
            $next_week_end
        ));

        $total_sections_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_sections WHERE academic_year_id = %d",
            $active_year_id
        ));

        $missing_plans = count(array_diff((array) $total_sections_ids, (array) $sections_planned));

        if ($missing_plans > 0) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => sprintf(_n('%d section missing plans.', '%d sections missing plans.', $missing_plans, 'olama-school'), $missing_plans),
                'icon' => 'dashicons-calendar-alt'
            );
        }

        return $alerts;
    }

    /**
     * Get Pending Plans for Review
     */
    public static function get_pending_plans_for_review($limit = 10)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.*, s.subject_name, sec.section_name, g.grade_name, u.display_name as teacher_name
            FROM {$wpdb->prefix}olama_plans p
            JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
            JOIN {$wpdb->prefix}olama_sections sec ON p.section_id = sec.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            JOIN {$wpdb->users} u ON p.teacher_id = u.ID
            WHERE p.status = 'submitted'
            ORDER BY p.created_at ASC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get Weekly Coverage Data
     */
    public static function get_weekly_coverage_data()
    {
        global $wpdb;

        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;

        if (!$active_year_id)
            return array();

        $start_of_week = date('Y-m-d', strtotime('last Sunday'));
        $end_of_week = date('Y-m-d', strtotime('next Saturday'));

        // Get all sections for the active year
        $sections = $wpdb->get_results($wpdb->prepare("
            SELECT s.id, s.section_name, s.grade_id, g.grade_name 
            FROM {$wpdb->prefix}olama_sections s
            JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            WHERE s.academic_year_id = %d
            ORDER BY 
                CASE 
                    WHEN g.grade_name LIKE '%%البستان%%' THEN 1
                    WHEN g.grade_name LIKE '%%التمهيدي%%' THEN 2
                    WHEN g.grade_name LIKE '%%الأول%%' OR g.grade_name LIKE '%%اول%%' THEN 3
                    WHEN g.grade_name LIKE '%%الثاني%%' OR g.grade_name LIKE '%%ثاني%%' THEN 4
                    WHEN g.grade_name LIKE '%%الثالث%%' OR g.grade_name LIKE '%%ثالث%%' THEN 5
                    WHEN g.grade_name LIKE '%%الرابع%%' OR g.grade_name LIKE '%%رابع%%' THEN 6
                    WHEN g.grade_name LIKE '%%الخامس%%' OR g.grade_name LIKE '%%خامس%%' THEN 7
                    WHEN g.grade_name LIKE '%%السادس%%' OR g.grade_name LIKE '%%سادس%%' THEN 8
                    WHEN g.grade_name LIKE '%%السابع%%' OR g.grade_name LIKE '%%سابع%%' THEN 9
                    WHEN g.grade_name LIKE '%%الثامن%%' OR g.grade_name LIKE '%%ثامن%%' THEN 10
                    WHEN g.grade_name LIKE '%%التاسع%%' OR g.grade_name LIKE '%%تاسع%%' THEN 11
                    WHEN g.grade_name LIKE '%%العاشر%%' OR g.grade_name LIKE '%%عاشر%%' THEN 12
                    WHEN g.grade_name LIKE '%%الحادي عشر%%' THEN 13
                    ELSE 99
                END ASC,
                s.section_name ASC
        ", $active_year_id));

        // Get plan statuses for the week
        $plans = $wpdb->get_results($wpdb->prepare("
            SELECT section_id, subject_id, status 
            FROM {$wpdb->prefix}olama_plans 
            WHERE academic_year_id = %d AND plan_date BETWEEN %s AND %s
        ", $active_year_id, $start_of_week, $end_of_week));

        $coverage = array();
        foreach ($sections as $sec) {
            $coverage[$sec->id] = array(
                'name' => $sec->grade_name . ' - ' . $sec->section_name,
                'plans' => array()
            );
        }

        foreach ($plans as $plan) {
            if (isset($coverage[$plan->section_id])) {
                $coverage[$plan->section_id]['plans'][$plan->subject_id] = $plan->status;
            }
        }

        return $coverage;
    }

    /**
     * Get student attendance stats for dashboard
     */
    public static function get_student_attendance_stats($date = null)
    {
        global $wpdb;
        $active_year = Olama_School_Academic::get_active_year();
        $active_year_id = $active_year ? $active_year->id : 0;
        $today = $date ?: current_time('Y-m-d');

        $stats = array(
            'absences_by_section' => array(),
            'total' => array('enrolled' => 0, 'present' => 0, 'absent' => 0),
            'school' => array('enrolled' => 0, 'present' => 0, 'absent' => 0),
            'kg' => array('enrolled' => 0, 'present' => 0, 'absent' => 0),
        );

        if (!$active_year_id)
            return $stats;

        // 1. Get all active sections and their attendance status in one query
        $sections_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.id as section_id, 
                g.grade_name, 
                s.section_name,
                sh.id as sheet_id,
                (SELECT COUNT(*) 
                 FROM {$wpdb->prefix}olama_attendance a 
                 WHERE a.section_id = s.id 
                 AND a.attendance_date = %s 
                 AND a.status = 'absent') as absent_count
            FROM {$wpdb->prefix}olama_sections s
            JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            LEFT JOIN {$wpdb->prefix}olama_attendance_sheets sh ON s.id = sh.section_id AND sh.attendance_date = %s
            WHERE s.academic_year_id = %d
            ORDER BY g.grade_level ASC, s.section_name ASC
        ", $today, $today, $active_year_id));

        foreach ($sections_data as $sec) {
            $is_completed = !empty($sec->sheet_id);
            $absent_count = (int) $sec->absent_count;
            
            $status = 'pending';
            if ($is_completed) {
                $status = ($absent_count > 0) ? 'absences' : 'all_present';
            }

            $stats['absences_by_section'][] = array(
                'label' => $sec->grade_name . ' - ' . $sec->section_name,
                'count' => $absent_count,
                'status' => $status
            );
        }

        // 2. Get enrollment totals by category
        $enrollments = $wpdb->get_results($wpdb->prepare("
            SELECT g.grade_name, count(e.id) as count
            FROM {$wpdb->prefix}olama_student_enrollment e
            JOIN {$wpdb->prefix}olama_sections s ON e.section_id = s.id
            JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            WHERE e.academic_year_id = %d AND e.status = 'active'
            GROUP BY g.id
        ", $active_year_id));

        foreach ($enrollments as $en) {
            $is_kg = (stripos($en->grade_name, 'KG') !== false || stripos($en->grade_name, 'التمهيدي') !== false || stripos($en->grade_name, 'البستان') !== false);
            $stats['total']['enrolled'] += $en->count;
            $stats['total']['present'] += $en->count; // Default to all present
            if ($is_kg) {
                $stats['kg']['enrolled'] += $en->count;
                $stats['kg']['present'] += $en->count; // Default to all present
            } else {
                $stats['school']['enrolled'] += $en->count;
                $stats['school']['present'] += $en->count; // Default to all present
            }
        }

        // 3. Get actual attendance for today
        $attendance = $wpdb->get_results($wpdb->prepare("
            SELECT g.grade_name, a.status, count(a.id) as count
            FROM {$wpdb->prefix}olama_attendance a
            JOIN {$wpdb->prefix}olama_sections s ON a.section_id = s.id
            JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            WHERE a.attendance_date = %s
            GROUP BY g.id, a.status
        ", $today));

        foreach ($attendance as $att) {
            $is_kg = (stripos($att->grade_name, 'KG') !== false || stripos($att->grade_name, 'التمهيدي') !== false || stripos($att->grade_name, 'البستان') !== false);

            if ($att->status == 'absent') {
                $stats['total']['absent'] += $att->count;
                $stats['total']['present'] -= $att->count;
                if ($is_kg) {
                    $stats['kg']['absent'] += $att->count;
                    $stats['kg']['present'] -= $att->count;
                } else {
                    $stats['school']['absent'] += $att->count;
                    $stats['school']['present'] -= $att->count;
                }
            }
        }

        return $stats;
    }

    /**
     * Get cleaning monitoring stats for dashboard
     */
    public static function get_cleaning_dashboard_stats($date = null)
    {
        global $wpdb;

        $floors = $wpdb->get_results("SELECT id, floor_name FROM {$wpdb->prefix}olama_cleaning_floors WHERE is_active = 1");
        $slots = $wpdb->get_results("SELECT id, slot_time FROM {$wpdb->prefix}olama_cleaning_slots WHERE is_active = 1");
        $assignments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_assignments");

        $date = $date ?: current_time('Y-m-d');
        $total_slots = count($slots);
        $total_tasks = count($floors) * $total_slots;

        // Current logs for the day
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT floor_id, slot_id FROM {$wpdb->prefix}olama_cleaning_logs WHERE cleaning_date = %s",
            $date
        ));

        // 1. Floor statuses
        $floor_stats = array();
        foreach ($floors as $fl) {
            $completed_on_floor = 0;
            foreach ($logs as $log) {
                if ((int) $log->floor_id === (int) $fl->id) {
                    $completed_on_floor++;
                }
            }
            $floor_stats[] = array(
                'label' => $fl->floor_name,
                'total' => $total_slots,
                'completed' => $completed_on_floor,
                'status' => ($completed_on_floor >= $total_slots) ? 'completed' : (($completed_on_floor > 0) ? 'partial' : 'pending')
            );
        }

        // 2. Supervisor statuses
        $supervisor_tasks = array();
        foreach ($assignments as $as) {
            if (!$as->supervisor_id)
                continue;
            if (!isset($supervisor_tasks[$as->supervisor_id])) {
                $supervisor_tasks[$as->supervisor_id] = array(
                    'total' => 0,
                    'completed' => 0,
                    'name' => Olama_School_Helpers::get_user_display_name($as->supervisor_id)
                );
            }
            $supervisor_tasks[$as->supervisor_id]['total'] += $total_slots;

            // Check completion for this assignment
            foreach ($logs as $log) {
                if ((int) $log->floor_id === (int) $as->floor_id) {
                    $supervisor_tasks[$as->supervisor_id]['completed']++;
                }
            }
        }

        $completed_total = count($logs);

        return array(
            'date' => $date,
            'total_tasks' => $total_tasks,
            'completed_tasks' => $completed_total,
            'percentage' => $total_tasks > 0 ? round(($completed_total / $total_tasks) * 100) : 0,
            'floor_stats' => $floor_stats,
            'supervisors' => $supervisor_tasks
        );
    }



    /**
     * Get teaching schedule for a specific day
     */
    public static function get_teacher_daily_schedule($teacher_id, $day_name = null, $selected_date = null)
    {
        global $wpdb;

        if (!$day_name) {
            $day_name = date('l'); // Today's English day name
        }

        if (!$selected_date) {
            $selected_date = date('Y-m-d'); // Today's date
        }

        $active_year = Olama_School_Academic::get_active_year();
        $active_semester = Olama_School_Academic::get_active_semester();

        if (!$active_year || !$active_semester)
            return array();

        $schedule_type = Olama_School_Schedule::is_ramadan($selected_date) ? 'ramadan' : 'normal';

        return $wpdb->get_results($wpdb->prepare("
            SELECT sch.*, sub.subject_name, sec.section_name, g.grade_name, p.status as plan_status, p.id as plan_id
            FROM {$wpdb->prefix}olama_schedule sch
            JOIN {$wpdb->prefix}olama_teacher_assignments ta ON ta.section_id = sch.section_id AND ta.subject_id = sch.subject_id
            JOIN {$wpdb->prefix}olama_subjects sub ON sch.subject_id = sub.id
            JOIN {$wpdb->prefix}olama_sections sec ON sch.section_id = sec.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            LEFT JOIN {$wpdb->prefix}olama_plans p ON p.section_id = sch.section_id 
                AND p.subject_id = sch.subject_id 
                AND p.plan_date = %s 
                AND p.period_number = sch.period_number
            WHERE ta.teacher_id = %d 
                AND ta.academic_year_id = %d 
                AND sch.semester_id = %d
                AND sch.day_name = %s
                AND sch.schedule_type = %s
            ORDER BY sch.period_number ASC
        ", $selected_date, $teacher_id, $active_year->id, $active_semester->id, $day_name, $schedule_type));
    }

    /**
     * Get personal plan stats for a teacher
     */
    public static function get_teacher_personal_stats($teacher_id)
    {
        global $wpdb;
        $active_year = Olama_School_Academic::get_active_year();
        if (!$active_year)
            return array('total' => 0, 'approved' => 0, 'pending' => 0, 'draft' => 0);

        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}olama_plans 
            WHERE teacher_id = %d AND academic_year_id = %d
            GROUP BY status
        ", $teacher_id, $active_year->id));

        $res = array('total' => 0, 'approved' => 0, 'pending' => 0, 'draft' => 0);
        foreach ($stats as $s) {
            if ($s->status == 'approved')
                $res['approved'] = $s->count;
            if ($s->status == 'submitted')
                $res['pending'] = $s->count;
            if ($s->status == 'draft')
                $res['draft'] = $s->count;
            $res['total'] += $s->count;
        }
        return $res;
    }

    /**
     * Get progress for subjects assigned to a teacher
     */
    public static function get_teacher_subjects_progress($teacher_id)
    {
        global $wpdb;
        $active_year = Olama_School_Academic::get_active_year();
        $active_semester = Olama_School_Academic::get_active_semester();
        if (!$active_year || !$active_semester)
            return array();

        // Get all subjects assigned to this teacher
        $assignments = $wpdb->get_results($wpdb->prepare("
            SELECT ta.subject_id, ta.section_id, sub.subject_name, sec.section_name, g.grade_name
            FROM {$wpdb->prefix}olama_teacher_assignments ta
            JOIN {$wpdb->prefix}olama_subjects sub ON ta.subject_id = sub.id
            JOIN {$wpdb->prefix}olama_sections sec ON ta.section_id = sec.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            WHERE ta.teacher_id = %d AND ta.academic_year_id = %d
        ", $teacher_id, $active_year->id));

        foreach ($assignments as &$a) {
            // Count total lessons in curriculum for this subject/grade/semester
            $total_lessons = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(cl.id)
                FROM {$wpdb->prefix}olama_curriculum_lessons cl
                JOIN {$wpdb->prefix}olama_curriculum_units cu ON cl.unit_id = cu.id
                WHERE cu.subject_id = %d AND cu.grade_id = (SELECT grade_id FROM {$wpdb->prefix}olama_sections WHERE id = %d) AND cu.semester_id = %d
            ", $a->subject_id, $a->section_id, $active_semester->id));

            // Count distinct lessons covered in approved plans
            $covered_lessons = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT lesson_id)
                FROM {$wpdb->prefix}olama_plans
                WHERE section_id = %d AND subject_id = %d AND academic_year_id = %d AND status = 'approved' AND lesson_id IS NOT NULL
            ", $a->section_id, $a->subject_id, $active_year->id));

            $a->total_lessons = (int) $total_lessons;
            $a->covered_lessons = (int) $covered_lessons;
            $a->percentage = $total_lessons > 0 ? round(($covered_lessons / $total_lessons) * 100) : 0;
        }

        return $assignments;
    }
    /**
     * Create a notification for a user (Phase 3)
     */
    public static function create_notification($user_id, $type, $message)
    {
        global $wpdb;
        return $wpdb->insert(
            "{$wpdb->prefix}olama_notifications",
            array(
                'user_id' => $user_id,
                'notification_type' => $type,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            )
        );
    }

    /**
     * Get user notifications (Phase 3)
     */
    public static function get_user_notifications($user_id, $unread_only = true)
    {
        global $wpdb;
        $where = $unread_only ? "WHERE user_id = %d AND is_read = 0" : "WHERE user_id = %d";
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}olama_notifications 
            $where 
            ORDER BY created_at DESC LIMIT 20
        ", $user_id));
    }

    /**
     * AJAX: Mark notification as read (Phase 3)
     */
    public function ajax_mark_notification_read()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');
        global $wpdb;
        $notif_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $user_id = get_current_user_id();

        if ($notif_id > 0) {
            $wpdb->update(
                "{$wpdb->prefix}olama_notifications",
                array('is_read' => 1),
                array('id' => $notif_id, 'user_id' => $user_id)
            );
        } else {
            // Mark all for user
            $wpdb->update(
                "{$wpdb->prefix}olama_notifications",
                array('is_read' => 1),
                array('user_id' => $user_id)
            );
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Get notifications (Phase 3)
     */
    public function ajax_get_notifications()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');
        $user_id = get_current_user_id();
        $notifications = self::get_user_notifications($user_id, true);

        // Format created_at
        foreach ($notifications as &$n) {
            $n->time_ago = Olama_School_Helpers::time_ago($n->created_at);
        }

        wp_send_json_success($notifications);
    }
    /**
     * Restrict Teacher Access to standard WP features
     */
    public function restrict_teacher_access()
    {
        // Don't restrict if user is an administrator
        if (current_user_can('manage_options')) {
            return;
        }

        // Hide standard WP menus
        remove_menu_page('index.php');                  // Dashboard
        remove_menu_page('edit.php');                   // Posts
        remove_menu_page('upload.php');                 // Media
        remove_menu_page('edit.php?post_type=page');    // Pages
        remove_menu_page('edit-comments.php');          // Comments
        remove_menu_page('themes.php');                 // Appearance
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('users.php');                  // Users
        remove_menu_page('tools.php');                  // Tools
        remove_menu_page('options-general.php');        // Settings

        // Redirect from index.php (Dashboard) to Olama School Dashboard
        global $pagenow;
        if ($pagenow == 'index.php') {
            wp_redirect(admin_url('admin.php?page=olama-school'));
            exit;
        }
    }

    /**
     * Clean Admin Bar for Teachers
     */
    public function clean_teacher_admin_bar($wp_admin_bar)
    {
        if (!Olama_School_Permissions::can('olama_view_dashboard') || current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('site-name');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('new-content');
    }

    /**
     * AJAX: Get Students for a Family
     */
    public function ajax_get_family_students()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $family_uid = sanitize_text_field($_GET['family_uid'] ?? '');
        if (empty($family_uid)) {
            wp_send_json_error('Missing Family UID');
        }

        $students = Olama_School_Family::get_family_students($family_uid);
        wp_send_json_success($students);
    }

    /**
     * Handle Family & Student Import/Export actions
     */
    public function handle_family_actions()
    {
        // Export Families
        if (isset($_POST['olama_export_families'])) {
            Olama_School_Exporter::export_families_csv();
        }

        // Import Families and Student Enrollment are now handled by the global handle_export method

        // Save Family
        if (isset($_POST['save_family'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_save_family')) {
                wp_die(__('Security check failed.', 'olama-school'));
            }

            // Prepare family data
            $family_data = array(
                'id' => $_POST['family_db_id'] ?? 0,
                'family_uid' => $_POST['family_uid'],
                'family_name' => $_POST['family_name'],
                'father_mobile' => $_POST['father_mobile'] ?? '',
                'mother_mobile' => $_POST['mother_mobile'] ?? '',
                'address' => $_POST['address'] ?? ''
            );

            $id = Olama_School_Family::save_family($family_data);

            if ($id && !is_wp_error($id)) {
                // Batch save students
                if (isset($_POST['students']) && is_array($_POST['students'])) {
                    foreach ($_POST['students'] as $stu) {
                        if (empty($stu['name']) || empty($stu['uid']))
                            continue;

                        $stu_data = array(
                            'student_name' => sanitize_text_field($stu['name']),
                            'student_uid' => sanitize_text_field($stu['uid']),
                            'family_id' => $family_data['family_uid'],
                            'dob' => $stu['dob'] ?? '',
                            'national_id' => $stu['national_id'] ?? '',
                            'gender' => $stu['gender'] ?? 'male'
                        );

                        if (!empty($stu['db_id'])) {
                            Olama_School_Student::update_student(intval($stu['db_id']), $stu_data);
                        } else {
                            Olama_School_Student::register_student($stu_data);
                        }
                    }
                }
                set_transient('olama_admin_message', __('Family and students saved successfully.', 'olama-school'), 30);
            } elseif (is_wp_error($id)) {
                set_transient('olama_admin_error', $id->get_error_message(), 30);
            }

            wp_redirect(admin_url('admin.php?page=olama-school-users&tab=families'));
            exit;
        }

        // Delete Family
        if (isset($_POST['delete_family'])) {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_delete_family')) {
                wp_die(__('Security check failed.', 'olama-school'));
            }

            $family_id = intval($_POST['family_id']);
            $result = Olama_School_Family::delete_family($family_id);

            if (is_wp_error($result)) {
                set_transient('olama_admin_error', $result->get_error_message(), 30);
            } else {
                set_transient('olama_admin_message', __('Family deleted successfully.', 'olama-school'), 30);
            }

            wp_redirect(admin_url('admin.php?page=olama-school-users&tab=families'));
            exit;
        }
    }

    /**
     * Handle Attendance Save from Admin
     */
    public function handle_attendance_save()
    {
        if (isset($_POST['olama_save_bulk_attendance']) && check_admin_referer('olama_save_bulk_attendance')) {
            if (!Olama_School_Permissions::can('olama_manage_attendance')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }
            $date = Olama_School_Helpers::sanitize_date($_POST['attendance_date']);
            $section_id = intval($_POST['section_id']);
            $academic_year_id = intval($_POST['academic_year_id']);
            $semester_id = intval($_POST['semester_id']);
            $attendance_data = $_POST['attendance'] ?? array();

            global $wpdb;
            $table = $wpdb->prefix . 'olama_attendance';

            // Ensure table exists
            $table_sheets = $wpdb->prefix . 'olama_attendance_sheets';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_sheets'") !== $table_sheets) {
                $olama_db = new Olama_School_DB();
                $olama_db->create_tables();
            }

            foreach ($attendance_data as $student_id => $data) {
                $status = sanitize_text_field($data['status'] ?? 'present');
                $reason = sanitize_text_field($data['reason'] ?? '');

                // Fetch student_uid for stable linkage
                $student_uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
                    $student_id
                ));

                $res = $wpdb->query($wpdb->prepare(
                    "INSERT INTO $table (student_id, student_uid, academic_year_id, semester_id, section_id, attendance_date, status, reason, recorded_by)
                    VALUES (%d, %s, %d, %d, %d, %s, %s, %s, %d)
                    ON DUPLICATE KEY UPDATE status = %s, student_uid = %s, reason = %s, recorded_by = %d",
                    $student_id,
                    $student_uid,
                    $academic_year_id,
                    $semester_id,
                    $section_id,
                    $date,
                    $status,
                    $reason,
                    get_current_user_id(),
                    $status,
                    $student_uid,
                    $reason,
                    get_current_user_id()
                ));

                if ($res === false) {
                    error_log("Olama Attendance: Failed to save attendance for student $student_id on date $date. DB Error: " . $wpdb->last_error);
                }
            }

            // Also mark the attendance sheet as completed for this section/date
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
                VALUES (%d, %d, %s, %d, 'completed')
                ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
                $academic_year_id,
                $section_id,
                $date,
                get_current_user_id(),
                get_current_user_id()
            ));

            wp_redirect(add_query_arg('message', 'attendance_saved', wp_get_referer()));
            exit;
        }
    }

    /**
     * Handle Cleaning Log Save
     */
    public function handle_cleaning_save()
    {
        if (isset($_POST['olama_save_cleaning_log']) && check_admin_referer('olama_save_cleaning_log')) {
            if (!Olama_School_Permissions::can('olama_manage_cleaning')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'olama_cleaning_logs';

            // Dynamic table check 
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                $olama_db = new Olama_School_DB();
                $olama_db->create_tables();
            }

            $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
            $floor_id = intval($_POST['floor_id']);
            $floor_name = sanitize_text_field($_POST['floor_name']);
            $cleaning_date = Olama_School_Helpers::sanitize_date($_POST['cleaning_date']);
            $slot_id = intval($_POST['slot_id']);
            $slot_time = sanitize_text_field($_POST['slot_time']);
            $cleaner_id = intval($_POST['cleaner_id']);
            $cleaner_name = sanitize_text_field($_POST['cleaner_name']);
            $checkpoints = $_POST['checkpoints'] ?? array();
            
            $recorded_by_name = Olama_School_Helpers::get_user_display_name(get_current_user_id());

            $data = array(
                'academic_year_id' => $academic_year_id,
                'floor_id' => $floor_id,
                'floor_name' => $floor_name,
                'cleaning_date' => $cleaning_date,
                'slot_id' => $slot_id,
                'slot_time' => $slot_time,
                'cleaner_id' => $cleaner_id,
                'cleaner_name' => $cleaner_name,
                'checkpoints_data' => json_encode($checkpoints),
                'recorded_by' => get_current_user_id(),
                'recorded_by_name' => $recorded_by_name
            );

            // Check for existing record for THIS slot on THIS date/floor
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE floor_id = %d AND cleaning_date = %s AND slot_id = %d",
                $floor_id,
                $cleaning_date,
                $slot_id
            ));

            if ($existing_id) {
                $wpdb->update($table, $data, array('id' => $existing_id));
            } else {
                $wpdb->insert($table, $data);
            }

            $redirect_url = admin_url('admin.php?page=olama-school-follow-up&tab=cleaning');
            $redirect_url = add_query_arg(array(
                'floor_id' => $floor_id,
                'cleaning_date' => $cleaning_date,
                'slot_id' => $slot_id,
                'message' => 'cleaning_saved'
            ), $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle Cleaning Module Configuration Save
     */
    public function handle_cleaning_config_save()
    {
        if (isset($_POST['olama_save_cleaning_config']) && check_admin_referer('olama_save_cleaning_config')) {
            if (!Olama_School_Permissions::can('olama_configure_cleaning')) {
                wp_die(__('Unauthorized', 'olama-school'));
            }

            global $wpdb;

            // Dynamic table check to ensure they exist
            $test_table = $wpdb->prefix . 'olama_cleaning_items';
            if ($wpdb->get_var("SHOW TABLES LIKE '$test_table'") !== $test_table) {
                $olama_db = new Olama_School_DB();
                $olama_db->create_tables();
            }

            $type = sanitize_text_field($_POST['config_type']);

            if ($type === 'items') {
                $table = $wpdb->prefix . 'olama_cleaning_items';
                if (!empty($_POST['new_item'])) {
                    $wpdb->insert($table, array('item_name' => sanitize_text_field($_POST['new_item'])));
                }
                if (isset($_POST['delete_item'])) {
                    $wpdb->delete($table, array('id' => intval($_POST['delete_item'])));
                }
            } elseif ($type === 'floors') {
                $table = $wpdb->prefix . 'olama_cleaning_floors';
                if (!empty($_POST['new_floor'])) {
                    $wpdb->insert($table, array('floor_name' => sanitize_text_field($_POST['new_floor'])));
                }
                if (isset($_POST['delete_floor'])) {
                    $wpdb->delete($table, array('id' => intval($_POST['delete_floor'])));
                }
            } elseif ($type === 'cleaners') {
                $table = $wpdb->prefix . 'olama_cleaning_cleaners';
                if (!empty($_POST['new_cleaner'])) {
                    $wpdb->insert($table, array('cleaner_name' => sanitize_text_field($_POST['new_cleaner'])));
                }
                if (isset($_POST['delete_cleaner'])) {
                    $wpdb->delete($table, array('id' => intval($_POST['delete_cleaner'])));
                }
            } elseif ($type === 'slots') {
                $table = $wpdb->prefix . 'olama_cleaning_slots';
                if (!empty($_POST['new_slot'])) {
                    $wpdb->insert($table, array('slot_time' => sanitize_text_field($_POST['new_slot'])));
                }
                if (isset($_POST['delete_slot'])) {
                    $wpdb->delete($table, array('id' => intval($_POST['delete_slot'])));
                }
            } elseif ($type === 'assignments') {
                $table = $wpdb->prefix . 'olama_cleaning_assignments';
                $floor_id = intval($_POST['floor_id']);
                $cleaner_id = intval($_POST['cleaner_id']);
                $supervisor_id = intval($_POST['supervisor_id'] ?? 0);
                
                // Clear existing and re-insert
                $wpdb->delete($table, array('floor_id' => $floor_id));
                if ($cleaner_id || $supervisor_id) {
                    $insert_data = array(
                        'floor_id' => $floor_id, 
                        'cleaner_id' => $cleaner_id, // NOT NULL in DB, 0 is safe
                    );
                    if ($supervisor_id) {
                        $insert_data['supervisor_id'] = $supervisor_id;
                    } else {
                        $insert_data['supervisor_id'] = null; // DEFAULT NULL
                    }
                    $wpdb->insert($table, $insert_data);
                }
            }

            wp_redirect(admin_url('admin.php?page=olama-school-follow-up&tab=cleaning&view=config&section=' . $type));
            exit;
        }
    }

    /**
     * AJAX: Save Attendance (for Teachers/Real-time)
     */
    public function ajax_save_attendance()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_attendance')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $student_id = intval($_POST['student_id']);
        $status = sanitize_text_field($_POST['status']);
        $date = Olama_School_Helpers::sanitize_date($_POST['date']);
        $section_id = intval($_POST['section_id'] ?? 0);
        $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
        $semester_id = intval($_POST['semester_id'] ?? 0);

        if (!$student_id || !$date) {
            wp_send_json_error('Missing parameters');
        }

        // Auto-fetch active parameters if not provided
        if (!$academic_year_id || !$semester_id || !$section_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $academic_year_id ?: ($active_year ? $active_year->id : 0);
            $active_sem = Olama_School_Academic::get_active_semester($academic_year_id);
            $semester_id = $semester_id ?: ($active_sem ? $active_sem->id : 0);

            if (!$section_id) {
                $enrollment = Olama_School_Student::get_student_enrollment($student_id, $academic_year_id);
                $section_id = $enrollment ? $enrollment->section_id : 0;
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'olama_attendance';

        // Check if table exists
        $table_sheets = $wpdb->prefix . 'olama_attendance_sheets';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_sheets'") !== $table_sheets) {
            $olama_db = new Olama_School_DB();
            $olama_db->create_tables();
        }

        // Fetch student_uid for stable linkage
        $student_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
            $student_id
        ));

        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (student_id, student_uid, academic_year_id, semester_id, section_id, attendance_date, status, recorded_by)
            VALUES (%d, %s, %d, %d, %d, %s, %s, %d)
            ON DUPLICATE KEY UPDATE status = %s, student_uid = %s, recorded_by = %d",
            $student_id,
            $student_uid,
            $academic_year_id,
            $semester_id,
            $section_id,
            $date,
            $status,
            get_current_user_id(),
            $status,
            $student_uid,
            get_current_user_id()
        ));

        if ($result !== false) {
            // Also mark the attendance sheet as completed for this section/date
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
                VALUES (%d, %d, %s, %d, 'completed')
                ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
                $academic_year_id,
                $section_id,
                $date,
                get_current_user_id(),
                get_current_user_id()
            ));

            wp_send_json_success();
        } else {
            error_log("Olama Attendance AJAX: Failed to save attendance for student $student_id on date $date. DB Error: " . $wpdb->last_error);
            wp_send_json_error(__('Database error', 'olama-school'));
        }
    }

    /**
     * AJAX: Mark ALL students in a section as present for today
     */
    public function ajax_mark_all_present()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_attendance')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $section_id = intval($_POST['section_id'] ?? 0);
        $date = Olama_School_Helpers::sanitize_date($_POST['date']);
        $academic_year_id = intval($_POST['academic_year_id'] ?? 0);

        if (!$section_id || !$date) {
            wp_send_json_error('Missing parameters');
        }

        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'olama_attendance';

        // 1. Get all students in this section
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.student_uid 
             FROM {$wpdb->prefix}olama_student_enrollment e
             JOIN {$wpdb->prefix}olama_students s ON e.student_uid = s.student_uid
             WHERE e.section_id = %d AND e.academic_year_id = %d AND e.status = 'active'",
            $section_id,
            $academic_year_id
        ));

        // 2. Clear existing attendance records for this section/date (so we reset to All Present)
        $wpdb->delete($table, array(
            'section_id' => $section_id,
            'attendance_date' => $date
        ));

        // Note: In this system, "present" seems to be the default state if no record exists.
        // However, we want to record that the sheet IS completed.
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
            VALUES (%d, %d, %s, %d, 'completed')
            ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
            $academic_year_id,
            $section_id,
            $date,
            get_current_user_id(),
            get_current_user_id()
        ));

        wp_send_json_success();
    }

    /**
     * Render Follow Up Page
     */
    public function render_follow_up_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'student_attendance';
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/follow-up.php';
    }

    /**
     * Render Daily Absence Report
     */
    public function render_daily_absence_report()
    {
        $date = isset($_GET['attendance_date']) ? sanitize_text_field($_GET['attendance_date']) : current_time('Y-m-d');

        global $wpdb;
        $table = $wpdb->prefix . 'olama_attendance';
        $students_table = $wpdb->prefix . 'olama_students';
        $sections_table = $wpdb->prefix . 'olama_sections';
        $grades_table = $wpdb->prefix . 'olama_grades';

        $absentees = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.student_name, s.student_uid, sec.section_name, g.grade_name, sec.grade_id
            FROM $table a
            JOIN $students_table s ON a.student_uid = s.student_uid
            JOIN $sections_table sec ON a.section_id = sec.id
            JOIN $grades_table g ON sec.grade_id = g.id
            WHERE a.attendance_date = %s AND a.status = 'absent'
            ORDER BY g.id, sec.id, s.student_name",
            $date
        ));

        // Get sections that haven't taken attendance yet
        $active_year = Olama_School_Academic::get_active_year();
        $pending_sections = array();
        if ($active_year) {
            $pending_sections = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, g.grade_name, s.section_name 
                FROM $sections_table s 
                JOIN $grades_table g ON s.grade_id = g.id 
                WHERE s.academic_year_id = %d 
                AND s.id NOT IN (
                    SELECT section_id FROM {$wpdb->prefix}olama_attendance_sheets 
                    WHERE academic_year_id = %d AND attendance_date = %s
                )
                ORDER BY g.grade_level, s.section_name",
                $active_year->id, $active_year->id, $date
            ));
            
            // Get sections with "All Present" (completed but no absentees in $absentees)
            $all_present_sections = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, g.grade_name, s.section_name 
                FROM $sections_table s 
                JOIN $grades_table g ON s.grade_id = g.id 
                JOIN {$wpdb->prefix}olama_attendance_sheets sh ON s.id = sh.section_id
                WHERE s.academic_year_id = %d AND sh.attendance_date = %s
                AND s.id NOT IN (
                    SELECT DISTINCT section_id FROM $table 
                    WHERE attendance_date = %s AND status = 'absent'
                )
                ORDER BY g.grade_level, s.section_name",
                $active_year->id, $date, $date
            ));
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/report-daily-absence.php';
    }

    /**
     * Render Detailed Attendance Report
     */
    public function render_detailed_attendance_report()
    {
        $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : current_time('Y-m-d');

        $attendance = array();
        if ($student_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'olama_attendance';
            $students_table = $wpdb->prefix . 'olama_students';
            $attendance = $wpdb->get_results($wpdb->prepare(
                "SELECT a.* FROM $table a 
                 JOIN $students_table s ON a.student_uid = s.student_uid
                 WHERE s.id = %d AND a.attendance_date BETWEEN %s AND %s 
                 ORDER BY a.attendance_date DESC",
                $student_id,
                $start_date,
                $end_date
            ));
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/report-detailed-attendance.php';
    }

    /**
     * Render Family Gateway Settings Content
     */
    public function render_family_gateway_settings_content()
    {
        $settings = get_option('olama_school_settings', array());
        $is_admin = current_user_can('manage_options');

        $default_services = Olama_School_Helpers::get_default_gateway_services();

        $services = $settings['fg_services'] ?? $default_services;
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('olama_school_settings_group');
            ?>
            <div class="olama-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="margin: 0; font-weight: 700; color: #1e293b;"><?php echo Olama_School_Helpers::translate('Gateway Services Management'); ?></h2>
                    <button type="button" class="button button-primary" id="add-fg-service">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px; margin-right: 5px;"></span>
                        <?php echo Olama_School_Helpers::translate('Add New Service'); ?>
                    </button>
                </div>

                <div id="fg-services-container">
                    <?php foreach ($services as $index => $service): ?>
                        <div class="fg-service-row" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; position: relative;">
                            <button type="button" class="remove-fg-service" style="position: absolute; top: 10px; left: 10px; background: none; border: none; color: #ef4444; cursor: pointer;" title="<?php echo Olama_School_Helpers::translate('Remove'); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                <div>
                                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Service Title (Arabic)'); ?></label>
                                    <input type="text" name="olama_school_settings[fg_services][<?php echo $index; ?>][title_ar]" value="<?php echo esc_attr($service['title_ar']); ?>" class="widefat" />
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Service Title (English)'); ?></label>
                                    <input type="text" name="olama_school_settings[fg_services][<?php echo $index; ?>][title_en]" value="<?php echo esc_attr($service['title_en']); ?>" class="widefat" />
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Page URL'); ?></label>
                                    <input type="text" name="olama_school_settings[fg_services][<?php echo $index; ?>][url]" value="<?php echo esc_attr($service['url']); ?>" class="widefat" placeholder="/example-page/" />
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Material Icon'); ?></label>
                                        <input type="text" name="olama_school_settings[fg_services][<?php echo $index; ?>][icon]" value="<?php echo esc_attr($service['icon']); ?>" class="widefat" placeholder="quiz" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #6366f1;"><?php echo Olama_School_Helpers::translate('Required Shortcode'); ?></label>
                                        <input type="text" name="olama_school_settings[fg_services][<?php echo $index; ?>][shortcode]" value="<?php echo esc_attr($service['shortcode']); ?>" class="widefat" style="border-color: #a5b4fc; background: #f5f3ff;" placeholder="[shortcode_tag]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; text-align: left;">
                    <?php submit_button(); ?>
                </div>
            </div>

            <script>
                jQuery(document).ready(function($) {
                    var serviceIndex = <?php echo count($services); ?>;
                    
                    $('#add-fg-service').on('click', function() {
                        var template = `
                            <div class="fg-service-row" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; position: relative; opacity: 0; transform: translateY(10px); transition: all 0.3s ease;">
                                <button type="button" class="remove-fg-service" style="position: absolute; top: 10px; left: 10px; background: none; border: none; color: #ef4444; cursor: pointer;">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Service Title (Arabic)'); ?></label>
                                        <input type="text" name="olama_school_settings[fg_services][${serviceIndex}][title_ar]" value="" class="widefat" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Service Title (English)'); ?></label>
                                        <input type="text" name="olama_school_settings[fg_services][${serviceIndex}][title_en]" value="" class="widefat" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Page URL'); ?></label>
                                        <input type="text" name="olama_school_settings[fg_services][${serviceIndex}][url]" value="" class="widefat" placeholder="/example-page/" />
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                        <div>
                                            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #475569;"><?php echo Olama_School_Helpers::translate('Material Icon'); ?></label>
                                            <input type="text" name="olama_school_settings[fg_services][${serviceIndex}][icon]" value="assignment" class="widefat" />
                                        </div>
                                        <div>
                                            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #6366f1;"><?php echo Olama_School_Helpers::translate('Required Shortcode'); ?></label>
                                            <input type="text" name="olama_school_settings[fg_services][${serviceIndex}][shortcode]" value="" class="widefat" style="border-color: #a5b4fc; background: #f5f3ff;" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        var $row = $(template);
                        $('#fg-services-container').append($row);
                        
                        // Force layout then animate
                        setTimeout(function() {
                            $row.css({ 'opacity': '1', 'transform': 'translateY(0)' });
                        }, 50);
                        
                        serviceIndex++;
                    });
                    
                    $(document).on('click', '.remove-fg-service', function() {
                        if (confirm('<?php echo esc_js(Olama_School_Helpers::translate('Are you sure you want to remove this service?')); ?>')) {
                            $(this).closest('.fg-service-row').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    });
                });
            </script>
        </form>
        <?php
    }
}
