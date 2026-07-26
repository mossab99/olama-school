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
        add_action('admin_init', array($this, 'handle_stationary_save'));
        add_action('admin_init', array($this, 'handle_academic_calendar_actions'));
        add_action('admin_init', array($this, 'handle_subject_actions'));
        add_action('admin_init', array($this, 'handle_backup_restore_actions'));
        add_action('admin_init', array($this, 'handle_teacher_settings_save'));
        add_action('admin_init', array($this, 'handle_family_actions'));
        add_action('wp_ajax_olama_get_semesters', array($this, 'ajax_get_semesters'));
        add_action('wp_ajax_olama_get_subjects', array($this, 'ajax_get_subjects'));
        add_action('wp_ajax_olama_get_student_history', array($this, 'ajax_get_enrollment_history'));
        add_action('wp_ajax_olama_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_olama_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_olama_get_family_students', array($this, 'ajax_get_family_students'));
        add_action('wp_ajax_olama_get_units', array($this, 'ajax_get_units'));
        add_action('wp_ajax_olama_get_lessons', array($this, 'ajax_get_lessons'));
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
            $result = Olama_School_Backup::restore_options($json_data);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

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
                            if (response.success) {
                                addLog(response.data, 'success');
                                alert(response.data);
                                location.reload();
                            } else {
                                addLog('<?php _e('Finalization Error:', 'olama-school'); ?> ' + response.data, 'error');
                                resetUI();
                            }
                        }).fail(function () {
                            addLog('<?php _e('Server timeout during finalization.', 'olama-school'); ?>', 'error');
                            resetUI();
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

        // Exam Hall Distribution assets
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
        $oracle_managed = Olama_School_Academic_Bridge::is_available();
        $blocked_mutation = false;
        if ($oracle_managed) {
            foreach (array('add_grade', 'edit_grade', 'add_section', 'edit_section') as $field) {
                if (isset($_POST[$field])) {
                    unset($_POST[$field]);
                    $blocked_mutation = true;
                }
            }
            if (isset($_GET['action']) && in_array($_GET['action'], array('delete_grade', 'delete_section', 'clear_all_grades'), true)) {
                unset($_GET['action']);
                $blocked_mutation = true;
            }
            if ($blocked_mutation) {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Grades and sections are managed by Oracle. Make structural changes in Oracle, then run Olama Oracle Sync.', 'olama-school')
                    . '</p></div>';
            }
        }

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
        if (Olama_School_Academic_Bridge::is_available()) {
            $has_mutation = isset($_POST['subject_action_type'])
                || (isset($_GET['action']) && in_array($_GET['action'], array('delete_subject', 'clear_all_subjects'), true));
            if ($has_mutation) {
                set_transient(
                    'olama_subject_error',
                    __('Subjects are managed by Oracle. Make structural changes in Oracle, then run Olama Oracle Sync.', 'olama-school'),
                    30
                );
            }
            return;
        }

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
    /**
     * Render unified Users page
     */
    /**
     * Render Transportation Management page
     */
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
    /**
     * Render Evaluation page with tabs
     */
    /**
     * Render Academic Supervision page with tabs
     */
    /**
     * Render Evaluation Progress Tab Content
     */
    /**
     * AJAX: Get students for evaluation progress (Full list with statuses)
     */
    /**
     * AJAX: Get evaluation content for a student
     */
    /**
     * AJAX: Approve evaluation
     */
    /**
     * AJAX: Save supervisor comments
     */
    /**
     * AJAX: Bulk approve evaluations
     */
    /**
     * Render Teacher Exams Tab Content
     */
    /**
     * AJAX: Bulk Add Exam Subjects
     */
    /**
     * AJAX: Get Units for Exam Material selection
     */
    public function ajax_get_units()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline') && !Olama_School_Permissions::can('olama_view_curriculum_timeline')) {
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

        if (!Olama_School_Permissions::can('olama_manage_curriculum_timeline') && !Olama_School_Permissions::can('olama_view_curriculum_timeline')) {
            wp_send_json_error(__('Unauthorized', 'olama-school'));
        }

        $lessons = Olama_School_Lesson::get_lessons(intval($_REQUEST['unit_id']));
        wp_send_json_success($lessons);
    }

    /**
     * Render Evaluation Management Content
     */
    /**
     * Render Student Evaluation Content
     */
    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        $tabs_config = array(
            'general'        => array('label' => __('General Settings', 'olama-school'), 'cap' => 'olama_manage_settings_general'),
            'shortcode'      => array('label' => __('Shortcode Generator', 'olama-school'), 'cap' => 'olama_manage_settings_shortcode'),
            'backup'         => array('label' => __('Backup & Restore', 'olama-school'), 'cap' => 'manage_options'),
            'logs'           => array('label' => __('Logs', 'olama-school'), 'cap' => 'manage_options'),
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
                <?php elseif ($active_tab === 'backup'): ?>
                    <?php $this->render_backup_settings_content(); ?>
                <?php elseif ($active_tab === 'logs'): ?>
                    <?php $this->render_logs_tab_content(); ?>
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
     * Render the unified Logs tab in Plugin Settings.
     * Accessible to manage_options only.
     */
    public function render_logs_tab_content()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to view system logs.', 'olama-school' ) );
        }

        // ── Handle Actions ────────────────────────────────────────────────
        if ( isset( $_POST['olama_clear_logs'] ) && check_admin_referer( 'olama_logs_action', 'olama_logs_nonce' ) ) {
            Olama_System_Logger::clear_all_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'All system logs cleared.', 'olama-school' ) . '</p></div>';
        }

        if ( isset( $_POST['olama_prune_logs'] ) && check_admin_referer( 'olama_logs_action', 'olama_logs_nonce' ) ) {
            $days   = max( 1, intval( $_POST['prune_days'] ?? 30 ) );
            $pruned = Olama_System_Logger::prune_logs( $days );
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Deleted %d log entries older than %d days.', 'olama-school' ), $pruned, $days ) . '</p></div>';
        }

        // ── Download log file ─────────────────────────────────────────────
        if ( isset( $_GET['olama_download_log'] ) && check_admin_referer( 'olama_download_log' ) ) {
            $log_file = Olama_System_Logger::get_log_file_path();
            if ( file_exists( $log_file ) ) {
                header( 'Content-Type: text/plain' );
                header( 'Content-Disposition: attachment; filename="olama-system-' . gmdate( 'Y-m-d' ) . '.log"' );
                readfile( $log_file );
                exit;
            }
        }

        // ── Filters & Pagination ──────────────────────────────────────────
        $filter_source = isset( $_GET['log_source'] ) ? sanitize_key( $_GET['log_source'] ) : '';
        $filter_level  = isset( $_GET['log_level'] ) ? sanitize_key( $_GET['log_level'] ) : '';
        $per_page      = 50;
        $current_page  = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset        = ( $current_page - 1 ) * $per_page;

        $query_args = [
            'source' => $filter_source,
            'level'  => $filter_level,
            'limit'  => $per_page,
            'offset' => $offset,
        ];

        $logs       = Olama_System_Logger::get_logs( $query_args );
        $total      = Olama_System_Logger::count_logs( $query_args );
        $total_pages = max( 1, ceil( $total / $per_page ) );

        $sources = [
            ''             => __( 'All Sources', 'olama-school' ),
            'school'       => __( 'School System', 'olama-school' ),
            'exam-engine'  => __( 'Exam Engine', 'olama-school' ),
            'registration' => __( 'Registration', 'olama-school' ),
        ];

        $levels = [
            ''        => __( 'All Levels', 'olama-school' ),
            'error'   => __( 'Error', 'olama-school' ),
            'warning' => __( 'Warning', 'olama-school' ),
            'info'    => __( 'Info', 'olama-school' ),
            'debug'   => __( 'Debug', 'olama-school' ),
        ];

        $level_colors = [
            'error'   => '#d63638',
            'warning' => '#dba617',
            'info'    => '#2271b1',
            'debug'   => '#787c82',
        ];

        $level_badges = [
            'error'   => 'background:#fce8e8; color:#d63638; border:1px solid #f8b4b4;',
            'warning' => 'background:#fef9e7; color:#8a6d0a; border:1px solid #fcd581;',
            'info'    => 'background:#e8f4fd; color:#2271b1; border:1px solid #a7cdf0;',
            'debug'   => 'background:#f0f0f1; color:#3c434a; border:1px solid #c3c4c7;',
        ];

        $base_page_url = admin_url( 'admin.php?page=olama-school-settings&tab=logs' );
        $log_file_path = Olama_System_Logger::get_log_file_path();
        $log_file_size = file_exists( $log_file_path ) ? size_format( filesize( $log_file_path ) ) : __( 'Not created yet', 'olama-school' );
        ?>

        <style>
        .olama-logs-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .olama-logs-toolbar select, .olama-logs-toolbar input[type="number"] { height:30px; }
        .olama-log-table { border-collapse:collapse; width:100%; font-size:13px; }
        .olama-log-table th { background:#f0f0f1; padding:8px 12px; text-align:left; border-bottom:2px solid #c3c4c7; font-weight:600; }
        .olama-log-table td { padding:7px 12px; border-bottom:1px solid #f0f0f1; vertical-align:top; }
        .olama-log-table tr:hover td { background:#f6f7f7; }
        .olama-log-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .olama-log-source { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; background:#f0f0f1; color:#3c434a; border:1px solid #c3c4c7; }
        .olama-log-message { font-family:monospace; font-size:12px; word-break:break-word; max-width:600px; }
        .olama-log-context { font-family:monospace; font-size:11px; color:#787c82; margin-top:4px; word-break:break-all; }
        .olama-logs-pagination { margin-top:16px; display:flex; align-items:center; gap:8px; }
        .olama-logs-pagination a, .olama-logs-pagination span { padding:4px 10px; border:1px solid #c3c4c7; border-radius:4px; font-size:13px; text-decoration:none; }
        .olama-logs-pagination .current { background:#2271b1; color:#fff; border-color:#2271b1; }
        .olama-log-empty { text-align:center; padding:40px; color:#787c82; }
        #olama-log-refresh-countdown { font-size:12px; color:#787c82; margin-left:auto; }
        </style>

        <div class="olama-logs-header" style="display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:18px; font-weight:700;"><?php _e( 'System Logs', 'olama-school' ); ?></h2>
            <span style="background:#f0f0f1; padding:3px 10px; border-radius:10px; font-size:12px; color:#3c434a;">
                <?php echo number_format( $total ); ?> <?php _e( 'entries', 'olama-school' ); ?>
            </span>
            <span style="font-size:12px; color:#787c82;">
                <?php _e( 'Log file:', 'olama-school' ); ?> <code>olama-system.log</code>
                (<?php echo esc_html( $log_file_size ); ?>)
            </span>
            <?php
            $download_url = wp_nonce_url(
                add_query_arg( 'olama_download_log', '1', $base_page_url ),
                'olama_download_log'
            );
            ?>
            <a href="<?php echo esc_url( $download_url ); ?>" class="button" style="margin-left:auto;">
                <span class="dashicons dashicons-download" style="vertical-align:middle; margin-right:4px;"></span>
                <?php _e( 'Download Log File', 'olama-school' ); ?>
            </a>
            <span id="olama-log-refresh-countdown"><?php _e( 'Auto-refresh in', 'olama-school' ); ?> <strong id="olama-log-seconds">30</strong>s</span>
        </div>

        <!-- Filters -->
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-settings" />
            <input type="hidden" name="tab" value="logs" />
            <div class="olama-logs-toolbar">
                <select name="log_source" id="log-source-filter">
                    <?php foreach ( $sources as $val => $label ): ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_source, $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="log_level" id="log-level-filter">
                    <?php foreach ( $levels as $val => $label ): ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_level, $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php _e( 'Filter', 'olama-school' ); ?></button>
                <a href="<?php echo esc_url( $base_page_url ); ?>" class="button"><?php _e( 'Reset', 'olama-school' ); ?></a>
            </div>
        </form>

        <!-- Log Table -->
        <?php if ( empty( $logs ) ): ?>
            <div class="olama-log-empty">
                <span class="dashicons dashicons-yes-alt" style="font-size:40px; color:#00a32a;"></span>
                <p><?php _e( 'No log entries found for the selected filters. The system is running cleanly.', 'olama-school' ); ?></p>
            </div>
        <?php else: ?>
            <table class="olama-log-table">
                <thead>
                    <tr>
                        <th style="width:160px;"><?php _e( 'Time', 'olama-school' ); ?></th>
                        <th style="width:80px;"><?php _e( 'Level', 'olama-school' ); ?></th>
                        <th style="width:110px;"><?php _e( 'Source', 'olama-school' ); ?></th>
                        <th><?php _e( 'Message', 'olama-school' ); ?></th>
                        <th style="width:60px;"><?php _e( 'User', 'olama-school' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ):
                        $badge_style = $level_badges[ $log->level ] ?? $level_badges['info'];
                        $user_info   = $log->user_id ? get_userdata( $log->user_id ) : null;
                        $user_label  = $user_info ? esc_html( $user_info->display_name ) : ( $log->user_id ? '#' . $log->user_id : '—' );
                    ?>
                    <tr>
                        <td style="white-space:nowrap; color:#787c82; font-size:12px;">
                            <?php echo esc_html( $log->created_at ); ?>
                        </td>
                        <td>
                            <span class="olama-log-badge" style="<?php echo esc_attr( $badge_style ); ?>">
                                <?php echo esc_html( strtoupper( $log->level ) ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="olama-log-source"><?php echo esc_html( $log->source ); ?></span>
                        </td>
                        <td>
                            <div class="olama-log-message"><?php echo esc_html( $log->message ); ?></div>
                            <?php if ( ! empty( $log->context ) ): ?>
                                <div class="olama-log-context"><?php echo esc_html( $log->context ); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap; font-size:12px;"><?php echo $user_label; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ): ?>
            <div class="olama-logs-pagination">
                <?php
                $page_base = add_query_arg( [
                    'page'       => 'olama-school-settings',
                    'tab'        => 'logs',
                    'log_source' => $filter_source,
                    'log_level'  => $filter_level,
                ], admin_url( 'admin.php' ) );

                if ( $current_page > 1 ) {
                    echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $page_base ) ) . '">← ' . __( 'Prev', 'olama-school' ) . '</a>';
                }

                $start = max( 1, $current_page - 2 );
                $end   = min( $total_pages, $current_page + 2 );
                for ( $p = $start; $p <= $end; $p++ ) {
                    if ( $p === $current_page ) {
                        echo '<span class="current">' . $p . '</span>';
                    } else {
                        echo '<a href="' . esc_url( add_query_arg( 'paged', $p, $page_base ) ) . '">' . $p . '</a>';
                    }
                }

                if ( $current_page < $total_pages ) {
                    echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $page_base ) ) . '">' . __( 'Next', 'olama-school' ) . ' →</a>';
                }
                ?>
                <span style="margin-left:auto; font-size:12px; color:#787c82;">
                    <?php printf( __( 'Page %d of %d', 'olama-school' ), $current_page, $total_pages ); ?>
                </span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Maintenance Actions -->
        <div style="margin-top:40px; padding:20px; background:#f9f9f9; border:1px solid #ddd; border-radius:6px;">
            <h3 style="margin-top:0;"><?php _e( 'Log Maintenance', 'olama-school' ); ?></h3>
            <form method="post" style="display:inline-block; margin-right:20px;">
                <?php wp_nonce_field( 'olama_logs_action', 'olama_logs_nonce' ); ?>
                <label><?php _e( 'Delete entries older than', 'olama-school' ); ?>
                    <input type="number" name="prune_days" value="30" min="1" max="365" style="width:60px; margin:0 6px;" />
                    <?php _e( 'days', 'olama-school' ); ?>
                </label>
                <button type="submit" name="olama_prune_logs" class="button button-secondary" style="margin-left:8px;">
                    <?php _e( 'Prune Old Logs', 'olama-school' ); ?>
                </button>
            </form>

            <form method="post" style="display:inline-block;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure? This will permanently delete ALL log entries.', 'olama-school' ); ?>');">
                <?php wp_nonce_field( 'olama_logs_action', 'olama_logs_nonce' ); ?>
                <button type="submit" name="olama_clear_logs" class="button" style="background:#fcf0f1; border-color:#d63638; color:#d63638;">
                    <span class="dashicons dashicons-trash" style="vertical-align:middle; margin-right:4px;"></span>
                    <?php _e( 'Clear All Logs', 'olama-school' ); ?>
                </button>
            </form>
        </div>

        <script>
        (function() {
            // Auto-refresh countdown (30 seconds)
            var seconds = 30;
            var el = document.getElementById('olama-log-seconds');
            if ( ! el ) return;
            var interval = setInterval(function() {
                seconds--;
                el.textContent = seconds;
                if ( seconds <= 0 ) {
                    clearInterval(interval);
                    window.location.reload();
                }
            }, 1000);
        })();
        </script>
        <?php
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
    /**
     * Get cleaning monitoring stats for dashboard
     */
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
        // Don't restrict if user is an administrator or staff member
        if (Olama_School_Permissions::can('olama_view_dashboard') || current_user_can('manage_options')) {
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
        // Keep site-name visible so staff can navigate back to the dashboard
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
    /**
     * Handle Cleaning Log Save
     */
    /**
     * Handle Cleaning Module Configuration Save
     */
    /**
     * AJAX: Save Attendance (for Teachers/Real-time)
     */
    /**
     * AJAX: Mark ALL students in a section as present for today
     */
    /**
     * Render Follow Up Page
     */
    /**
     * Render Daily Absence Report
     */
    /**
     * Render Detailed Attendance Report
     */
    /**
     * Render Family Gateway Settings Content
     */
    /**
     * Render Exam Hall Distribution Page
     */
}
