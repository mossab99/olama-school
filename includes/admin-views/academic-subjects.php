<?php
/**
 * Academic Management - Subjects View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$subjects = Olama_School_Subject::get_subjects();

// Group subjects by grade
$grouped_subjects = array();
foreach ($subjects as $subject) {
    if (isset($subject->grade_name)) {
        $grouped_subjects[$subject->grade_name][] = $subject;
    }
}

// Handle Edit Mode
$edit_subject = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_subject' && isset($_GET['subject_id'])) {
    $edit_subject = Olama_School_Subject::get_subject(intval($_GET['subject_id']));
}
?>

<div class="olama-subjects-container">
    <div
        style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
        <div style="display: flex; gap: 10px; align-items: center;">
            <form method="post" action="">
                <?php wp_nonce_field('olama_export_subjects'); ?>
                <input type="hidden" name="olama_export_subjects" value="true" />
                <button type="submit" class="button"><span class="dashicons dashicons-export"
                        style="margin-top: 4px;"></span>
                    <?php _e('Export Subjects (CSV)', 'olama-school'); ?></button>
            </form>

            <div style="border-left: 1px solid #ddd; height: 30px; margin: 0 10px;"></div>

            <form method="post" action="" enctype="multipart/form-data"
                style="display: flex; gap: 10px; align-items: center;">
                <?php wp_nonce_field('olama_import_subjects'); ?>
                <input type="hidden" name="olama_import_type" value="subjects" />
                <input type="file" name="olama_import_file" accept=".csv" required />
                <button type="submit" class="button button-primary"><span class="dashicons dashicons-import"
                        style="margin-top: 4px;"></span> <?php _e('Import Subjects', 'olama-school'); ?></button>
            </form>

            <div style="border-left: 1px solid #ddd; height: 30px; margin: 0 10px;"></div>

            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=subjects&action=clear_all_subjects'), 'olama_clear_all_subjects'); ?>"
                class="button button-link-delete" style="color: #b91c1c; font-weight: 600;"
                onclick="return confirm('<?php echo esc_js(Olama_School_Helpers::translate('Are you sure you want to delete ALL subjects? This action cannot be undone!')); ?>')">
                <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Clear All Subjects'); ?>
            </a>
        </div>

        <button type="button" class="button button-primary button-large" onclick="olamaOpenSubjectModal('add')">
            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
            <?php _e('Add New Subject', 'olama-school'); ?>
        </button>
    </div>

    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
        <h2><?php _e('Existing Subjects', 'olama-school'); ?></h2>
        <?php if ($grouped_subjects): ?>
            <?php foreach ($grouped_subjects as $grade_name => $grade_subjects): ?>
                <h3
                    style="background: #f8f9fa; padding: 12px 18px; border-left: 4px solid #2271b1; margin-top: 20px; font-size: 1.1rem; border-radius: 4px;">
                    <?php echo esc_html($grade_name); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped"
                    style="margin-bottom: 30px; border-radius: 4px; overflow: hidden;">
                    <thead>
                        <tr>
                            <th><?php _e('Subject Name', 'olama-school'); ?></th>
                            <th style="width: 100px;"><?php _e('Code', 'olama-school'); ?></th>
                            <th style="width: 80px; text-align: center;"><?php _e('Color', 'olama-school'); ?></th>
                            <th style="width: 100px; text-align: center;"><?php _e('Status', 'olama-school'); ?></th>
                            <th style="width: 160px;"><?php _e('Actions', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grade_subjects as $subject): ?>
                            <tr>
                                <td><strong><?php echo esc_html($subject->subject_name); ?></strong></td>
                                <td><code><?php echo esc_html($subject->subject_code); ?></code></td>
                                <td style="text-align: center;">
                                    <span
                                        style="display: inline-block; width: 24px; height: 24px; background: <?php echo esc_attr($subject->color_code); ?>; border: 1px solid #ccc; border-radius: 4px;"></span>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $is_active = isset($subject->is_active) ? (int) $subject->is_active : 1;
                                    if ($is_active): ?>
                                        <span class="olama-status-pill olama-status-published"
                                            style="font-size: 0.7rem;"><?php _e('Active', 'olama-school'); ?></span>
                                    <?php else: ?>
                                        <span class="olama-status-pill olama-status-draft"
                                            style="font-size: 0.7rem;"><?php _e('Inactive', 'olama-school'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="display: flex; gap: 5px;">
                                    <?php
                                    // Ensure is_active is present for the JS data
                                    if (!isset($subject->is_active))
                                        $subject->is_active = 1;
                                    ?>
                                    <button type="button" class="button button-small"
                                        onclick="olamaOpenSubjectModal('edit', <?php echo htmlspecialchars(json_encode($subject)); ?>)">
                                        <?php _e('Edit', 'olama-school'); ?>
                                    </button>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=subjects&action=delete_subject&subject_id=' . $subject->id), 'olama_delete_subject_' . $subject->id); ?>"
                                        class="button button-small" style="color: #dc2626;"
                                        onclick="return confirm('<?php _e('Delete Subject?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 20px; text-align: center; color: #666; font-style: italic;">
                <?php _e('No subjects found. Add your first subject using the button above.', 'olama-school'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Subject Modal -->
<div id="olama-subject-modal" class="olama-modal" style="display: none;">
    <div class="olama-modal-content">
        <div class="olama-modal-header">
            <h2 id="modal-title"><?php _e('Add Subject', 'olama-school'); ?></h2>
            <span class="olama-modal-close" onclick="olamaCloseSubjectModal()">&times;</span>
        </div>
        <div class="olama-modal-body">
            <form method="post" action="" id="olama-subject-form">
                <?php wp_nonce_field('olama_subject_action', '_wpnonce', true, true); ?>
                <input type="hidden" name="subject_id" id="modal-subject-id" value="" />
                <input type="hidden" name="subject_action_type" id="modal-action-type" value="add" />

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Subject Name', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_name" id="modal-subject-name" required
                                class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Subject Code', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_code" id="modal-subject-code" placeholder="e.g. ENG01"
                                class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                        <td>
                            <select name="grade_id" id="modal-grade-id" required style="width: 100%; max-width: 25em;">
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade->id; ?>"><?php echo esc_html($grade->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Color Code', 'olama-school'); ?></th>
                        <td><input type="color" name="color_code" id="modal-color-code" value="#3498db" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Status', 'olama-school'); ?></th>
                        <td>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="hidden" name="is_active" value="0" />
                                <input type="checkbox" name="is_active" id="modal-is-active" checked value="1" />
                                <?php _e('Active (Subject available for planning)', 'olama-school'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <div class="olama-modal-footer">
                    <button type="submit" class="button button-primary button-large"
                        id="modal-submit-btn"><?php _e('Add Subject', 'olama-school'); ?></button>
                    <button type="button" class="button button-large"
                        onclick="olamaCloseSubjectModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function olamaOpenSubjectModal(mode, data = null) {
        const modal = document.getElementById('olama-subject-modal');
        const title = document.getElementById('modal-title');
        const submitBtn = document.getElementById('modal-submit-btn');
        const actionInput = document.getElementById('modal-action-type');

        if (mode === 'edit' && data) {
            title.innerText = '<?php _e('Edit Subject', 'olama-school'); ?>';
            submitBtn.innerText = '<?php _e('Update Subject', 'olama-school'); ?>';
            actionInput.value = 'edit';

            document.getElementById('modal-subject-id').value = data.id;
            document.getElementById('modal-subject-name').value = data.subject_name;
            document.getElementById('modal-subject-code').value = data.subject_code;
            document.getElementById('modal-grade-id').value = data.grade_id;
            document.getElementById('modal-color-code').value = data.color_code;
            document.getElementById('modal-is-active').checked = parseInt(data.is_active || 1) === 1;
        } else {
            title.innerText = '<?php _e('Add Subject', 'olama-school'); ?>';
            submitBtn.innerText = '<?php _e('Add Subject', 'olama-school'); ?>';
            actionInput.value = 'add';

            document.getElementById('olama-subject-form').reset();
            document.getElementById('modal-subject-id').value = '';
            document.getElementById('modal-color-code').value = '#3498db';
            document.getElementById('modal-is-active').checked = true;
        }

        modal.style.display = 'block';
    }

    function olamaCloseSubjectModal() {
        document.getElementById('olama-subject-modal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        const modal = document.getElementById('olama-subject-modal');
        if (event.target == modal) {
            olamaCloseSubjectModal();
        }
    }
</script>