<?php
/**
 * Admin Permissions Page — role-centric view with sidebar
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!Olama_School_Permissions::can('olama_manage_users_permissions')) {
    return;
}

$roles = array(
    'administrator'        => __('Administrator', 'olama-school'),
    'editor'               => __('Supervisor', 'olama-school'),
    'teacher'              => __('Teacher', 'olama-school'),
    'author'               => __('Assistant', 'olama-school'),
    'accountant'           => __('Accountant', 'olama-school'),
    'os_warehouse_manager' => __('Warehouse Manager', 'olama-school'),
    'os_warehouse_staff'   => __('Warehouse Staff', 'olama-school'),
    'os_viewer'            => __('Stores Viewer', 'olama-school'),
);

$capability_groups = Olama_School_Permissions::get_all_capabilities();

// Process saving
if (isset($_POST['save_permissions'])) {
    check_admin_referer('olama_save_permissions');
    foreach ($roles as $role_name => $role_label) {
        if ($role_name === 'administrator') continue;

        $role = get_role($role_name);
        if (!$role) continue;

        // Sync to custom mirror roles to ensure codebase compatibility
        $mirror_role = null;
        if ($role_name === 'editor') {
            $mirror_role = get_role('supervisor');
        } elseif ($role_name === 'author') {
            $mirror_role = get_role('assistant');
        }

        foreach ($capability_groups as $group) {
            foreach ($group['caps'] as $cap => $cap_label) {
                if (isset($_POST['caps'][$role_name][$cap])) {
                    $role->add_cap($cap);
                    if ($mirror_role) $mirror_role->add_cap($cap);
                } else {
                    $role->remove_cap($cap);
                    if ($mirror_role) $mirror_role->remove_cap($cap);
                }
            }
        }
    }
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ ' . __('Permissions saved successfully.', 'olama-school') . '</strong></p></div>';
}

// Get user counts
$user_counts = count_users();
$avail_roles = $user_counts['avail_roles'];

// For Supervisor/Assistant mirrors
$supervisor_count = isset($avail_roles['supervisor']) ? $avail_roles['supervisor'] : 0;
$editor_count = isset($avail_roles['editor']) ? $avail_roles['editor'] : 0;
$total_supervisor_count = $supervisor_count + $editor_count;

$assistant_count = isset($avail_roles['assistant']) ? $avail_roles['assistant'] : 0;
$author_count = isset($avail_roles['author']) ? $avail_roles['author'] : 0;
$total_assistant_count = $assistant_count + $author_count;
?>
<style>
    /* Reset & layout */
    .olama-permissions-container {
        margin-top: 16px;
        box-sizing: border-box;
    }
    .olama-permissions-container * {
        box-sizing: border-box;
    }

    .perm-layout {
        display: flex;
        background: #f4f3ef; /* Light beige from mockup */
        border-radius: 12px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        min-height: calc(100vh - 180px);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    /* Sidebar */
    .perm-sidebar {
        width: 260px;
        background: #f4f3ef;
        border-right: 1px solid #e2e8f0;
        padding: 24px 16px;
        flex-shrink: 0;
    }

    .perm-sidebar-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        font-weight: 700;
        padding: 0 16px;
        margin-bottom: 16px;
    }

    .perm-role-btn {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 12px 16px;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 8px;
        text-align: left;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: #334155;
        transition: all 0.2s;
        margin-bottom: 8px;
    }

    .perm-role-btn:hover {
        background: rgba(255, 255, 255, 0.5);
        color: #0f172a;
    }

    .perm-role-btn.active {
        background: #fff;
        border-color: #e2e8f0;
        color: #0f172a;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    .perm-role-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        margin-right: 12px;
    }

    /* Role icon background colors (mimicking mockup pastels) */
    .perm-role-btn[data-role="administrator"] .perm-role-icon { background: #e0e7ff; color: #4f46e5; }
    .perm-role-btn[data-role="editor"] .perm-role-icon { background: #dcfce7; color: #16a34a; }
    .perm-role-btn[data-role="teacher"] .perm-role-icon { background: #e0f2fe; color: #0284c7; }
    .perm-role-btn[data-role="author"] .perm-role-icon { background: #ffe4e6; color: #e11d48; }
    .perm-role-btn[data-role="accountant"] .perm-role-icon { background: #fef3c7; color: #d97706; }
    .perm-role-btn[data-role="os_warehouse_manager"] .perm-role-icon { background: #dcfce7; color: #16a34a; }
    .perm-role-btn[data-role="os_warehouse_staff"] .perm-role-icon { background: #dcfce7; color: #16a34a; }
    .perm-role-btn[data-role="os_viewer"] .perm-role-icon { background: #f3f4f6; color: #4b5563; }

    .perm-role-badge {
        font-size: 11px;
        background: transparent;
        color: #64748b;
        padding: 2px 0;
        font-weight: 600;
    }
    
    .perm-role-btn.active .perm-role-badge {
        color: #6366f1;
    }

    /* Main Content */
    .perm-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #fff;
    }

    .perm-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 32px;
        border-bottom: 1px solid #e2e8f0;
    }

    .perm-header-title {
        font-size: 20px;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .perm-header-subtitle {
        font-size: 13px;
        color: #64748b;
        margin-top: 4px;
        font-weight: normal;
    }

    .perm-save-btn {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .perm-save-btn:hover {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .perm-disable-btn {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #ef4444;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .perm-disable-btn:hover {
        border-color: #fca5a5;
        background: #fef2f2;
    }

    .perm-panels {
        flex: 1;
        overflow-y: auto;
        padding: 32px;
    }

    .perm-panel {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .perm-panel.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Module Groups */
    .perm-module {
        margin-bottom: 32px;
    }

    .perm-module-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .perm-module-title .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        color: #cbd5e1;
    }

    .perm-list {
        background: #fff;
        border-top: 1px solid #f1f5f9;
    }

    .perm-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s;
    }

    .perm-item-info {
        flex: 1;
    }

    .perm-item-name {
        font-size: 14px;
        font-weight: 500;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .perm-item-key {
        font-size: 11px;
        font-family: monospace;
        color: #94a3b8;
    }

    /* Toggle Switches */
    .perm-toggle {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }

    .perm-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .perm-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .3s;
        border-radius: 24px;
    }

    .perm-toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .perm-toggle input:checked + .perm-toggle-slider {
        background-color: #6366f1;
    }

    .perm-toggle input:focus + .perm-toggle-slider {
        box-shadow: 0 0 1px #6366f1;
    }

    .perm-toggle input:checked + .perm-toggle-slider:before {
        transform: translateX(20px);
    }
    
    .perm-toggle input:disabled + .perm-toggle-slider {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .perm-admin-note {
        padding: 16px 20px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 24px;
    }

</style>

<div class="olama-permissions-container">
    <form method="post" id="permissionsForm">
        <?php wp_nonce_field('olama_save_permissions'); ?>

        <div class="perm-layout">
            <!-- Sidebar -->
            <div class="perm-sidebar">
                <div class="perm-sidebar-title"><?php _e('Roles', 'olama-school'); ?></div>
                <div class="perm-roles-list">
                    <?php 
                    foreach ($roles as $role_name => $label): 
                        // Determine count
                        if ($role_name === 'editor') {
                            $count = $total_supervisor_count;
                        } elseif ($role_name === 'author') {
                            $count = $total_assistant_count;
                        } else {
                            $count = isset($avail_roles[$role_name]) ? $avail_roles[$role_name] : 0;
                        }
                        
                        $is_admin = ($role_name === 'administrator');
                        $icon = 'dashicons-groups';
                        if ($is_admin) $icon = 'dashicons-shield';
                        elseif ($role_name === 'teacher') $icon = 'dashicons-welcome-learn-more';
                        elseif ($role_name === 'accountant') $icon = 'dashicons-chart-pie';
                        elseif ($role_name === 'os_warehouse_manager' || $role_name === 'os_warehouse_staff') $icon = 'dashicons-store';
                    ?>
                        <button type="button" class="perm-role-btn" data-role="<?php echo esc_attr($role_name); ?>" data-label="<?php echo esc_attr($label); ?>">
                            <div style="display:flex; align-items:center;">
                                <span class="perm-role-icon"><span class="dashicons <?php echo $icon; ?>"></span></span>
                                <?php echo esc_html($label); ?>
                            </div>
                            <?php if ($count > 0): ?>
                                <span class="perm-role-badge" title="<?php echo esc_attr($count . ' users'); ?>"><?php echo number_format_i18n($count); ?> <?php _e('users', 'olama-school'); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="perm-content">
                <div class="perm-header">
                    <div>
                        <h2 class="perm-header-title">
                            <span class="dashicons dashicons-shield" style="color:#6366f1;"></span>
                            <span id="currentRoleTitle"><?php _e('Select a role', 'olama-school'); ?></span>
                            <span id="currentRoleBadge" class="perm-role-badge" style="background:#e0e7ff; color:#4f46e5; padding:2px 8px; border-radius:12px; font-size:12px; display:none; margin-left:8px;"></span>
                        </h2>
                        <div class="perm-header-subtitle"><?php _e('Configure what this role can access', 'olama-school'); ?></div>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <button type="button" id="disableAllBtn" class="perm-disable-btn">
                            <span class="dashicons dashicons-no"></span> <?php _e('Disable All', 'olama-school'); ?>
                        </button>
                        <button type="submit" name="save_permissions" class="perm-save-btn">
                            <span class="dashicons dashicons-saved"></span> <?php _e('Save changes', 'olama-school'); ?>
                        </button>
                    </div>
                </div>

                <div class="perm-panels">
                    <?php foreach ($roles as $role_name => $role_label): 
                        $is_admin = ($role_name === 'administrator');
                        $role = get_role($role_name);
                    ?>
                        <div class="perm-panel" id="panel-<?php echo esc_attr($role_name); ?>">
                            
                            <?php if ($is_admin): ?>
                                <div class="perm-admin-note" style="margin-top:0; margin-bottom:24px;">
                                    <span class="dashicons dashicons-info" style="color:#6366f1;"></span>
                                    <?php _e('Administrators always have full access regardless of settings.', 'olama-school'); ?>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($capability_groups as $group_id => $group): ?>
                                <div class="perm-module">
                                    <div class="perm-module-title">
                                        <span class="dashicons dashicons-category"></span>
                                        <?php echo esc_html($group['label']); ?>
                                    </div>
                                    <div class="perm-list">
                                        <?php foreach ($group['caps'] as $cap => $cap_label): 
                                            $has_cap = $role ? $role->has_cap($cap) : false;
                                        ?>
                                            <div class="perm-item">
                                                <div class="perm-item-info">
                                                    <div class="perm-item-name"><?php echo esc_html($cap_label); ?></div>
                                                    <div class="perm-item-key"><?php echo esc_html($cap); ?></div>
                                                </div>
                                                <label class="perm-toggle">
                                                    <input type="checkbox"
                                                        name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]"
                                                        <?php checked($has_cap); ?>
                                                        <?php disabled($is_admin); ?>>
                                                    <span class="perm-toggle-slider"></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (!$is_admin): ?>
                                <div class="perm-admin-note">
                                    <span class="dashicons dashicons-info" style="color:#6366f1;"></span>
                                    <?php _e('Administrators always have full access regardless of these settings.', 'olama-school'); ?>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleBtns = document.querySelectorAll('.perm-role-btn');
    const panels = document.querySelectorAll('.perm-panel');
    const titleEl = document.getElementById('currentRoleTitle');
    const badgeEl = document.getElementById('currentRoleBadge');
    const disableAllBtn = document.getElementById('disableAllBtn');

    function selectRole(roleName) {
        // Update buttons
        roleBtns.forEach(btn => {
            if (btn.dataset.role === roleName) {
                btn.classList.add('active');
                
                // Update header title
                titleEl.textContent = btn.dataset.label;
                
                // Update header badge
                const btnBadge = btn.querySelector('.perm-role-badge');
                if (btnBadge) {
                    badgeEl.textContent = btnBadge.textContent;
                    badgeEl.style.display = 'inline-block';
                } else {
                    badgeEl.style.display = 'none';
                }
            } else {
                btn.classList.remove('active');
            }
        });

        if (disableAllBtn) {
            if (roleName === 'administrator') {
                disableAllBtn.style.display = 'none';
            } else {
                disableAllBtn.style.display = 'inline-flex';
            }
        }

        // Update panels
        panels.forEach(panel => {
            if (panel.id === 'panel-' + roleName) {
                panel.classList.add('active');
            } else {
                panel.classList.remove('active');
            }
        });

        // Save to localStorage
        localStorage.setItem('olama_selected_role', roleName);
    }

    // Attach click events
    roleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            selectRole(this.dataset.role);
        });
    });

    if (disableAllBtn) {
        disableAllBtn.addEventListener('click', function() {
            const activePanel = document.querySelector('.perm-panel.active');
            if (activePanel) {
                const checkboxes = activePanel.querySelectorAll('input[type="checkbox"]:not(:disabled)');
                checkboxes.forEach(cb => {
                    cb.checked = false;
                });
            }
        });
    }

    // Determine initial role
    let savedRole = localStorage.getItem('olama_selected_role');
    let validRoles = Array.from(roleBtns).map(b => b.dataset.role);
    
    if (savedRole && validRoles.includes(savedRole)) {
        selectRole(savedRole);
    } else {
        // Default to 'editor' (Supervisor) if it exists, otherwise the second role
        if (validRoles.includes('editor')) {
            selectRole('editor');
        } else if (validRoles.length > 1) {
            selectRole(validRoles[1]);
        } else if (validRoles.length > 0) {
            selectRole(validRoles[0]);
        }
    }
});
</script>
