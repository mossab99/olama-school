<?php
/**
 * Academic Management - Calendar View
 */
if (!defined('ABSPATH')) {
    exit;
}

$years = Olama_School_Academic::get_years();
$selected_year_id = isset($_GET['manage_year']) ? intval($_GET['manage_year']) : 0;
?>

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
    <div class="olama-main-col">
        <div class="olama-card"
            style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <h2>
                <?php _e('Academic Years', 'olama-school'); ?>
            </h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <?php _e('ID', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Year Name', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Start Date', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('End Date', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Status', 'olama-school'); ?>
                        </th>
                        <th style="width: 250px;">
                            <?php _e('Actions', 'olama-school'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($years): ?>
                        <?php foreach ($years as $year): ?>
                            <tr class="<?php echo ($selected_year_id === $year->id) ? 'active-row' : ''; ?>"
                                style="<?php echo ($selected_year_id === $year->id) ? 'background-color: #f0f6fb;' : ''; ?>">
                                <td>
                                    <?php echo $year->id; ?>
                                </td>
                                <td><strong>
                                        <?php echo esc_html($year->year_name); ?>
                                    </strong></td>
                                <td>
                                    <?php echo esc_html($year->start_date); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($year->end_date); ?>
                                </td>
                                <td>
                                    <?php if ($year->is_active): ?>
                                        <span class="status-pill active"
                                            style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <?php _e('Active', 'olama-school'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill inactive"
                                            style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            <?php _e('Inactive', 'olama-school'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=olama-school-academic&manage_year=' . $year->id); ?>"
                                        class="button button-small">
                                        <?php _e('Manage Semesters', 'olama-school'); ?>
                                    </a>

                                    <?php if (!$year->is_active): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&action=activate&year_id=' . $year->id), 'olama_activate_year_' . $year->id); ?>"
                                            class="button button-small primary">
                                            <?php _e('Activate', 'olama-school'); ?>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&action=delete&year_id=' . $year->id), 'olama_delete_year_' . $year->id); ?>"
                                        class="button button-small delete-button" style="color: #dc2626;"
                                        onclick="return confirm('<?php _e('Delete Year and its Semesters?', 'olama-school'); ?>')">
                                        <?php _e('Delete', 'olama-school'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <?php _e('No academic years found.', 'olama-school'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($selected_year_id) {
            $selected_year = null;
            foreach ($years as $y) {
                if (intval($y->id) === $selected_year_id) {
                    $selected_year = $y;
                    break;
                }
            }

            if ($selected_year) {
                $semesters = Olama_School_Academic::get_semesters($selected_year_id);
                ?>
                <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;">
                            <?php printf(__('Semesters for %s', 'olama-school'), esc_html($selected_year->year_name)); ?>
                        </h2>
                        <button type="button" class="button"
                            onclick="document.getElementById('add-semester-form').style.display='block'">
                            <?php _e('Add Semester', 'olama-school'); ?>
                        </button>
                    </div>

                    <div id="add-semester-form"
                        style="display: none; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">
                        <form method="post" action="">
                            <?php wp_nonce_field('olama_add_semester'); ?>
                            <input type="hidden" name="semester_year_id" value="<?php echo $selected_year_id; ?>" />
                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Name', 'olama-school'); ?>
                                    </label>
                                    <select name="semester_name" required>
                                        <option value="1st Semester">
                                            <?php _e('1st Semester', 'olama-school'); ?>
                                        </option>
                                        <option value="2nd Semester">
                                            <?php _e('2nd Semester', 'olama-school'); ?>
                                        </option>
                                        <option value="Summer Semester">
                                            <?php _e('Summer Semester', 'olama-school'); ?>
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Start Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="sem_start_date" required />
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('End Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="end_date" required />
                                </div>
                                <div>
                                    <?php submit_button(__('Add', 'olama-school'), 'primary', 'add_semester', false); ?>
                                    <button type="button" class="button"
                                        onclick="document.getElementById('add-semester-form').style.display='none'">
                                        <?php _e('Cancel', 'olama-school'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>
                                    <?php _e('Semester Name', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Start Date', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('End Date', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Actions', 'olama-school'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($semesters): ?>
                                <?php foreach ($semesters as $sem): ?>
                                    <tr>
                                        <td><strong>
                                                <?php echo esc_html($sem->semester_name); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo esc_html($sem->start_date); ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($sem->end_date); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id . '&action=delete_semester&semester_id=' . $sem->id), 'olama_delete_semester_' . $sem->id); ?>"
                                                class="button button-small" style="color: #dc2626;"
                                                onclick="return confirm('<?php _e('Delete Semester?', 'olama-school'); ?>')">
                                                <?php _e('Delete', 'olama-school'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <?php _e('No semesters defined for this year.', 'olama-school'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Events Section -->
                <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0;">
                            <?php printf(__('Events for %s', 'olama-school'), esc_html($selected_year->year_name)); ?>
                        </h2>
                        <button type="button" class="button"
                            onclick="document.getElementById('add-event-form').style.display='block'">
                            <?php _e('Add Event', 'olama-school'); ?>
                        </button>
                    </div>

                    <div id="add-event-form"
                        style="display: none; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">
                        <form method="post"
                            action="<?php echo esc_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id)); ?>">
                            <?php wp_nonce_field('olama_add_event'); ?>
                            <input type="hidden" name="event_year_id" value="<?php echo $selected_year_id; ?>" />
                            <div
                                style="display: grid; grid-template-columns: 1fr 150px 150px 100px; gap: 10px; align-items: flex-end;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Description', 'olama-school'); ?>
                                    </label>
                                    <input type="text" name="event_description" required style="width: 100%;" />
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Start Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="event_start_date" required style="width: 100%;"
                                        min="<?php echo esc_attr($selected_year->start_date); ?>"
                                        max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('End Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="event_end_date" required style="width: 100%;"
                                        min="<?php echo esc_attr($selected_year->start_date); ?>"
                                        max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                </div>
                                <div>
                                    <?php submit_button(__('Add', 'olama-school'), 'primary', 'add_event', false); ?>
                                    <button type="button" class="button button-small" style="margin-top: 5px; width: 100%;"
                                        onclick="document.getElementById('add-event-form').style.display='none'">
                                        <?php _e('Cancel', 'olama-school'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div id="edit-event-form"
                        style="display: none; background: #fffbeb; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #d97706;">
                        <form method="post"
                            action="<?php echo esc_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id)); ?>">
                            <?php wp_nonce_field('olama_update_event'); ?>
                            <input type="hidden" name="edit_event_id" id="edit_event_id" />
                            <div
                                style="display: grid; grid-template-columns: 1fr 150px 150px 100px; gap: 10px; align-items: flex-end;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Description', 'olama-school'); ?>
                                    </label>
                                    <input type="text" name="edit_event_description" id="edit_event_description" required
                                        style="width: 100%;" />
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('Start Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="edit_event_start_date" id="edit_event_start_date" required
                                        style="width: 100%;" min="<?php echo esc_attr($selected_year->start_date); ?>"
                                        max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                                        <?php _e('End Date', 'olama-school'); ?>
                                    </label>
                                    <input type="date" name="edit_event_end_date" id="edit_event_end_date" required
                                        style="width: 100%;" min="<?php echo esc_attr($selected_year->start_date); ?>"
                                        max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                </div>
                                <div>
                                    <?php submit_button(__('Update', 'olama-school'), 'primary', 'update_event', false); ?>
                                    <button type="button" class="button button-small" style="margin-top: 5px; width: 100%;"
                                        onclick="document.getElementById('edit-event-form').style.display='none'">
                                        <?php _e('Cancel', 'olama-school'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>
                                    <?php _e('Event Description', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Start Date', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('End Date', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Actions', 'olama-school'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $events = Olama_School_Academic::get_events($selected_year_id);
                            if ($events): ?>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><strong>
                                                <?php echo esc_html($event->event_description); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo esc_html($event->start_date); ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html($event->end_date); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small olama-edit-event-btn"
                                                data-id="<?php echo esc_attr($event->id); ?>"
                                                data-desc="<?php echo esc_attr($event->event_description); ?>"
                                                data-start="<?php echo esc_attr($event->start_date); ?>"
                                                data-end="<?php echo esc_attr($event->end_date); ?>" onclick="olamaEditEvent(this)">
                                                <?php _e('Edit', 'olama-school'); ?>
                                            </button>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id . '&action=delete_event&event_id=' . $event->id), 'olama_delete_event_' . $event->id); ?>"
                                                class="button button-small" style="color: #dc2626;"
                                                onclick="return confirm('<?php _e('Delete Event?', 'olama-school'); ?>')">
                                                <?php _e('Delete', 'olama-school'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <?php _e('No events defined for this year.', 'olama-school'); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php }
        } ?>
    </div>

    <div class="olama-side-col">
        <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
            <h2 style="margin-top: 0;">
                <?php _e('Add Academic Year', 'olama-school'); ?>
            </h2>
            <form method="post" action="">
                <?php wp_nonce_field('olama_add_year'); ?>
                <p>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php _e('Year Name', 'olama-school'); ?>
                    </label>
                    <input type="text" name="year_name" required class="widefat" placeholder="e.g. 2025-2026" />
                </p>
                <p>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php _e('Start Date', 'olama-school'); ?>
                    </label>
                    <input type="date" name="start_date" required class="widefat" />
                </p>
                <p>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php _e('End Date', 'olama-school'); ?>
                    </label>
                    <input type="date" name="end_date" required class="widefat" />
                </p>
                <p>
                    <label><input type="checkbox" name="is_active" value="1" />
                        <?php _e('Set as Active', 'olama-school'); ?>
                    </label>
                </p>
                <?php submit_button(__('Add Year', 'olama-school'), 'primary', 'add_year', true, array('style' => 'width: 100%;')); ?>
            </form>
        </div>
    </div>
</div>

<script>
    function olamaEditEvent(btn) {
        document.getElementById('add-event-form').style.display = 'none';
        const editForm = document.getElementById('edit-event-form');
        editForm.style.display = 'block';

        document.getElementById('edit_event_id').value = btn.getAttribute('data-id');
        document.getElementById('edit_event_description').value = btn.getAttribute('data-desc');
        document.getElementById('edit_event_start_date').value = btn.getAttribute('data-start');
        document.getElementById('edit_event_end_date').value = btn.getAttribute('data-end');

        editForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>