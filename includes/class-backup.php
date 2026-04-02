<?php
/**
 * Database Backup and Restore Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Backup
{
    /**
     * Get list of all plugin tables
     */
    public static function get_plugin_tables()
    {
        return array(
            // --- Core Setup ---
            'olama_settings',
            'olama_academic_years',
            'olama_semesters',
            'olama_grades',
            'olama_sections',
            'olama_families',
            'olama_students',
            'olama_student_enrollment',
            'olama_subjects',
            'olama_teachers',
            
            // --- Evaluations & Supervision ---
            'olama_ev_templates',
            'olama_ev_domains',
            'olama_ev_categories',
            'olama_ev_indicators',
            'olama_ev_records',
            'olama_ev_scores',
            'olama_supervisor_visits',
            'olama_supervisor_assignments',
            
            // --- Academic Planning ---
            'olama_plans',
            'olama_plan_questions',
            'olama_templates',
            'olama_lesson_plans',
            'olama_schedule',
            'olama_curriculum_units',
            'olama_curriculum_lessons',
            'olama_curriculum_questions',
            'olama_academic_events',
            'olama_teacher_assignments',
            'olama_teacher_office_hours',
            
            // --- Exams (Internal/Manual) ---
            'olama_exams',
            'olama_exam_attachments',
            'olama_semester_exams',
            'olama_stationary',
            
            // --- Exam Engine (Online) ---
            'olama_exam_question_categories',
            'olama_exam_questions',
            'olama_exam_exams',
            'olama_exam_attempts',
            'olama_exam_essay_grades',
            'olama_exam_placement_info',
            
            // --- Student Services ---
            'olama_attendance',
            'olama_attendance_sheets',
            'olama_transport_buses',
            'olama_student_bus_assignments',
            
            // --- Staff Shifts & Cleaning ---
            'olama_shifts_locations',
            'olama_shifts_time_slots',
            'olama_shifts_periods',
            'olama_shifts',
            'olama_shifts_assignments',
            'olama_shifts_schedule',
            'olama_cleaning_logs',
            'olama_cleaning_items',
            'olama_cleaning_floors',
            'olama_cleaning_cleaners',
            'olama_cleaning_slots',
            'olama_cleaning_assignments',
            
            // --- Logs & Preferences ---
            'olama_user_preferences',
            'olama_notifications',
            'olama_logs',

            // --- Core User Tables (Added for data integrity between environments) ---
            'users',
            'usermeta'
        );
    }

    /**
     * Generate backup data as multi-part JSON
     */
    public static function generate_backup()
    {
        // Increase resource limits for large databases
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        global $wpdb;
        $backup_data = array(
            'version' => OLAMA_SCHOOL_VERSION,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
            'parts' => array()
        );

        // --- PART 1: Olama School Core ---
        if (class_exists('Olama_School_DB') && method_exists('Olama_School_DB', 'get_tables')) {
            $tables = Olama_School_DB::get_tables();
            // Include core user data in the main plugin part
            $tables[] = 'users';
            $tables[] = 'usermeta';

            $backup_data['parts']['olama_school'] = array(
                'label' => 'Olama School Core & Users',
                'tables' => self::collect_tables_data($tables)
            );
        }

        // --- PART 2: Olama Exam Engine ---
        if (class_exists('Olama_Exam_DB') && method_exists('Olama_Exam_DB', 'get_tables')) {
            $tables = Olama_Exam_DB::get_tables();
            $backup_data['parts']['olama_exam_engine'] = array(
                'label' => 'Olama Exam Engine',
                'tables' => self::collect_tables_data($tables)
            );
        }

        return $backup_data;
    }

    /**
     * Helper to collect data for a list of tables
     */
    private static function collect_tables_data($tables)
    {
        global $wpdb;
        $data = array();

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
                $data[$table] = $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
            }
        }

        return $data;
    }

    /**
     * Clear a specific table's data
     */
    public static function clear_table_data($table)
    {
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table;

        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        // TRUNCATE auto-commits, use DELETE for transaction safety
        $wpdb->query("DELETE FROM $full_table_name");
        $result = $wpdb->query("ALTER TABLE $full_table_name AUTO_INCREMENT = 1");
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');

        return $result;
    }

    /**
     * Insert a batch of rows into a table
     */
    public static function insert_table_rows($table, $rows)
    {
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table;

        if (!is_array($rows) || empty($rows)) {
            return true;
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($rows as $row) {
                $result = $wpdb->insert($full_table_name, $row);

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }
            $wpdb->query('COMMIT');
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
            error_log('[OLAMA RESTORE ERROR] Insert failed for table ' . $full_table_name . ': ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Restore a specific table's data (Full)
     */
    public static function restore_table_data($table, $rows)
    {
        self::clear_table_data($table);
        return self::insert_table_rows($table, $rows);
    }

    /**
     * Restore backup data from Multi-Part JSON
     */
    public static function restore_backup($json_data)
    {
        global $wpdb;

        error_log('[OLAMA RESTORE] Multi-Part restore_backup() started');

        // Increase limits for large datasets
        @set_time_limit(900); // 15 minutes
        @ini_set('memory_limit', '1024M');

        $json_data = trim($json_data);
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_backup', __('Invalid backup file format (JSON error): ', 'olama-school') . json_last_error_msg());
        }

        // Support both old format (direct tables key) and new format (parts key)
        $parts = array();
        if (isset($data['parts']) && is_array($data['parts'])) {
            $parts = $data['parts'];
        } elseif (isset($data['tables']) && is_array($data['tables'])) {
            // Legacy support
            $parts['legacy_backup'] = array('tables' => $data['tables']);
        }

        if (empty($parts)) {
            return new WP_Error('invalid_backup', __('Invalid backup file format (No data found).', 'olama-school'));
        }

        $current_user_id = get_current_user_id();

        // Disable FK checks and start transaction
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($parts as $part_id => $part) {
                if (!isset($part['tables']) || !is_array($part['tables'])) continue;

                error_log('[OLAMA RESTORE] Processing Part: ' . $part_id);

                foreach ($part['tables'] as $table => $rows) {
                    $full_table_name = $wpdb->prefix . $table;

                    // Check if table exists
                    if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") !== $full_table_name) {
                        error_log('[OLAMA RESTORE] Skipping table (not found in DB): ' . $table);
                        continue;
                    }

                    // Wipe table with Admin-Preservation logic
                    if ($table === 'users') {
                        $wpdb->query($wpdb->prepare("DELETE FROM $full_table_name WHERE ID != %d", $current_user_id));
                    } else if ($table === 'usermeta') {
                        $wpdb->query($wpdb->prepare("DELETE FROM $full_table_name WHERE user_id != %d", $current_user_id));
                    } else {
                        $wpdb->query("DELETE FROM $full_table_name");
                        $wpdb->query("ALTER TABLE $full_table_name AUTO_INCREMENT = 1");
                    }

                    // Filter rows for Admin-Preservation
                    if ($table === 'users') {
                        $rows = array_filter($rows, function ($r) use ($current_user_id) {
                            return (int)$r['ID'] !== (int)$current_user_id;
                        });
                    } else if ($table === 'usermeta') {
                        $rows = array_filter($rows, function ($r) use ($current_user_id) {
                            return (int)$r['user_id'] !== (int)$current_user_id;
                        });
                    }

                    if (!empty($rows)) {
                        $batch_size = 1000;
                        $batches = array_chunk($rows, $batch_size);
                        foreach ($batches as $batch) {
                            $result = self::batch_insert($full_table_name, $batch);
                            if ($result === false) throw new Exception($wpdb->last_error);
                        }
                    }
                    error_log('[OLAMA RESTORE] Restored ' . $table);
                }
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[OLAMA RESTORE ERROR] ' . $e->getMessage());
            return new WP_Error('restore_failed', __('Database restoration failed: ', 'olama-school') . $e->getMessage());
        } finally {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        }

        return true;
    }

    /**
     * Batch insert multiple rows in a single query
     * Much faster than individual inserts for large datasets
     */
    private static function batch_insert($table, $rows)
    {
        global $wpdb;

        if (empty($rows)) {
            return true;
        }

        // Get columns from first row
        $first_row = reset($rows);
        $columns = array_keys($first_row);
        $columns_escaped = array_map(function ($col) {
            return '`' . esc_sql($col) . '`';
        }, $columns);
        $columns_sql = implode(', ', $columns_escaped);

        // Build values for all rows
        $values_list = array();
        $placeholders_list = array();

        foreach ($rows as $row) {
            $row_values = array();
            $row_placeholders = array();

            foreach ($columns as $col) {
                $value = isset($row[$col]) ? $row[$col] : null;

                if ($value === null) {
                    $row_placeholders[] = 'NULL';
                } else {
                    $row_placeholders[] = '%s';
                    $row_values[] = $value;
                }
            }

            $placeholders_list[] = '(' . implode(', ', $row_placeholders) . ')';
            $values_list = array_merge($values_list, $row_values);
        }

        $placeholders_sql = implode(', ', $placeholders_list);
        $sql = "INSERT INTO $table ($columns_sql) VALUES $placeholders_sql";

        if (!empty($values_list)) {
            $sql = $wpdb->prepare($sql, $values_list);
        }

        return $wpdb->query($sql);
    }

    /**
     * Get the base directory for backups based on OS
     */
    public static function get_backup_storage_dir()
    {
        $custom_path = get_option('olama_backup_path');
        if (!empty($custom_path)) {
            return wp_normalize_path(trailingslashit($custom_path));
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: Outside the public/ folder in Local Sites structure
            $public_dir = wp_normalize_path(untrailingslashit(ABSPATH));
            $app_dir = dirname($public_dir);
            $site_dir = dirname($app_dir);
            $base = $site_dir . '/olama_backups/';
        } else {
            // Linux (Production)
            $base = '/srv/olama-backups/';
        }

        return wp_normalize_path($base . '/');
    }

    /**
     * Ensure the backup directory exists and is protected
     */
    public static function ensure_storage_dir_exists()
    {
        $dir = self::get_backup_storage_dir();
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new WP_Error('dir_creation_failed', sprintf(__('Could not create storage directory: %s', 'olama-school'), $dir));
            }
            // Security
            @file_put_contents($dir . 'index.php', '<?php // Silence is golden');
            @file_put_contents($dir . '.htaccess', 'Deny from all');
        }

        if (!is_writable($dir)) {
            return new WP_Error('dir_not_writable', sprintf(__('Storage directory is not writable: %s', 'olama-school'), $dir));
        }

        return $dir;
    }

    /**
     * Save a backup file to the server storage
     */
    public static function save_backup_to_server()
    {
        $dir = self::ensure_storage_dir_exists();
        if (is_wp_error($dir)) {
            return $dir;
        }

        $backup_data = self::generate_backup();
        $filename = 'olama-auto-backup-' . current_time('Y-m-d-His') . '.json';
        $filepath = $dir . $filename;

        if (file_put_contents($filepath, json_encode($backup_data))) {
            // Run retention policy after successful save
            self::run_retention_policy();
            return $filename;
        }

        return new WP_Error('save_failed', __('Failed to write backup file to server.', 'olama-school'));
    }

    /**
     * Delete old backups based on retention policy
     */
    public static function run_retention_policy()
    {
        $dir = self::get_backup_storage_dir();
        if (!is_dir($dir)) {
            return;
        }

        $retention_count = (int) get_option('olama_backup_retention', 7);
        if ($retention_count <= 0) {
            return;
        }

        $files = glob($dir . 'olama-auto-backup-*.json');
        if (count($files) <= $retention_count) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $files_to_delete = count($files) - $retention_count;
        for ($i = 0; $i < $files_to_delete; $i++) {
            @unlink($files[$i]);
        }
    }

    /**
     * Entry point for scheduled WP-Cron backup
     */
    public static function run_scheduled_backup()
    {
        $start_time = current_time('mysql');
        error_log('[OLAMA CRON] Starting scheduled backup at ' . $start_time);

        $result = self::save_backup_to_server();
        if (is_wp_error($result)) {
            error_log('[OLAMA CRON ERROR] Scheduled backup failed (Started: ' . $start_time . '): ' . $result->get_error_message());
        } else {
            error_log('[OLAMA CRON SUCCESS] Scheduled backup completed: ' . $result);
        }
    }
}

