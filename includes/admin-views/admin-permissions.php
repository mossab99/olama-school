<?php
/**
 * Admin Permissions Page
 */
if (!defined('ABSPATH')) {
    exit;
}

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
                        <th style="width: 250px;"><?php _e('Capability', 'olama-school'); ?></th>
                        <?php foreach ($roles as $label): ?>
                            <th><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($capabilities as $cap => $cap_label): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cap_label); ?></strong></td>
                            <?php foreach ($roles as $role_name => $label):
                                $role = get_role($role_name);
                                $has_cap = $role ? $role->has_cap($cap) : false;
                            ?>
                                <td>
                                    <input type="checkbox"
                                        name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]"
                                        <?php checked($has_cap); ?>>
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
