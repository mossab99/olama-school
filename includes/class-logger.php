<?php
/**
 * Olama School Logger Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Logger
{
    /**
     * Log an action to the audit trail
     *
     * @param string $action  Specific action performed (e.g. 'plan_created')
     * @param string $details Additional contextual information
     * @param int    $user_id User who performed the action (defaults to current)
     */
    public static function log($action, $details = '', $user_id = 0)
    {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'olama_logs';

        $data = array(
            'user_id' => $user_id,
            'action' => sanitize_text_field($action),
            'details' => sanitize_textarea_field($details),
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
            'created_at' => current_time('mysql'),
        );

        $wpdb->insert($table_name, $data);

        // Optionally trigger notifications if enabled
        self::maybe_notify($action, $details, $user_id);
    }

    /**
     * Send email notifications based on action
     */
    private static function maybe_notify($action, $details, $user_id)
    {
        $enable_notifs = get_option('olama_enable_notifs', 'yes');
        if ($enable_notifs !== 'yes') {
            return;
        }

        $admin_email = get_option('olama_admin_email', get_option('admin_email'));
        $user_info = get_userdata($user_id);
        $user_name = $user_info ? $user_info->display_name : 'System';

        $subject = sprintf('[Olama School] Activity Alert: %s', strtoupper($action));
        $message = sprintf(
            "An activity was recorded in the Olama School system:\n\n" .
            "Action: %s\n" .
            "User: %s\n" .
            "Details: %s\n" .
            "Time: %s\n\n" .
            "This is an automated notification.",
            $action,
            $user_name,
            $details,
            current_time('mysql')
        );

        wp_mail($admin_email, $subject, $message);
    }
}