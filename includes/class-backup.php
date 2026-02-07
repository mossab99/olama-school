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
            'olama_exams',
            'olama_exam_attachments',
            'olama_ev_templates',
            'olama_ev_domains',
            'olama_ev_categories',
            'olama_ev_indicators',
            'olama_ev_records',
            'olama_ev_scores',
            'olama_plans',
            'olama_plan_questions',
            'olama_templates',
            'olama_schedule',
            'olama_curriculum_units',
            'olama_curriculum_lessons',
            'olama_curriculum_questions',
            'olama_logs',
            'olama_academic_events',
            'olama_teacher_assignments',
            'olama_teacher_office_hours',
            'olama_user_preferences',
            'olama_notifications',
            'olama_semester_exams',
            'olama_stationary',
            'olama_attendance',
            'olama_shifts_locations',
            'olama_shifts_time_slots',
            'olama_shifts_schedule'
        );
    }

    /**
     * Generate backup data
     */
    public static function generate_backup()
    {
        global $wpdb;
        $tables = self::get_plugin_tables();
        $backup_data = array(
            'version' => OLAMA_SCHOOL_VERSION,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
            'tables' => array()
        );

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name) {
                $backup_data['tables'][$table] = $wpdb->get_results("SELECT * FROM $full_table_name", ARRAY_A);
            }
        }

        return $backup_data;
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
     * Restore backup data from JSON (Legacy / Full)
     */
    public static function restore_backup($json_data)
    {
        global $wpdb;

        error_log('[OLAMA RESTORE] restore_backup() started');

        // Increase limits for large datasets
        @set_time_limit(600); // 10 minutes
        @ini_set('memory_limit', '512M');

        error_log('[OLAMA RESTORE] Parsing JSON data (' . strlen($json_data) . ' bytes)');
        $json_data = trim($json_data);
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[OLAMA RESTORE] JSON decode error: ' . json_last_error_msg());
            return new WP_Error('invalid_backup', __('Invalid backup file format (JSON error): ', 'olama-school') . json_last_error_msg());
        }

        if (!$data || !isset($data['tables'])) {
            error_log('[OLAMA RESTORE] Invalid backup format - missing tables key');
            return new WP_Error('invalid_backup', __('Invalid backup file format (Missing tables key).', 'olama-school'));
        }

        error_log('[OLAMA RESTORE] JSON parsed successfully. Tables in backup: ' . count($data['tables']));
        $tables = self::get_plugin_tables();

        // Disable FK checks and start transaction
        error_log('[OLAMA RESTORE] Disabling FK checks, starting transaction');
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $wpdb->query('START TRANSACTION');

        try {
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;

                // Wipe table (DELETE avoids auto-commit)
                error_log('[OLAMA RESTORE] Clearing table: ' . $table);
                $wpdb->query("DELETE FROM $full_table_name");
                $wpdb->query("ALTER TABLE $full_table_name AUTO_INCREMENT = 1");

                if (isset($data['tables'][$table]) && is_array($data['tables'][$table])) {
                    $rows = $data['tables'][$table];
                    $row_count = count($rows);
                    error_log('[OLAMA RESTORE] Inserting ' . $row_count . ' rows into: ' . $table);

                    if ($row_count > 0) {
                        // Use batch insert for performance (1000 rows per batch)
                        $batch_size = 1000;
                        $batches = array_chunk($rows, $batch_size);
                        $batch_num = 0;

                        foreach ($batches as $batch) {
                            $batch_num++;
                            $result = self::batch_insert($full_table_name, $batch);

                            if ($result === false) {
                                error_log('[OLAMA RESTORE] Batch insert failed at batch ' . $batch_num . ' in ' . $table . ': ' . $wpdb->last_error);
                                throw new Exception($wpdb->last_error);
                            }

                            // Log progress for large tables
                            if ($row_count > 500 && $batch_num % 10 === 0) {
                                error_log('[OLAMA RESTORE] Progress: ' . ($batch_num * $batch_size) . '/' . $row_count . ' rows in ' . $table);
                            }
                        }
                    }

                    error_log('[OLAMA RESTORE] Completed table: ' . $table . ' (' . $row_count . ' rows)');
                    // Free up memory for processed table
                    unset($data['tables'][$table]);
                } else {
                    error_log('[OLAMA RESTORE] Table not in backup or empty: ' . $table);
                }
            }
            error_log('[OLAMA RESTORE] All tables restored, committing transaction');
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            error_log('[OLAMA RESTORE] Exception caught: ' . $e->getMessage());
            $wpdb->query('ROLLBACK');
            error_log('[OLAMA RESTORE ERROR] Backup restoration failed: ' . $e->getMessage());
            return new WP_Error('restore_failed', __('Database restoration failed: ', 'olama-school') . $e->getMessage());
        } finally {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        }

        error_log('[OLAMA RESTORE] restore_backup() completed successfully');
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
        $result = self::save_backup_to_server();
        if (is_wp_error($result)) {
            error_log('[OLAMA CRON ERROR] Scheduled backup failed: ' . $result->get_error_message());
        } else {
            error_log('[OLAMA CRON SUCCESS] Scheduled backup completed: ' . $result);
        }
    }
}

