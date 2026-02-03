<?php
/**
 * Admin View: Teacher Office Hours management
 */

if (!defined('ABSPATH')) {
    exit;
}

$teachers = Olama_School_Teacher::get_teachers();
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Get academic years and semesters
$years = Olama_School_Academic::get_years();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));

$office_hours = array();
if ($teacher_id) {
    $office_hours = Olama_School_Teacher::get_office_hours($teacher_id, $selected_year_id, $selected_semester_id);
}

$days = array(
    'Sunday' => __('Sunday', 'olama-school'),
    'Monday' => __('Monday', 'olama-school'),
    'Tuesday' => __('Tuesday', 'olama-school'),
    'Wednesday' => __('Wednesday', 'olama-school'),
    'Thursday' => __('Thursday', 'olama-school'),
    'Friday' => __('Friday', 'olama-school'),
    'Saturday' => __('Saturday', 'olama-school'),
);
?>

<div class="olama-office-hours-admin">
    <div class="olama-admin-header-box">
        <h2 style="display: flex; align-items: center; gap: 10px;">
            <span class="dashicons dashicons-calendar-alt"
                style="font-size: 24px; width: 24px; height: 24px; color: #2563eb;"></span>
            <?php _e('Teacher Office Hours', 'olama-school'); ?>
        </h2>
    </div>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'office_hours_saved'): ?>
        <div class="updated notice is-dismissible">
            <p><?php _e('Office hours saved successfully.', 'olama-school'); ?></p>
        </div>
    <?php endif; ?>

    <div class="olama-filter-row"
        style="margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        <div class="filter-item">
            <label
                style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;"><?php _e('Select Teacher', 'olama-school'); ?></label>
            <select name="teacher_id" id="olama-teacher-selector" class="olama-select2" style="min-width: 280px;">
                <option value=""><?php _e('-- Select Teacher --', 'olama-school'); ?></option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo $teacher->ID; ?>" <?php selected($teacher_id, $teacher->ID); ?>>
                        <?php echo esc_html($teacher->display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-item">
            <label
                style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;"><?php _e('Academic Year', 'olama-school'); ?></label>
            <select name="academic_year_id" id="olama-year-selector" style="min-width: 180px;">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                        <?php echo esc_html($y->year_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-item">
            <label
                style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;"><?php _e('Semester', 'olama-school'); ?></label>
            <select name="semester_id" id="olama-semester-selector" style="min-width: 180px;">
                <?php foreach ($semesters as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                        <?php echo esc_html($s->semester_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="button" class="button button-primary button-large" id="olama-load-hours"
            style="height: 40px; padding: 0 20px;">
            <?php _e('Load Hours', 'olama-school'); ?>
        </button>
    </div>

    <?php if ($teacher_id): ?>
        <div class="olama-card"
            style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="olama-office-hours-form">
                <?php wp_nonce_field('olama_save_office_hours', 'olama_office_hours_nonce'); ?>
                <input type="hidden" name="action" value="olama_save_office_hours">
                <input type="hidden" name="olama_save_office_hours" value="1">
                <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
                <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">

                <h3 style="margin-top: 0; margin-bottom: 25px; color: #1e293b; font-weight: 700;">
                    <?php echo sprintf(__('Manage Office Hours for %s', 'olama-school'), get_userdata($teacher_id)->display_name); ?>
                </h3>

                <table class="wp-list-table widefat fixed striped" id="olama-office-hours-table"
                    style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr>
                            <th style="font-weight: 700; color: #475569;"><?php _e('Day of the Week', 'olama-school'); ?>
                            </th>
                            <th style="font-weight: 700; color: #475569;">
                                <?php _e('Available Time Slots (e.g., 08:00 AM - 09:00 AM)', 'olama-school'); ?>
                            </th>
                            <th style="width: 100px; text-align: center; font-weight: 700; color: #475569;">
                                <?php _e('Action', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="office-hours-body">
                        <?php if (empty($office_hours)): ?>
                            <tr class="oh-row empty-row">
                                <td colspan="3" style="text-align: center; padding: 30px; color: #94a3b8; font-style: italic;">
                                    <?php _e('No office hours defined yet. Click "Add Slot" to begin.', 'olama-school'); ?>
                                </td>
                            </tr>
                        <?php else:
                            foreach ($office_hours as $index => $oh): ?>
                                <tr class="oh-row">
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
                                        <button type="button" class="button button-link-delete remove-row"
                                            title="<?php _e('Remove', 'olama-school'); ?>">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding: 15px;">
                                <button type="button" class="button" id="add-oh-row"
                                    style="background: #f1f5f9; border-color: #cbd5e1; color: #475569;">
                                    <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                                    <?php _e('Add Slot', 'olama-school'); ?>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div class="submit-row" style="margin-top: 30px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="button button-primary button-large"
                        style="height: 45px; padding: 0 30px; font-weight: 600;">
                        <?php _e('Save Office Hours', 'olama-school'); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    jQuery(document).ready(function ($) {
        var $body = $('#office-hours-body');
        var rowCounter = <?php echo count($office_hours); ?>;

        $('#olama-load-hours').on('click', function () {
            var tid = $('#olama-teacher-selector').val();
            var yid = $('#olama-year-selector').val();
            var sid = $('#olama-semester-selector').val();

            if (!tid) {
                alert('<?php _e('Please select a teacher first.', 'olama-school'); ?>');
                return;
            }

            var url = '<?php echo admin_url('admin.php?page=olama-school-academic&tab=office_hours'); ?>';
            url += '&teacher_id=' + tid;
            if (yid) url += '&academic_year_id=' + yid;
            if (sid) url += '&semester_id=' + sid;
            window.location.href = url;
        });

        $('#add-oh-row').on('click', function (e) {
            e.preventDefault();
            $('.empty-row').hide();

            var html = '<tr class="oh-row">' +
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
                '<button type="button" class="button button-link-delete remove-row" title="<?php _e('Remove', 'olama-school'); ?>">' +
                '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
                '</td>' +
                '</tr>';

            $body.append(html);
            rowCounter++;
        });

        $(document).on('click', '.remove-row', function () {
            $(this).closest('tr').remove();
            if ($body.find('tr.oh-row:not(.empty-row)').length === 0) {
                $('.empty-row').show();
            }
        });

        // Academic Year change should update Semesters
        $('#olama-year-selector').on('change', function () {
            var yearId = $(this).val();
            var semesterSelector = $('#olama-semester-selector');

            semesterSelector.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'olama_get_semesters',
                    academic_year_id: yearId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        semesterSelector.empty();
                        $.each(response.data, function (i, sem) {
                            semesterSelector.append($('<option>', {
                                value: sem.id,
                                text: sem.semester_name
                            }));
                        });
                    }
                },
                complete: function () {
                    semesterSelector.prop('disabled', false);
                }
            });
        });
    });
</script>