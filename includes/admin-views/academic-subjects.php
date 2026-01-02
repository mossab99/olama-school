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

<div
    style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
    <form method="post" action="">
        <?php wp_nonce_field('olama_export_subjects'); ?>
        <input type="hidden" name="olama_export_subjects" value="true" />
        <button type="submit" class="button"><span class="dashicons dashicons-export" style="margin-top: 4px;"></span>
            <?php _e('Export Subjects (CSV)', 'olama-school'); ?></button>
    </form>

    <div style="border-left: 1px solid #ddd; height: 30px; margin: 0 10px;"></div>

    <form method="post" action="" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
        <?php wp_nonce_field('olama_import_subjects'); ?>
        <input type="hidden" name="olama_import_type" value="subjects" />
        <input type="file" name="olama_import_file" accept=".csv" required />
        <button type="submit" class="button button-primary"><span class="dashicons dashicons-import"
                style="margin-top: 4px;"></span> <?php _e('Import Subjects', 'olama-school'); ?></button>
    </form>
</div>
<div class="olama-flex" style="display: flex; gap: 20px;">
    <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
        <?php if ($edit_subject): ?>
            <h2><?php _e('Edit Subject', 'olama-school'); ?></h2>
            <form method="post" action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects'); ?>">
                <?php wp_nonce_field('olama_edit_subject'); ?>
                <input type="hidden" name="subject_id" value="<?php echo $edit_subject->id; ?>" />
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Subject Name', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_name" required class="regular-text"
                                value="<?php echo esc_attr($edit_subject->subject_name); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Subject Code', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_code" placeholder="e.g. ENG01"
                                value="<?php echo esc_attr($edit_subject->subject_code); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                        <td>
                            <select name="grade_id" required>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade->id; ?>" <?php selected($edit_subject->grade_id, $grade->id); ?>>
                                        <?php echo esc_html($grade->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Color Code', 'olama-school'); ?></th>
                        <td><input type="color" name="color_code"
                                value="<?php echo esc_attr($edit_subject->color_code); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Update Subject', 'olama-school'), 'primary', 'edit_subject', false); ?>
                <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects'); ?>"
                    class="button"><?php _e('Cancel', 'olama-school'); ?></a>
            </form>
        <?php else: ?>
            <h2><?php _e('Add Subject', 'olama-school'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('olama_add_subject'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Subject Name', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_name" required class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Subject Code', 'olama-school'); ?></th>
                        <td><input type="text" name="subject_code" placeholder="e.g. ENG01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                        <td>
                            <select name="grade_id" required>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade->id; ?>"><?php echo esc_html($grade->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Color Code', 'olama-school'); ?></th>
                        <td><input type="color" name="color_code" value="#3498db" /></td>
                    </tr>
                </table>
                <?php submit_button(__('Add Subject', 'olama-school'), 'primary', 'add_subject'); ?>
            </form>
        <?php endif; ?>
    </div>

    <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
        <h2><?php _e('Existing Subjects', 'olama-school'); ?></h2>
        <?php if ($grouped_subjects): ?>
            <?php foreach ($grouped_subjects as $grade_name => $grade_subjects): ?>
                <h3 style="background: #f8f9fa; padding: 8px 12px; border-left: 4px solid #2271b1; margin-top: 20px;">
                    <?php echo esc_html($grade_name); ?>
                </h3>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th><?php _e('Subject Name', 'olama-school'); ?></th>
                            <th style="width: 80px;"><?php _e('Code', 'olama-school'); ?></th>
                            <th style="width: 60px; text-align: center;"><?php _e('Color', 'olama-school'); ?></th>
                            <th style="width: 140px;"><?php _e('Actions', 'olama-school'); ?></th>
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
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects&action=edit_subject&subject_id=' . $subject->id); ?>"
                                        class="button button-small"><?php _e('Edit', 'olama-school'); ?></a>
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
            <p><?php _e('No subjects found. Add your first subject using the form on the left.', 'olama-school'); ?></p>
        <?php endif; ?>
    </div>
</div>
