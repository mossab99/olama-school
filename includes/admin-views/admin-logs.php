<?php
/**
 * Admin Activity Logs Page
 */
if (!defined('ABSPATH')) {
    exit;
}

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
                            <td>
                                <span class="badge"
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
            <?php submit_button(__('Save Settings', 'olama-school'), 'primary'); ?>
        </form>
    </div>
</div>