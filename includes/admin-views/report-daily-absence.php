<?php
/**
 * Daily Absence Report Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

$date = isset($_GET['attendance_date']) ? sanitize_text_field($_GET['attendance_date']) : current_time('Y-m-d');
?>

<div class="wrap olama-school-wrap">
    <h1><?php echo Olama_School_Helpers::translate('Daily Absence Report'); ?></h1>

    <div class="card" style="padding: 15px; margin-bottom: 20px; max-width: 100%;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-reports">
            <input type="hidden" name="tab" value="daily_absence">
            
            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <div>
                    <label><strong><?php _e('Date:', 'olama-school'); ?></strong></label><br>
                    <input type="date" name="attendance_date" value="<?php echo esc_attr($date); ?>">
                </div>
                <div>
                    <button type="submit" class="button button-secondary"><?php _e('Filter', 'olama-school'); ?></button>
                </div>
            </div>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th width="100"><?php _e('UID', 'olama-school'); ?></th>
                <th><?php _e('Student Name', 'olama-school'); ?></th>
                <th><?php _e('Grade & Section', 'olama-school'); ?></th>
                <th><?php _e('Reason', 'olama-school'); ?></th>
                <th><?php _e('Recorded By', 'olama-school'); ?></th>
                <th width="150"><?php _e('Actions', 'olama-school'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($absentees)): ?>
                <tr><td colspan="6"><?php _e('No absentees found for this date.', 'olama-school'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($absentees as $res): 
                    $recorder = get_userdata($res->recorded_by);
                    $recorder_name = $recorder ? $recorder->display_name : __('Unknown', 'olama-school');
                ?>
                    <tr>
                        <td><?php echo esc_html($res->student_uid); ?></td>
                        <td><strong><?php echo esc_html($res->student_name); ?></strong></td>
                        <td><?php echo esc_html($res->grade_name . ' - ' . $res->section_name); ?></td>
                        <td><?php echo esc_html($res->reason); ?></td>
                        <td><?php echo esc_html($recorder_name); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=olama-school-follow-up&tab=student_attendance&grade_id=' . $res->grade_id . '&section_id=' . $res->section_id . '&attendance_date=' . $date); ?>" class="button button-small">
                                <?php _e('View/Edit', 'olama-school'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
