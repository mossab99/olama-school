<?php
/**
 * Admin Permissions Page
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!Olama_School_Permissions::can('olama_manage_users_permissions')) {
    return;
}

$roles = array(
    'administrator' => __('Administrator', 'olama-school'),
    'editor' => __('Supervisor', 'olama-school'),
    'teacher' => __('Teacher', 'olama-school'),
    'author' => __('Author', 'olama-school'),
);

$capability_groups = Olama_School_Permissions::get_all_capabilities();

if (isset($_POST['save_permissions'])) {
    check_admin_referer('olama_save_permissions');
    foreach ($roles as $role_name => $role_label) {
        if ($role_name === 'administrator') continue; // Don't modify admin caps here, they have everything.

        $role = get_role($role_name);
        if (!$role)
            continue;

        foreach ($capability_groups as $group) {
            foreach ($group['caps'] as $cap => $cap_label) {
                if (isset($_POST['caps'][$role_name][$cap])) {
                    $role->add_cap($cap);
                } else {
                    $role->remove_cap($cap);
                }
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
                        <th style="width: 250px;"><?php _e('Submenu / Tab Permission', 'olama-school'); ?></th>
                        <?php foreach ($roles as $role_key => $label): ?>
                            <th style="text-align: center;"><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($capability_groups as $group_id => $group): ?>
                        <tr class="olama-cap-group-header" style="background: #f8fafc;">
                            <td colspan="<?php echo count($roles) + 1; ?>" style="font-weight: 800; color: #1e293b; padding: 12px 15px; border-left: 4px solid #6366f1;">
                                <span class="dashicons dashicons-category" style="margin-top: 2px; margin-right: 5px; color: #6366f1;"></span>
                                <?php echo esc_html($group['label']); ?>
                            </td>
                        </tr>
                        <?php foreach ($group['caps'] as $cap => $cap_label): ?>
                            <tr>
                                <td style="padding-left: 30px;">
                                    <strong><?php echo esc_html($cap_label); ?></strong>
                                    <div style="font-size: 10px; color: #94a3b8; font-family: monospace;"><?php echo esc_html($cap); ?></div>
                                </td>
                                <?php foreach ($roles as $role_name => $label):
                                    $role = get_role($role_name);
                                    $has_cap = $role ? $role->has_cap($cap) : false;
                                    $is_admin = ($role_name === 'administrator');
                                ?>
                                    <td style="text-align: center;">
                                        <input type="checkbox"
                                            name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]"
                                            <?php checked($has_cap); ?>
                                            <?php disabled($is_admin); ?>
                                            style="<?php echo $is_admin ? 'opacity: 0.5;' : ''; ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top: 20px;">
            <?php submit_button(__('Save All Permissions', 'olama-school'), 'primary', 'save_permissions'); ?>
        </div>
    </form>
</div>
