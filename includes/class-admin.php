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
        add_action('admin_init', array($this, 'handle_teacher_settings_save'));
        add_action('admin_init', array($this, 'handle_kg_curriculum_actions'));
        add_action('admin_init', array($this, 'handle_kg_evaluation_save'));
        add_action('admin_init', array($this, 'handle_kg_report_print'));
        add_action('admin_init', array($this, 'handle_family_actions'));
        add_action('wp_ajax_olama_kg_autosave', array($this, 'ajax_kg_autosave'));
        add_action('wp_ajax_olama_save_exam', array($this, 'ajax_save_exam'));
        add_action('wp_ajax_olama_get_semesters', array($this, 'ajax_get_semesters'));
        add_action('wp_ajax_olama_get_subjects', array($this, 'ajax_get_subjects'));
        add_action('wp_ajax_olama_get_student_history', array($this, 'ajax_get_enrollment_history'));
        add_action('wp_ajax_olama_handle_plan_approval', array($this, 'ajax_handle_plan_approval'));
        add_action('wp_ajax_olama_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_olama_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_olama_get_family_students', array($this, 'ajax_get_family_students'));
        add_action('admin_init', array($this, 'restrict_teacher_access'));
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

            if ($section_id && $semester_id) {
                $schedule = Olama_School_Schedule::get_schedule($section_id, $semester_id);
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
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $schedule_data = $_POST['schedule'] ?? [];

            Olama_School_Schedule::save_bulk_schedule($section_id, $semester_id, $schedule_data);

            // Clear WordPress object cache to ensure fresh data on redirect
            wp_cache_flush();

            $url = add_query_arg(array(
                'grade_id' => intval($_POST['grade_id']),
                'section_id' => $section_id,
                'semester_id' => $semester_id,
                'message' => 'schedule_saved'
            ), admin_url('admin.php?page=olama-school-plans&tab=schedule'));

            wp_redirect($url);
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_full_schedule' && isset($_GET['section_id']) && isset($_GET['semester_id'])) {
            check_admin_referer('olama_delete_full_schedule');
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}olama_schedule", array(
                'section_id' => intval($_GET['section_id']),
                'semester_id' => intval($_GET['semester_id'])
            ));

            wp_redirect(remove_query_arg(array('action', 'section_id', 'semester_id', '_wpnonce')));
            exit;
        }
        // Handle Schedule Import
        if (isset($_POST['olama_import_schedule']) && check_admin_referer('olama_import_schedule')) {
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
                                    'subject_id' => $subject_id
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
     * Handle Teacher Settings Save (Language only)
     */
    public function handle_teacher_settings_save()
    {
        if (isset($_POST['olama_teacher_save']) && check_admin_referer('olama_teacher_settings_save', 'olama_teacher_settings_nonce')) {
            if (!current_user_can('teacher') && !current_user_can('manage_options')) {
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
            'olama_view_plans',
            'olama-school',
            array($this, 'render_dashboard_page'),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Dashboard'),
            Olama_School_Helpers::translate('Dashboard'),
            'olama_view_plans',
            'olama-school',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Reports'),
            Olama_School_Helpers::translate('Reports'),
            'olama_view_reports',
            'olama-school-reports',
            array($this, 'render_reports_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Weekly Plan Management'),
            Olama_School_Helpers::translate('Weekly Plan Management'),
            'olama_view_plans',
            'olama-school-plans',
            array($this, 'render_weekly_plan_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Academic Management'),
            Olama_School_Helpers::translate('Academic Management'),
            'olama_view_plans', // Changed from olama_manage_academic_structure to allow teachers access
            'olama-school-academic',
            array($this, 'render_academic_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Curriculum Management'),
            Olama_School_Helpers::translate('Curriculum Management'),
            'olama_manage_curriculum',
            'olama-school-curriculum',
            array($this, 'render_curriculum_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Exam Management'),
            Olama_School_Helpers::translate('Exam Management'),
            'olama_manage_academic_structure',
            'olama-school-exams',
            array($this, 'render_exam_management_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Evaluation'),
            Olama_School_Helpers::translate('Evaluation'),
            'olama_view_plans', // View plans cap is often used for teachers
            'olama-school-evaluation',
            array($this, 'render_evaluation_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Users & Permissions'),
            Olama_School_Helpers::translate('Users & Permissions'),
            'olama_manage_settings',
            'olama-school-users',
            array($this, 'render_users_page')
        );

        add_submenu_page(
            'olama-school',
            Olama_School_Helpers::translate('Settings'),
            Olama_School_Helpers::translate('Settings'),
            'olama_view_plans',
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

        wp_enqueue_style('olama-admin-style', OLAMA_SCHOOL_URL . 'assets/css/admin.css', array(), OLAMA_SCHOOL_VERSION);
        wp_enqueue_style('jquery-ui-datepicker-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

        if (Olama_School_Helpers::is_arabic()) {
            wp_enqueue_style('olama-admin-rtl', OLAMA_SCHOOL_URL . 'assets/css/admin-rtl.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('olama-admin-script', OLAMA_SCHOOL_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-datepicker'), OLAMA_SCHOOL_VERSION, true);

        wp_localize_script('olama-admin-script', 'olamaAdmin', array(
            'dateFormat' => 'dd-mm-yy',
            'isArabic' => Olama_School_Helpers::is_arabic(),
        ));

        $page = $_GET['page'] ?? '';

        if ($page === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-script', OLAMA_SCHOOL_URL . 'assets/js/plan.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            $active_year = Olama_School_Academic::get_active_year();
            $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

            // Calculate semester_id based on selected date to ensure AJAX works correctly
            $today = time();
            $today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
            $week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;
            $active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : 'Sunday';

            $days_map = array('Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4);
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
                    'selectUnit' => Olama_School_Helpers::translate('Select Unit'),
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
                    'sending' => Olama_School_Helpers::translate('Sending...'),
                    'approving' => Olama_School_Helpers::translate('Approving...'),
                    'enterFeedback' => Olama_School_Helpers::translate('Please enter some feedback.'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum') {
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

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum' && isset($_GET['tab']) && $_GET['tab'] === 'timeline') {
            wp_enqueue_style('olama-timeline-style', OLAMA_SCHOOL_URL . 'assets/css/timeline.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
            wp_enqueue_script('olama-timeline-script', OLAMA_SCHOOL_URL . 'assets/js/timeline.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-timeline-script', 'olamaTimeline', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'curriculumNonce' => wp_create_nonce('olama_curriculum_nonce'),
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
                    'errorOccurred' => Olama_School_Helpers::translate('Error occurred'),
                    'communicationError' => Olama_School_Helpers::translate('Communication error'),
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
        $can_manage = current_user_can('olama_manage_academic_structure');

        // If teacher and no specific tab, default to office_hours
        if (!$can_manage && !isset($_GET['tab'])) {
            $active_tab = 'office_hours';
        }
        ?>
                <div class="wrap olama-school-wrap">
                    <h1>
                        <?php _e('Academic Management', 'olama-school'); ?>
                    </h1>

                    <h2 class="nav-tab-wrapper">
                        <?php if ($can_manage): ?>
                                <a href="?page=olama-school-academic&tab=calendar"
                                    class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>">
                                    <?php _e('Academic Calendar', 'olama-school'); ?>
                                </a>
                                <a href="?page=olama-school-academic&tab=grades"
                                    class="nav-tab <?php echo $active_tab === 'grades' ? 'nav-tab-active' : ''; ?>">
                                    <?php _e('Grades & Sections', 'olama-school'); ?>
                                </a>
                                <a href="?page=olama-school-academic&tab=subjects"
                                    class="nav-tab <?php echo $active_tab === 'subjects' ? 'nav-tab-active' : ''; ?>">
                                    <?php _e('Subjects', 'olama-school'); ?>
                                </a>
                                <a href="?page=olama-school-academic&tab=assign_teachers"
                                    class="nav-tab <?php echo $active_tab === 'assign_teachers' ? 'nav-tab-active' : ''; ?>">
                                    <?php _e('Assign Teachers to Subjects', 'olama-school'); ?>
                                </a>
                                <a href="?page=olama-school-academic&tab=stationary"
                                    class="nav-tab <?php echo $active_tab === 'stationary' ? 'nav-tab-active' : ''; ?>">
                                    <?php echo Olama_School_Helpers::translate('Stationary'); ?>
                                </a>
                        <?php endif; ?>
                        <a href="?page=olama-school-academic&tab=office_hours"
                            class="nav-tab <?php echo $active_tab === 'office_hours' ? 'nav-tab-active' : ''; ?>">
                            <?php _e('Office Hours', 'olama-school'); ?>
                        </a>
                    </h2>

                    <div class="olama-tab-content" style="margin-top: 20px;">
                        <?php
                        switch ($active_tab) {
                            case 'grades':
                                if ($can_manage)
                                    $this->render_grades_page_content();
                                break;
                            case 'subjects':
                                if ($can_manage)
                                    $this->render_subjects_page_content();
                                break;
                            case 'assign_teachers':
                                if ($can_manage)
                                    $this->render_teacher_assignments_page_content();
                                break;
                            case 'stationary':
                                if ($can_manage)
                                    $this->render_stationary_page_content();
                                break;
                            case 'office_hours':
                                $this->render_teacher_office_hours_page_content();
                                break;
                            case 'calendar':
                            default:
                                if ($can_manage) {
                                    $this->render_academic_page_content();
                                } else {
                                    $this->render_teacher_office_hours_page_content();
                                }
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
                // Allow HTML in error messages for the "Force Delete" link
                echo '<div class="' . $msg_type . ' is-dismissible"><p>' . Olama_School_Helpers::translate($message) . '</p></div>';
            }
        }

        $selected_year_id = isset($_GET['manage_year']) ? intval($_GET['manage_year']) : 0;
        $years = Olama_School_Academic::get_years();
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
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : (!empty($semesters) ? $semesters[0]->id : 0);

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $subjects = $selected_grade_id ? Olama_School_Subject::get_subjects_by_grade($selected_grade_id) : array();
        $selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

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

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'families'; // Default to families

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
     * Render unified Curriculum Management page with tabs
     */
    public function render_curriculum_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'curriculum';
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
                        <a href="<?php echo esc_url(add_query_arg(array_merge(array('page' => 'olama-school-curriculum', 'tab' => 'curriculum'), array_filter($base_params)), admin_url('admin.php'))); ?>"
                            class="nav-tab <?php echo $active_tab === 'curriculum' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Curriculum'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array_merge(array('page' => 'olama-school-curriculum', 'tab' => 'timeline'), array_filter($base_params)), admin_url('admin.php'))); ?>"
                            class="nav-tab <?php echo $active_tab === 'timeline' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Timeline'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array_merge(array('page' => 'olama-school-curriculum', 'tab' => 'bulk_upload'), array_filter($base_params)), admin_url('admin.php'))); ?>"
                            class="nav-tab <?php echo $active_tab === 'bulk_upload' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Bulk Upload'); ?>
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array_merge(array('page' => 'olama-school-curriculum', 'tab' => 'analysis'), array_filter($base_params)), admin_url('admin.php'))); ?>"
                            class="nav-tab <?php echo $active_tab === 'analysis' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Analysis'); ?>
                        </a>
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
                            default:
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
        ?>
                <div class="wrap olama-school-wrap">
                    <h1>
                        <?php echo Olama_School_Helpers::translate('Exam Management'); ?>
                    </h1>

                    <h2 class="nav-tab-wrapper">
                        <a href="?page=olama-school-exams&tab=exam_schedule"
                            class="nav-tab <?php echo $active_tab === 'exam_schedule' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Exam Schedule'); ?>
                        </a>
                    </h2>

                    <div class="olama-tab-content" style="margin-top: 20px;">
                        <?php
                        switch ($active_tab) {
                            case 'exam_schedule':
                            default:
                                $this->render_exam_schedule_content();
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
        $is_admin = current_user_can('manage_options');
        ?>
                <div class="wrap olama-school-wrap">
                    <h1>
                        <?php echo Olama_School_Helpers::translate('Evaluation'); ?>
                    </h1>

                    <h2 class="nav-tab-wrapper">
                        <a href="?page=olama-school-evaluation&tab=student_evaluation"
                            class="nav-tab <?php echo $active_tab === 'student_evaluation' ? 'nav-tab-active' : ''; ?>">
                            <?php echo Olama_School_Helpers::translate('Student Evaluation'); ?>
                        </a>

                        <?php if ($is_admin): ?>
                                <a href="?page=olama-school-evaluation&tab=evaluation_mgmt"
                                    class="nav-tab <?php echo $active_tab === 'evaluation_mgmt' ? 'nav-tab-active' : ''; ?>">
                                    <?php echo Olama_School_Helpers::translate('Evaluation Management'); ?>
                                </a>
                        <?php endif; ?>
                    </h2>

                    <div class="olama-tab-content" style="margin-top: 20px;">
                        <?php
                        switch ($active_tab) {
                            case 'evaluation_mgmt':
                                if ($is_admin)
                                    $this->render_evaluation_mgmt_page_content();
                                break;
                            case 'student_evaluation':
                            default:
                                $this->render_student_evaluation_page_content();
                                break;
                        }
                        ?>
                    </div>
                </div>
                <?php
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
    public function render_student_evaluation_page_content()
    {
        $form = new Olama_School_EV_Form();
        $form->render_page();
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
                <div class="wrap olama-school-wrap">
                    <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                        <?php _e('Plugin Settings', 'olama-school'); ?>
                    </h1>

                    <h2 class="nav-tab-wrapper">
                        <a href="?page=olama-school-settings&tab=general"
                            class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                            <?php _e('General Settings', 'olama-school'); ?>
                        </a>
                        <?php if (current_user_can('manage_options')): ?>
                                <a href="?page=olama-school-settings&tab=shortcode"
                                    class="nav-tab <?php echo $active_tab === 'shortcode' ? 'nav-tab-active' : ''; ?>">
                                    <?php _e('Shortcode Generator', 'olama-school'); ?>
                                </a>
                        <?php endif; ?>
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
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'creation';
        ?>
                <div class="wrap olama-school-wrap">
                    <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                        <?php _e('Weekly Plan Management', 'olama-school'); ?>
                    </h1>

                    <h2 class="nav-tab-wrapper">
                        <?php
                        $base_params = array(
                            'academic_year_id' => isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0,
                            'semester_id' => isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0,
                            'grade_id' => isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0,
                            'section_id' => isset($_GET['section_id']) ? intval($_GET['section_id']) : 0,
                            'plan_month' => isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '',
                            'week_start' => isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '',
                        );

                        // For comparison tab, we might have different param names, but let's keep it simple for now and align the main ones
                        $tabs = array(
                            'creation' => __('Plan Creation', 'olama-school'),
                            'list' => __('Plan List', 'olama-school'),
                            'comparison' => __('Plan Comparison', 'olama-school'),
                            'schedule' => __('Weekly Schedule', 'olama-school'),
                        );

                        if (current_user_can('manage_options')) {
                            $tabs['data'] = __('Data Management', 'olama-school');
                        }

                        $tabs = array_merge($tabs, array(
                            'load' => __('Plan Load', 'olama-school'),
                            'coverage' => __('Curriculum Coverage', 'olama-school'),
                        ));

                        foreach ($tabs as $tab_slug => $tab_label):
                            $url = add_query_arg(array_merge(array('page' => 'olama-school-plans', 'tab' => $tab_slug), array_filter($base_params)), admin_url('admin.php'));
                            ?>
                                <a href="<?php echo esc_url($url); ?>"
                                    class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                                    <?php echo esc_html($tab_label); ?>
                                </a>
                        <?php endforeach; ?>
                    </h2>

                    <div class="olama-tab-content" style="margin-top: 20px;">
                        <?php if ($active_tab === 'creation'): ?>
                                <?php $this->render_plan_page_content(); ?>
                        <?php elseif ($active_tab === 'list'): ?>
                                <?php $this->render_plan_list_page_content(); ?>
                        <?php elseif ($active_tab === 'comparison'): ?>
                                <?php $this->render_comparison_page_content(); ?>
                        <?php elseif ($active_tab === 'schedule'): ?>
                                <?php $this->render_schedule_page_content(); ?>
                        <?php elseif ($active_tab === 'data'): ?>
                                <?php $this->render_data_management_page_content(); ?>
                        <?php elseif ($active_tab === 'load'): ?>
                                <?php $this->render_plan_load_page_content(); ?>
                        <?php elseif ($active_tab === 'coverage'): ?>
                                <?php $this->render_curriculum_coverage_page_content(); ?>
                        <?php endif; ?>
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
            $teacher_id = intval($_POST['teacher_id']);
            $slots = $_POST['slots'] ?? [];

            Olama_School_Teacher::save_office_hours($teacher_id, $slots);

            $url = add_query_arg(array(
                'page' => 'olama-school-academic',
                'tab' => 'office_hours',
                'teacher_id' => $teacher_id,
                'message' => 'office_hours_saved'
            ), admin_url('admin.php'));

            wp_redirect($url);
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

        // Academic Infrastructure
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

        $current_semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : [];
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : 0);

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

        // Date logic: Week start (Sunday) dropdown grouped by month
        $all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id);
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key_start = date('Y-m', strtotime($val));
            $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

            // Check if week ends in a different month (cross-month support)
            $m_key_end = date('Y-m', strtotime($val . ' +4 days'));
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

        // If not valid, default to first week of the month
        if (!$valid_week && !empty($current_month_weeks)) {
            $week_start = $current_month_weeks[0]['val'];
        } elseif (empty($week_start)) {
            $today = time();
            $today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
            $week_start = $today_val;
        }

        $days = array(
            'Sunday' => date('Y-m-d', strtotime($week_start)),
            'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
            'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
            'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
            'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
        );

        $active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : 'Sunday';
        $selected_date = $days[$active_day];

        // Use the selected semester directly for all queries including subject loading
        // This ensures subjects from the schedule (linked to semester_id) are correctly loaded
        $semester_id = $selected_semester_id;

        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));
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

        // Academic Infrastructure
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : 0);

        // Reuse week selection logic
        $all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id);
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key = date('Y-m', strtotime($val));
            $months_weeks[$m_key][] = array('val' => $val, 'label' => $label);
        }

        $today = time();
        $today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
        $initial_week = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;
        $selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : date('Y-m', strtotime($initial_week));

        if (!isset($months_weeks[$selected_month]) && !empty($months_weeks)) {
            $m_keys = array_keys($months_weeks);
            $selected_month = $m_keys[0];
        }

        $current_month_weeks = $months_weeks[$selected_month] ?? array();
        $week_start = $initial_week;
        $valid_week = false;
        foreach ($current_month_weeks as $w) {
            if ($w['val'] === $week_start) {
                $valid_week = true;
                break;
            }
        }
        if (!$valid_week && !empty($current_month_weeks)) {
            $week_start = $current_month_weeks[0]['val'] ?? '';
        }

        $days = array(
            'Sunday' => date('Y-m-d', strtotime($week_start)),
            'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
            'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
            'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
            'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
        );

        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));

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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/reports.php';
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
            'editor' => __('Coordinator/Editor', 'olama-school'),
            'author' => __('Teacher/Author', 'olama-school'),
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
        if (!current_user_can('manage_options')) {
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
        // Check nonce from either the appended 'nonce' param or the form field
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST['olama_exam_nonce_field']) ? $_POST['olama_exam_nonce_field'] : '');

        if (empty($nonce) || !wp_verify_nonce($nonce, 'olama_save_exam')) {
            wp_send_json_error(__('Security check failed.', 'olama-school'));
        }

        if (!current_user_can('manage_options') && !current_user_can('olama_manage_academic')) {
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
        } elseif (!$result) {
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
        $grade_id = intval($_GET['grade_id']);
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

        $next_sunday = date('Y-m-d', strtotime('next Sunday'));
        $next_saturday = date('Y-m-d', strtotime('next Sunday + 6 days'));

        $sections_planned = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_plans WHERE academic_year_id = %d AND plan_date BETWEEN %s AND %s",
            $active_year_id,
            $next_sunday,
            $next_saturday
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
            SELECT id, section_name, grade_id 
            FROM {$wpdb->prefix}olama_sections 
            WHERE academic_year_id = %d
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
                'name' => $sec->section_name,
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
     * AJAX: Handle Plan Approval
     */
    public function ajax_handle_plan_approval()
    {
        global $wpdb;
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('olama_manage_plans')) {
            wp_send_json_error(__('Permission denied.', 'olama-school'));
        }

        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$plan_id || !in_array($new_status, array('approved', 'draft'))) {
            wp_send_json_error(__('Invalid request parameters.', 'olama-school'));
        }

        $result = Olama_School_Plan::update_status($plan_id, $new_status);

        if ($result) {
            // Log the action
            Olama_School_Logger::log(
                sprintf('Plan %s by supervisor', $new_status),
                sprintf('Plan ID %d changed to %s', $plan_id, $new_status)
            );
            // Notify teacher of the status change (Phase 3)
            $plan = $wpdb->get_row($wpdb->prepare("
                SELECT p.teacher_id, s.subject_name 
                FROM {$wpdb->prefix}olama_plans p
                JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
                WHERE p.id = %d
            ", $plan_id));

            if ($plan) {
                $feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';
                $status_label = ($new_status === 'approved') ? __('Approved', 'olama-school') : (($new_status === 'draft') ? __('Rejected/Needs Edits', 'olama-school') : $new_status);

                if (!empty($feedback)) {
                    $msg = sprintf(__('Your plan for %s has been %s. Feedback: %s', 'olama-school'), $plan->subject_name, $status_label, $feedback);
                } else {
                    $msg = sprintf(__('Your plan for %s has been %s.', 'olama-school'), $plan->subject_name, $status_label);
                }

                self::create_notification($plan->teacher_id, 'plan_status', $msg);
            }

            wp_send_json_success(array('message' => sprintf(__('Plan %s successfully.', 'olama-school'), $new_status)));
        } else {
            wp_send_json_error(__('Database error: Could not update plan status.', 'olama-school'));
        }
    }

    /**
     * Get teaching schedule for a specific day
     */
    public static function get_teacher_daily_schedule($teacher_id, $day_name = null)
    {
        global $wpdb;

        if (!$day_name) {
            $day_name = date('l'); // Today's English day name
        }

        $active_year = Olama_School_Academic::get_active_year();
        $active_semester = Olama_School_Academic::get_active_semester();

        if (!$active_year || !$active_semester)
            return array();

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
            ORDER BY sch.period_number ASC
        ", date('Y-m-d'), $teacher_id, $active_year->id, $active_semester->id, $day_name));
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
        if (!current_user_can('teacher') || current_user_can('manage_options')) {
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
        if (!current_user_can('teacher') || current_user_can('manage_options')) {
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
}
