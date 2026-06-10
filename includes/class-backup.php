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
     * Get list of all Olama ecosystem tables present in the current database.
     */
    public static function get_plugin_tables()
    {
        $tables = array('users', 'usermeta');

        if (class_exists('Olama_School_DB') && method_exists('Olama_School_DB', 'get_tables')) {
            $tables = array_merge($tables, Olama_School_DB::get_tables());
        }

        foreach (self::get_declared_table_providers() as $provider) {
            $tables = array_merge($tables, (array) call_user_func(array($provider, 'get_tables')));
        }

        $tables = array_merge($tables, self::discover_olama_tables());

        return self::normalize_table_list($tables);
    }

    /**
     * Prefixes used by registered Olama ecosystem plugins for custom tables/options.
     */
    private static function get_olama_data_prefixes()
    {
        return apply_filters('olama_school_backup_data_prefixes', array(
            'olama_',
            'os_',
            'oee_',
        ));
    }

    /**
     * Find loaded Olama plugin classes that expose a get_tables() registry.
     */
    private static function get_declared_table_providers()
    {
        $providers = array();

        foreach (get_declared_classes() as $class_name) {
            if (!method_exists($class_name, 'get_tables')) {
                continue;
            }

            if (!preg_match('/^(Olama|OS_|OEE_)/', $class_name)) {
                continue;
            }

            $method = new ReflectionMethod($class_name, 'get_tables');
            if (!$method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $providers[] = $class_name;
        }

        return array_unique($providers);
    }

    /**
     * Discover live database tables owned by Olama plugins by naming convention.
     */
    private static function discover_olama_tables()
    {
        global $wpdb;

        $tables = array();
        foreach (self::get_olama_data_prefixes() as $data_prefix) {
            $like = $wpdb->esc_like($wpdb->prefix . $data_prefix) . '%';
            $found = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
            $tables = array_merge($tables, (array) $found);
        }

        return $tables;
    }

    /**
     * Convert full table names to plugin-relative names and remove unsafe entries.
     */
    private static function normalize_table_list($tables, $apply_filter = true)
    {
        global $wpdb;

        $normalized = array();
        foreach ((array) $tables as $table) {
            $table = (string) $table;

            if (strpos($table, $wpdb->prefix) === 0) {
                $table = substr($table, strlen($wpdb->prefix));
            }

            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }

            $normalized[] = $table;
        }

        if ($apply_filter) {
            $normalized = apply_filters('olama_school_backup_plugin_tables', $normalized);
        }

        $normalized = array_values(array_unique(array_filter((array) $normalized)));

        sort($normalized, SORT_NATURAL);

        return $normalized;
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
            'parts' => array(),
            'options' => self::collect_options_data()
        );

        $backup_data['parts']['olama_ecosystem'] = array(
            'label' => 'Olama School Ecosystem',
            'tables' => self::collect_tables_data(self::get_plugin_tables())
        );

        $backup_data = apply_filters('olama_school_backup_data', $backup_data);

        return $backup_data;
    }

    /**
     * Helper to collect data for a list of tables
     */
    private static function collect_tables_data($tables)
    {
        global $wpdb;
        $data = array();

        foreach (self::normalize_table_list($tables) as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if (self::table_exists($table)) {
                $data[$table] = $wpdb->get_results("SELECT * FROM `" . esc_sql($full_table_name) . "`", ARRAY_A);
            }
        }

        return $data;
    }

    /**
     * Collect WordPress options owned by Olama plugins.
     */
    private static function collect_options_data()
    {
        global $wpdb;

        $options = array();
        foreach (self::get_olama_data_prefixes() as $data_prefix) {
            $like = $wpdb->esc_like($data_prefix) . '%';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $like
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $name = $row['option_name'];
                if (strpos($name, '_transient_') !== false || strpos($name, '_site_transient_') !== false) {
                    continue;
                }

                $options[$name] = array(
                    'value' => $row['option_value'],
                    'autoload' => $row['autoload'],
                );
            }
        }

        ksort($options, SORT_NATURAL);

        return apply_filters('olama_school_backup_options', $options);
    }

    /**
     * Check for a plugin-relative table name.
     */
    private static function table_exists($table)
    {
        global $wpdb;

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        $full_table_name = $wpdb->prefix . $table;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full_table_name)) === $full_table_name;
    }

    /**
     * Clear a specific table's data
     */
    public static function clear_table_data($table)
    {
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table;

        if (!self::table_exists($table)) {
            return true;
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        // TRUNCATE auto-commits, use DELETE for transaction safety
        $wpdb->query("DELETE FROM `" . esc_sql($full_table_name) . "`");
        $result = $wpdb->query("ALTER TABLE `" . esc_sql($full_table_name) . "` AUTO_INCREMENT = 1");
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

        if (!self::table_exists($table)) {
            return true;
        }

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
     * Get an index of all tables in the backup JSON
     */
    public static function get_restore_index($json_data)
    {
        $data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format.', 'olama-school'));
        }

        $index = array();
        if (isset($data['parts']) && is_array($data['parts'])) {
            // New multi-part format
            foreach ($data['parts'] as $part_id => $part) {
                if (isset($part['tables']) && is_array($part['tables'])) {
                    foreach (array_keys($part['tables']) as $table) {
                        $index[] = array('part' => $part_id, 'table' => $table);
                    }
                }
            }
        } elseif (isset($data['tables']) && is_array($data['tables'])) {
            // Legacy format
            foreach (array_keys($data['tables']) as $table) {
                $index[] = array('part' => 'legacy', 'table' => $table);
            }
        }

        return $index;
    }

    /**
     * Restore a single specific table from the backup JSON
     */
    public static function restore_single_table($json_data, $part_id, $table_name)
    {
        global $wpdb;
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format.', 'olama-school'));
        }

        $table_name = self::normalize_table_list(array($table_name), false);
        $table_name = reset($table_name);
        if (!$table_name) {
            return new WP_Error('invalid_table', __('Invalid table name.', 'olama-school'));
        }

        // Find the rows
        $rows = array();
        if ($part_id === 'legacy') {
            $rows = $data['tables'][$table_name] ?? array();
        } else {
            $rows = $data['parts'][$part_id]['tables'][$table_name] ?? array();
        }

        $current_user_id = get_current_user_id();
        $full_table_name = $wpdb->prefix . $table_name;

        // Check if table exists
        if (!self::table_exists($table_name)) {
            return true; // Not a fatal error, just skip
        }

        // Disable FK checks for single operation
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Wipe & Preserve
            if ($table_name === 'users') {
                $wpdb->query($wpdb->prepare("DELETE FROM `" . esc_sql($full_table_name) . "` WHERE ID != %d", $current_user_id));
                $rows = array_filter($rows, function ($r) use ($current_user_id) {
                    return (int)$r['ID'] !== (int)$current_user_id;
                });
            } else if ($table_name === 'usermeta') {
                $wpdb->query($wpdb->prepare("DELETE FROM `" . esc_sql($full_table_name) . "` WHERE user_id != %d", $current_user_id));
                $rows = array_filter($rows, function ($r) use ($current_user_id) {
                    return (int)$r['user_id'] !== (int)$current_user_id;
                });
                
                // Sanitation: unset umeta_id to avoid primary key collisions with the current admin
                $rows = array_map(function ($r) {
                    unset($r['umeta_id']);
                    return $r;
                }, $rows);
            } else {
                $wpdb->query("DELETE FROM `" . esc_sql($full_table_name) . "`");
                $wpdb->query("ALTER TABLE `" . esc_sql($full_table_name) . "` AUTO_INCREMENT = 1");
            }

            // Batch insert the rows
            if (!empty($rows)) {
                $batch_size = 500;
                $batches = array_chunk($rows, $batch_size);
                foreach ($batches as $batch) {
                    $result = self::batch_insert($full_table_name, $batch);
                    if ($result === false) throw new Exception($wpdb->last_error);
                }
            }
        } catch (Exception $e) {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
            return new WP_Error('table_failed', __('Error restoring table ', 'olama-school') . $table_name . ': ' . $e->getMessage());
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        return true;
    }

    /**
     * Restore all plugin options stored in the backup.
     */
    public static function restore_options($json_data)
    {
        $data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format.', 'olama-school'));
        }

        if (empty($data['options']) || !is_array($data['options'])) {
            return true;
        }

        foreach ($data['options'] as $option_name => $option_data) {
            if (!self::is_allowed_option_name($option_name)) {
                continue;
            }

            $raw_value = is_array($option_data) && array_key_exists('value', $option_data)
                ? $option_data['value']
                : $option_data;

            $autoload = null;
            if (is_array($option_data) && array_key_exists('autoload', $option_data)) {
                $autoload = in_array($option_data['autoload'], array('yes', 'on', 'auto-on', '1', 1, true), true);
            }

            update_option($option_name, maybe_unserialize($raw_value), $autoload);
        }

        return true;
    }

    /**
     * Full restore helper used by WP-CLI and any non-AJAX callers.
     */
    public static function restore_backup($json_data)
    {
        $index = self::get_restore_index($json_data);
        if (is_wp_error($index)) {
            return $index;
        }

        foreach ($index as $item) {
            $result = self::restore_single_table($json_data, $item['part'], $item['table']);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return self::restore_options($json_data);
    }

    /**
     * Only restore options that belong to Olama plugin namespaces.
     */
    private static function is_allowed_option_name($option_name)
    {
        foreach (self::get_olama_data_prefixes() as $data_prefix) {
            if (strpos($option_name, $data_prefix) === 0) {
                return true;
            }
        }

        return false;
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

        // Filter out columns that don't exist in the current database schema
        $table_columns = $wpdb->get_col("DESCRIBE `" . esc_sql($table) . "`", 0);
        $valid_columns = $table_columns ? array_flip($table_columns) : array();

        // Get columns from first row
        $first_row = reset($rows);
        $raw_columns = array_keys($first_row);
        
        $columns = array();
        foreach ($raw_columns as $col) {
            if (isset($valid_columns[$col])) {
                $columns[] = $col;
            }
        }

        if (empty($columns)) {
            return true;
        }

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

