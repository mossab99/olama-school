<?php
/**
 * Teacher Office Hours View
 */
if (!defined('ABSPATH')) {
    exit;
}

$office_hours = Olama_School_Teacher::get_office_hours($selected_teacher_id);
$teacher_user = get_userdata($selected_teacher_id);
$teacher_name = $teacher_user ? $teacher_user->display_name : __('Unknown Teacher', 'olama-school');

$days = array(
    'Sunday' => __('Sunday', 'olama-school'),
    'Monday' => __('Monday', 'olama-school'),
    'Tuesday' => __('Tuesday', 'olama-school'),
    'Wednesday' => __('Wednesday', 'olama-school'),
    'Thursday' => __('Thursday', 'olama-school'),
);
?>

<div class="olama-card"
    style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin: 0; color: #1e293b; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center;">
            <span class="dashicons dashicons-calendar-alt"
                style="font-size: 24px; width: 24px; height: 24px; margin-inline-end: 10px; color: #2563eb;"></span>
            <?php printf(__('Office Hours: %s', 'olama-school'), esc_html($teacher_name)); ?>
        </h2>
        <?php if ($is_admin && !empty($teachers)): ?>
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-weight: 600; color: #475569;"><?php _e('Switch Teacher:', 'olama-school'); ?></label>
                <select
                    onchange="window.location.href='<?php echo admin_url('admin.php?page=olama-school-plans&tab=office_hours&teacher_id='); ?>' + this.value">
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?php echo $t->ID; ?>" <?php selected($selected_teacher_id, $t->ID); ?>>
                            <?php echo esc_html($t->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'office_hours_saved'): ?>
        <div class="updated notice is-dismissible" style="margin: 0 0 20px 0;">
            <p><?php _e('Office hours saved successfully.', 'olama-school'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" id="olama-office-hours-form"
        action="<?php echo admin_url('admin.php?page=olama-school-plans&tab=office_hours'); ?>">
        <?php wp_nonce_field('olama_save_office_hours', 'olama_office_hours_nonce'); ?>
        <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher_id; ?>">
        <input type="hidden" name="olama_save_office_hours" value="1">

        <table class="wp-list-table widefat fixed striped" id="office-hours-table">
            <thead>
                <tr>
                    <th style="width: 250px;"><?php _e('Day of the Week', 'olama-school'); ?></th>
                    <th><?php _e('Free Time / Slots', 'olama-school'); ?></th>
                    <th style="width: 80px; text-align: center;"><?php _e('Action', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody id="office-hours-body">
                <?php if (empty($office_hours)): ?>
                    <tr class="empty-row">
                        <td colspan="3" style="text-align: center; padding: 20px; color: #64748b;">
                            <?php _e('No office hours defined yet. Click "Add Slot" to begin.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($office_hours as $index => $oh): ?>
                        <tr>
                            <td>
                                <select name="slots[<?php echo $index; ?>][day_name]" style="width: 100%;">
                                    <?php foreach ($days as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($oh->day_name, $val); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="slots[<?php echo $index; ?>][time]"
                                    value="<?php echo esc_attr($oh->available_time); ?>"
                                    placeholder="<?php _e('e.g., 10:00 AM - 12:00 PM', 'olama-school'); ?>"
                                    style="width: 100%;">
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button button-link-delete remove-slot"
                                    title="<?php _e('Remove', 'olama-school'); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="button" id="add-slot-btn">
                <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                <?php _e('Add Slot', 'olama-school'); ?>
            </button>
            <button type="submit" class="button button-primary button-large">
                <?php _e('Save Office Hours', 'olama-school'); ?>
            </button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function ($) {
        var $body = $('#office-hours-body');
        var $emptyRow = $('.empty-row');
        var rowCounter = <?php echo count($office_hours); ?>;

        $('#add-slot-btn').on('click', function (e) {
            e.preventDefault();
            $emptyRow.hide();

            var html = '<tr>' +
                '<td>' +
                '<select name="slots[' + rowCounter + '][day_name]" style="width: 100%;">' +
                <?php foreach ($days as $val => $label): ?>
                '<option value="<?php echo esc_attr($val); ?>"><?php echo esc_js($label); ?></option>' +
                <?php endforeach; ?>
            '</select>' +
                '</td>' +
                '<td>' +
                '<input type="text" name="slots[' + rowCounter + '][time]" placeholder="<?php _e('e.g., 10:00 AM - 12:00 PM', 'olama-school'); ?>" style="width: 100%;">' +
                '</td>' +
                '<td style="text-align: center;">' +
                '<button type="button" class="button button-link-delete remove-slot" title="<?php _e('Remove', 'olama-school'); ?>">' +
                '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
                '</td>' +
                '</tr>';

            $body.append(html);
            rowCounter++;
        });

        $body.on('click', '.remove-slot', function (e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            if ($body.find('tr:not(.empty-row)').length === 0) {
                $emptyRow.show();
            }
        });

        // Debug help
        console.log('Office Hours JS Loaded. Teacher ID:', <?php echo $selected_teacher_id; ?>);
    });
</script>