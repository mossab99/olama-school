<?php
/**
 * Cleaning Configuration View
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'items';

$sections = array(
    'items' => array('label' => Olama_School_Helpers::translate('Cleaning Items'), 'icon' => 'dashicons-list-view'),
    'floors' => array('label' => Olama_School_Helpers::translate('Manage Floors'), 'icon' => 'dashicons-admin-home'),
    'cleaners' => array('label' => Olama_School_Helpers::translate('Manage Cleaners'), 'icon' => 'dashicons-businessman'),
    'slots' => array('label' => Olama_School_Helpers::translate('Manage Time Slots'), 'icon' => 'dashicons-clock'),
    'assignments' => array('label' => Olama_School_Helpers::translate('Floor Selection'), 'icon' => 'dashicons-share-alt2'),
);

if (!isset($sections[$section])) {
    $section = 'items';
}
?>

<div class="cleaning-config-wrap" style="display: flex; gap: 30px; margin-top: 20px;">
    <!-- Sidebar Tabs -->
    <div class="config-sidebar" style="width: 250px; flex-shrink: 0;">
        <div style="background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <div style="padding: 20px; background: #1e88e5; color: #fff; font-weight: 700; font-size: 16px;">
                <i class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></i>
                <?php echo Olama_School_Helpers::translate('Cleaning Configuration'); ?>
            </div>
            <?php foreach ($sections as $id => $s): ?>
                <a href="?page=olama-school-follow-up&tab=cleaning&view=config&section=<?php echo $id; ?>" 
                   style="display: flex; gap: 10px; padding: 15px 20px; text-decoration: none; color: <?php echo $section === $id ? '#1e88e5' : '#666'; ?>; background: <?php echo $section === $id ? '#f0f7ff' : '#fff'; ?>; border-left: 4px solid <?php echo $section === $id ? '#1e88e5' : 'transparent'; ?>; transition: all 0.2s; font-weight: <?php echo $section === $id ? '600' : '400'; ?>;">
                    <i class="dashicons <?php echo $s['icon']; ?>"></i>
                    <?php echo $s['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="?page=olama-school-follow-up&tab=cleaning" class="button button-link" style="color: #666;">
                <i class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></i> <?php echo Olama_School_Helpers::translate('Back to Logs'); ?>
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="config-main" style="flex: 1;">
        <div class="card" style="padding: 30px; border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff;">
            <h2 style="margin-top:0; border-bottom: 2px solid #f0f4f8; padding-bottom: 15px; margin-bottom: 25px; color: #2c3e50;">
                <?php echo $sections[$section]['label']; ?>
            </h2>

            <?php if ($section === 'items'): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                    <input type="hidden" name="olama_save_cleaning_config" value="1">
                    <input type="hidden" name="config_type" value="items">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                        <input type="text" name="new_item" placeholder="<?php echo Olama_School_Helpers::translate('Add New Item'); ?>" style="flex: 1; height: 40px;">
                        <button type="submit" class="button button-primary"><?php echo Olama_School_Helpers::translate('Add'); ?></button>
                    </div>
                </form>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th><?php echo Olama_School_Helpers::translate('Item Name'); ?></th><th style="width: 80px;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th></tr></thead>
                    <tbody>
                        <?php 
                        $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_items ORDER BY id DESC");
                        foreach ($items as $it): ?>
                            <tr>
                                <td><?php echo esc_html($it->item_name); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure?')">
                                        <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                                        <input type="hidden" name="olama_save_cleaning_config" value="1">
                                        <input type="hidden" name="config_type" value="items">
                                        <input type="hidden" name="delete_item" value="<?php echo $it->id; ?>">
                                        <button type="submit" class="button button-link" style="color: #d63638;"><i class="dashicons dashicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($section === 'floors'): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                    <input type="hidden" name="olama_save_cleaning_config" value="1">
                    <input type="hidden" name="config_type" value="floors">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                        <input type="text" name="new_floor" placeholder="<?php echo Olama_School_Helpers::translate('Add New Floor'); ?>" style="flex: 1; height: 40px;">
                        <button type="submit" class="button button-primary"><?php echo Olama_School_Helpers::translate('Add'); ?></button>
                    </div>
                </form>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th><?php echo Olama_School_Helpers::translate('Floor Name'); ?></th><th style="width: 80px;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th></tr></thead>
                    <tbody>
                        <?php 
                        $floors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors ORDER BY id DESC");
                        foreach ($floors as $fl): ?>
                            <tr>
                                <td><?php echo esc_html($fl->floor_name); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure?')">
                                        <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                                        <input type="hidden" name="olama_save_cleaning_config" value="1">
                                        <input type="hidden" name="config_type" value="floors">
                                        <input type="hidden" name="delete_floor" value="<?php echo $fl->id; ?>">
                                        <button type="submit" class="button button-link" style="color: #d63638;"><i class="dashicons dashicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($section === 'cleaners'): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                    <input type="hidden" name="olama_save_cleaning_config" value="1">
                    <input type="hidden" name="config_type" value="cleaners">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                        <input type="text" name="new_cleaner" placeholder="<?php echo Olama_School_Helpers::translate('Add New Cleaner'); ?>" style="flex: 1; height: 40px;">
                        <button type="submit" class="button button-primary"><?php echo Olama_School_Helpers::translate('Add'); ?></button>
                    </div>
                </form>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th><?php echo Olama_School_Helpers::translate('Personnel name'); ?></th><th style="width: 80px;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th></tr></thead>
                    <tbody>
                        <?php 
                        $cleaners = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_cleaners ORDER BY id DESC");
                        foreach ($cleaners as $cl): ?>
                            <tr>
                                <td><?php echo esc_html($cl->cleaner_name); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure?')">
                                        <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                                        <input type="hidden" name="olama_save_cleaning_config" value="1">
                                        <input type="hidden" name="config_type" value="cleaners">
                                        <input type="hidden" name="delete_cleaner" value="<?php echo $cl->id; ?>">
                                        <button type="submit" class="button button-link" style="color: #d63638;"><i class="dashicons dashicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($section === 'slots'): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                    <input type="hidden" name="olama_save_cleaning_config" value="1">
                    <input type="hidden" name="config_type" value="slots">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                        <input type="time" name="new_slot" style="flex: 1; height: 40px;">
                        <button type="submit" class="button button-primary"><?php echo Olama_School_Helpers::translate('Add'); ?></button>
                    </div>
                </form>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th><?php echo Olama_School_Helpers::translate('Time'); ?></th><th style="width: 80px;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th></tr></thead>
                    <tbody>
                        <?php 
                        $slots = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_slots ORDER BY slot_time ASC");
                        foreach ($slots as $sl): ?>
                            <tr>
                                <td><?php echo esc_html($sl->slot_time); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure?')">
                                        <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                                        <input type="hidden" name="olama_save_cleaning_config" value="1">
                                        <input type="hidden" name="config_type" value="slots">
                                        <input type="hidden" name="delete_slot" value="<?php echo $sl->id; ?>">
                                        <button type="submit" class="button button-link" style="color: #d63638;"><i class="dashicons dashicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($section === 'assignments'): ?>
                <p class="description" style="margin-bottom: 20px;">
                    <?php echo Olama_School_Helpers::translate('Assign a cleaner to each floor for automatic selection during checkup.'); ?>
                </p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo Olama_School_Helpers::translate('Floor'); ?></th>
                            <th><?php echo Olama_School_Helpers::translate('Assigned Cleaner'); ?></th>
                            <th><?php echo Olama_School_Helpers::translate('Responsible Supervisor'); ?></th>
                            <th style="width:100px;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $floors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors WHERE is_active = 1");
                        $cleaners = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_cleaners WHERE is_active = 1");
                        $supervisors = get_users(array('role__in' => array('supervisor', 'olama_supervisor', 'administrator', 'school_manager', 'editor'), 'number' => -1)); 
                        foreach ($floors as $fl): 
                            $assignment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_cleaning_assignments WHERE floor_id = %d", $fl->id));
                        ?>
                            <tr>
                                <form method="post">
                                    <?php wp_nonce_field('olama_save_cleaning_config'); ?>
                                    <input type="hidden" name="olama_save_cleaning_config" value="1">
                                    <input type="hidden" name="config_type" value="assignments">
                                    <input type="hidden" name="floor_id" value="<?php echo $fl->id; ?>">
                                    <td style="vertical-align: middle;"><strong><?php echo esc_html($fl->floor_name); ?></strong></td>
                                    <td>
                                        <select name="cleaner_id" style="width:100%;">
                                            <option value=""><?php echo Olama_School_Helpers::translate('Not Assigned'); ?></option>
                                            <?php foreach ($cleaners as $cl): ?>
                                                <option value="<?php echo $cl->id; ?>" <?php selected($assignment && $assignment->cleaner_id, $cl->id); ?>><?php echo esc_html($cl->cleaner_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="supervisor_id" style="width:100%;">
                                            <option value=""><?php echo Olama_School_Helpers::translate('Not Assigned'); ?></option>
                                            <?php foreach ($supervisors as $sv): ?>
                                                <option value="<?php echo $sv->ID; ?>" <?php selected($assignment && $assignment->supervisor_id, $sv->ID); ?>><?php echo esc_html($sv->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="submit" class="button button-small"><?php echo Olama_School_Helpers::translate('Assign'); ?></button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .cleaning-config-wrap [dir="rtl"] .config-sidebar { margin-left: 30px; margin-right: 0; }
    .cleaning-config-wrap [dir="rtl"] sidebar a { border-left: 0; border-right: 4px solid transparent; }
    .cleaning-config-wrap [dir="rtl"] sidebar a.active { border-right-color: #1e88e5; }
</style>
