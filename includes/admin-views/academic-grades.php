<?php
/**
 * Academic Management - Grades & Sections View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['manage_grade']) ? intval($_GET['manage_grade']) : 0;
$selected_grade = null;
if ($selected_grade_id) {
    $selected_grade = Olama_School_Grade::get_grade($selected_grade_id);
}
?>

<div
    style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
    <form method="post" action="">
        <?php wp_nonce_field('olama_export_grades'); ?>
        <input type="hidden" name="olama_export_grades" value="true" />
        <button type="submit" class="button"><span class="dashicons dashicons-export" style="margin-top: 4px;"></span>
            <?php _e('Export Grades & Sections (CSV)', 'olama-school'); ?>
        </button>
    </form>

    <div style="border-left: 1px solid #ddd; height: 30px; margin: 0 10px;"></div>

    <form method="post" action="" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
        <?php wp_nonce_field('olama_import_grades'); ?>
        <input type="hidden" name="olama_import_type" value="grades" />
        <input type="file" name="olama_import_file" accept=".csv" required />
        <button type="submit" class="button button-primary"><span class="dashicons dashicons-import"
                style="margin-top: 4px;"></span>
            <?php _e('Import Grades & Sections', 'olama-school'); ?>
        </button>
    </form>
</div>
<div class="olama-flex" style="display: flex; gap: 20px;">
    <div class="olama-main-col" style="flex: 2;">
        <!-- Existing Grades Card -->
        <div class="olama-card"
            style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <h2>
                <?php _e('Existing Grades', 'olama-school'); ?>
            </h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <?php _e('ID', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Grade Name', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Level', 'olama-school'); ?>
                        </th>
                        <th>
                            <?php _e('Periods', 'olama-school'); ?>
                        </th>
                        <th style="width: 250px;">
                            <?php _e('Actions', 'olama-school'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($grades): ?>
                        <?php foreach ($grades as $grade): ?>
                            <tr class="<?php echo ($selected_grade_id === (int) $grade->id) ? 'olama-selected-row' : ''; ?>"
                                style="<?php echo ($selected_grade_id === (int) $grade->id) ? 'background-color: #f0f7ff;' : ''; ?>">
                                <td>
                                    <?php echo $grade->id; ?>
                                </td>
                                <td><strong>
                                        <?php echo esc_html($grade->grade_name); ?>
                                    </strong></td>
                                <td>
                                    <?php echo esc_html($grade->grade_level); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($grade->periods_count ?? 8); ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $grade->id); ?>"
                                        class="button button-small button-primary">
                                        <?php _e('Manage Sections', 'olama-school'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&action=edit_grade&grade_id=' . $grade->id); ?>"
                                        class="button button-small">
                                        <?php _e('Edit', 'olama-school'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=grades&action=delete_grade&grade_id=' . $grade->id), 'olama_delete_grade_' . $grade->id); ?>"
                                        class="button button-small" style="color: #dc2626;"
                                        onclick="return confirm('<?php _e('Delete Grade?', 'olama-school'); ?>')">
                                        <?php _e('Delete', 'olama-school'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <?php _e('No grades found.', 'olama-school'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($selected_grade): ?>
            <!-- Sections Management Card -->
            <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>
                        <?php echo sprintf(__('Sections for %s', 'olama-school'), esc_html($selected_grade->grade_name)); ?>
                    </h2>
                    <button type="button" class="button button-primary" onclick="olamaToggleForm('olama-add-section-form')">
                        <?php _e('Add Section', 'olama-school'); ?>
                    </button>
                </div>

                <!-- Add Section Form (Hidden by default) -->
                <div id="olama-add-section-form"
                    style="display: none; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
                    <h3>
                        <?php _e('Add New Section', 'olama-school'); ?>
                    </h3>
                    <form method="post"
                        action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id); ?>">
                        <?php wp_nonce_field('olama_add_section'); ?>
                        <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php _e('Section Name', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="section_name" required placeholder="e.g. Section A"
                                        class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php _e('Room Number', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="room_number" placeholder="e.g. 101" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px;">
                            <?php submit_button(__('Save Section', 'olama-school'), 'primary', 'add_section', false); ?>
                            <button type="button" class="button" onclick="olamaToggleForm('olama-add-section-form')">
                                <?php _e('Cancel', 'olama-school'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Edit Section Form (Hidden by default) -->
                <div id="olama-edit-section-form"
                    style="display: none; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
                    <h3>
                        <?php _e('Edit Section', 'olama-school'); ?>
                    </h3>
                    <form method="post"
                        action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id); ?>">
                        <?php wp_nonce_field('olama_edit_section'); ?>
                        <input type="hidden" name="section_id" id="edit_section_id" />
                        <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php _e('Section Name', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="section_name" id="edit_section_name" required
                                        class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php _e('Room Number', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="room_number" id="edit_room_number" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top: 15px;">
                            <?php submit_button(__('Update Section', 'olama-school'), 'primary', 'edit_section', false); ?>
                            <button type="button" class="button" onclick="olamaToggleForm('olama-edit-section-form')">
                                <?php _e('Cancel', 'olama-school'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Section Name', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Room', 'olama-school'); ?>
                            </th>
                            <th style="width: 150px;">
                                <?php _e('Actions', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $grade_sections = Olama_School_Section::get_by_grade($selected_grade_id);
                        if ($grade_sections): ?>
                            <?php foreach ($grade_sections as $sec): ?>
                                <tr>
                                    <td><strong>
                                            <?php echo esc_html($sec->section_name); ?>
                                        </strong></td>
                                    <td>
                                        <?php echo esc_html($sec->room_number); ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="olamaEditSection(this)"
                                            data-id="<?php echo $sec->id; ?>"
                                            data-name="<?php echo esc_attr($sec->section_name); ?>"
                                            data-room="<?php echo esc_attr($sec->room_number); ?>">
                                            <?php _e('Edit', 'olama-school'); ?>
                                        </button>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id . '&action=delete_section&section_id=' . $sec->id), 'olama_delete_section_' . $sec->id); ?>"
                                            class="button button-small" style="color: #dc2626;"
                                            onclick="return confirm('<?php _e('Delete Section?', 'olama-school'); ?>')">
                                            <?php _e('Delete', 'olama-school'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">
                                    <?php _e('No sections defined for this grade.', 'olama-school'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="olama-side-col" style="flex: 1;">
        <!-- Add Grade Form -->
        <div class="olama-card"
            style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <?php if (isset($_GET['action']) && $_GET['action'] === 'edit_grade' && isset($_GET['grade_id'])):
                $edit_grade_id = intval($_GET['grade_id']);
                $edit_grade = Olama_School_Grade::get_grade($edit_grade_id);
                if ($edit_grade): ?>
                    <h2>
                        <?php _e('Edit Grade', 'olama-school'); ?>
                    </h2>
                    <form method="post" action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades'); ?>">
                        <?php wp_nonce_field('olama_edit_grade'); ?>
                        <input type="hidden" name="grade_id" value="<?php echo $edit_grade_id; ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php _e('Grade Name', 'olama-school'); ?>
                                </th>
                                <td><input type="text" name="grade_name" required class="regular-text"
                                        value="<?php echo esc_attr($edit_grade->grade_name); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php _e('Grade Level', 'olama-school'); ?>
                                </th>
                                <td><input type="number" name="grade_level" required
                                        value="<?php echo esc_attr($edit_grade->grade_level); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php _e('Periods per Day', 'olama-school'); ?>
                                </th>
                                <td><input type="number" name="periods_count" required min="1" max="15"
                                        value="<?php echo esc_attr($edit_grade->periods_count ?? 8); ?>" /></td>
                            </tr>
                        </table>
                        <?php submit_button(__('Update Grade', 'olama-school'), 'primary', 'edit_grade', false); ?>
                        <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades'); ?>" class="button">
                            <?php _e('Cancel', 'olama-school'); ?>
                        </a>
                    </form>
                <?php else: ?>
                    <div class="error">
                        <p>
                            <?php _e('Grade not found.', 'olama-school'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <h2>
                    <?php _e('Add New Grade', 'olama-school'); ?>
                </h2>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_add_grade'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('Grade Name', 'olama-school'); ?>
                            </th>
                            <td><input type="text" name="grade_name" required class="regular-text"
                                    placeholder="e.g. Grade 5" /></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('Grade Level', 'olama-school'); ?>
                            </th>
                            <td><input type="number" name="grade_level" required placeholder="5" /></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('Periods/Day', 'olama-school'); ?>
                            </th>
                            <td><input type="number" name="periods_count" required min="1" max="15" value="8" /></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Add Grade', 'olama-school'), 'primary', 'add_grade'); ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function olamaToggleForm(formId) {
        var forms = ['olama-add-section-form', 'olama-edit-section-form'];
        forms.forEach(function (id) {
            var el = document.getElementById(id);
            if (id === formId) {
                el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function olamaEditSection(btn) {
        var id = btn.getAttribute('data-id');
        var name = btn.getAttribute('data-name');
        var room = btn.getAttribute('data-room');

        document.getElementById('edit_section_id').value = id;
        document.getElementById('edit_section_name').value = name;
        document.getElementById('edit_room_number').value = room;

        olamaToggleForm('olama-edit-section-form');
        document.getElementById('olama-edit-section-form').scrollIntoView({ behavior: 'smooth' });
    }
</script>