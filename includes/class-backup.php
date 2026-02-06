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
            'olama_subjects',
            'olama_teachers',
            'olama_families',
            'olama_students',
            'olama_student_enrollment',
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
            'olama_exams',
            'olama_user_preferences',
            'olama_notifications',
            'olama_ev_templates',
            'olama_ev_domains',
            'olama_ev_categories',
            'olama_ev_indicators',
            'olama_ev_records',
            'olama_ev_scores',
            'olama_semester_exams',
            'olama_stationary',
            'olama_exam_attachments',
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
     * Restore backup data from JSON
     */
    public static function restore_backup($json_data)
    {
        global $wpdb;
        $json_data = trim($json_data);
        $data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_backup', __('Invalid backup file format (JSON error): ', 'olama-school') . json_last_error_msg());
        }

        if (!$data || !isset($data['tables'])) {
            return new WP_Error('invalid_backup', __('Invalid backup file format (Missing tables key).', 'olama-school'));
        }

        // Optional: Version check warning if needed

        $tables = self::get_plugin_tables();

        // Start transaction (if supported by DB engine, though WPDB doesn't natively expose it simply for all setups)
        // We'll do it table by table.

        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;

            // Wipe table
            $wpdb->query("TRUNCATE TABLE $full_table_name");

            if (isset($data['tables'][$table]) && is_array($data['tables'][$table])) {
                foreach ($data['tables'][$table] as $row) {
                    $wpdb->insert($full_table_name, $row);
                }
            }
        }

        return true;
    }
}
