<?php
/**
 * Admin Permissions Page — sticky header, scrollable body
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!Olama_School_Permissions::can('olama_manage_users_permissions')) {
    return;
}

$roles = array(
    'administrator' => __('Administrator', 'olama-school'),
    'editor'        => __('Supervisor', 'olama-school'),
    'supervisor'    => __('Supervisor (Custom)', 'olama-school'),
    'teacher'       => __('Teacher', 'olama-school'),
    'author'        => __('Assistant', 'olama-school'),
    'assistant'     => __('Assistant (Custom)', 'olama-school'),
);

$capability_groups = Olama_School_Permissions::get_all_capabilities();

if (isset($_POST['save_permissions'])) {
    check_admin_referer('olama_save_permissions');
    foreach ($roles as $role_name => $role_label) {
        if ($role_name === 'administrator') continue;

        $role = get_role($role_name);
        if (!$role) continue;

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
    echo '<div class="notice notice-success"><p><strong>✅ ' . __('Permissions saved successfully.', 'olama-school') . '</strong></p></div>';
}
?>
<style>
    .perm-wrap {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-top: 16px;
    }

    /* Scroll container: full viewport minus admin toolbar + page heading */
    .perm-scroll {
        overflow-x: auto;
        overflow-y: auto;
        max-height: calc(100vh - 180px);
    }

    .perm-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 820px;
        font-size: 13px;
    }

    /* ── Sticky header ── */
    .perm-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background: #1e293b;
        color: #fff;
        padding: 14px 16px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        border-bottom: 3px solid #6366f1;
    }

    .perm-table thead th:first-child {
        text-align: right;
        min-width: 260px;
        border-radius: 0;
    }

    .perm-table thead th:not(:first-child) {
        text-align: center;
        min-width: 110px;
    }

    /* ── Group header rows ── */
    .perm-group-row td {
        background: #f1f5f9;
        padding: 10px 16px;
        font-weight: 800;
        color: #1e293b;
        border-top: 2px solid #e2e8f0;
        border-left: 4px solid #6366f1;
        font-size: 13px;
    }

    .perm-group-row .dashicons {
        color: #6366f1;
        vertical-align: middle;
        margin-bottom: 2px;
        margin-left: 4px;
    }

    /* ── Data rows ── */
    .perm-table tbody tr:not(.perm-group-row) td {
        padding: 10px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .perm-table tbody tr:not(.perm-group-row):hover td {
        background: #f8fafc;
    }

    .cap-name {
        font-weight: 600;
        color: #1e293b;
        line-height: 1.4;
    }

    .cap-key {
        font-size: 10px;
        color: #94a3b8;
        font-family: monospace;
        margin-top: 2px;
    }

    .perm-table td.check-cell {
        text-align: center;
    }

    .perm-table td.check-cell input[type="checkbox"] {
        width: 17px;
        height: 17px;
        cursor: pointer;
        accent-color: #6366f1;
    }

    .perm-table td.check-cell input[type="checkbox"]:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .perm-footer {
        padding: 20px 24px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .perm-footer .button-primary {
        background: #6366f1;
        border-color: #4f46e5;
        color: #fff;
        font-size: 14px;
        padding: 8px 24px;
        height: auto;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(99,102,241,0.35);
    }

    .perm-footer .button-primary:hover {
        background: #4f46e5;
        border-color: #4338ca;
    }

    .perm-footer small {
        color: #64748b;
    }
</style>

<div class="olama-permissions-container">
    <form method="post">
        <?php wp_nonce_field('olama_save_permissions'); ?>

        <div class="perm-wrap">
            <div class="perm-scroll">
                <table class="perm-table">
                    <thead>
                        <tr>
                            <th><?php _e('Permission / Module', 'olama-school'); ?></th>
                            <?php foreach ($roles as $role_key => $label): ?>
                                <th><?php echo esc_html($label); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($capability_groups as $group_id => $group): ?>
                            <tr class="perm-group-row">
                                <td colspan="<?php echo count($roles) + 1; ?>">
                                    <span class="dashicons dashicons-category"></span>
                                    <?php echo esc_html($group['label']); ?>
                                </td>
                            </tr>
                            <?php foreach ($group['caps'] as $cap => $cap_label): ?>
                                <tr>
                                    <td>
                                        <div class="cap-name"><?php echo esc_html($cap_label); ?></div>
                                        <div class="cap-key"><?php echo esc_html($cap); ?></div>
                                    </td>
                                    <?php foreach ($roles as $role_name => $label):
                                        $role     = get_role($role_name);
                                        $has_cap  = $role ? $role->has_cap($cap) : false;
                                        $is_admin = ($role_name === 'administrator');
                                    ?>
                                        <td class="check-cell">
                                            <input type="checkbox"
                                                name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]"
                                                <?php checked($has_cap); ?>
                                                <?php disabled($is_admin); ?>>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="perm-footer">
                <button type="submit" name="save_permissions" class="button button-primary">
                    💾 <?php _e('Save All Permissions', 'olama-school'); ?>
                </button>
                <small><?php _e('Administrators always have full access regardless of settings.', 'olama-school'); ?></small>
            </div>
        </div>
    </form>
</div>
