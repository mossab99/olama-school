<?php
/**
 * Oracle-backed Academic Management - Grades & Sections.
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$study_year = Olama_School_Academic_Bridge::get_study_year();
$core_available = Olama_School_Academic_Bridge::is_available();
$selected_grade_id = isset($_GET['manage_grade']) ? absint($_GET['manage_grade']) : 0;
$selected_grade = $selected_grade_id ? Olama_School_Grade::get_grade($selected_grade_id) : null;
$last_synced_at = '';
foreach ($grades as $grade) {
    if (!empty($grade->oracle_last_synced_at) && $grade->oracle_last_synced_at > $last_synced_at) {
        $last_synced_at = $grade->oracle_last_synced_at;
    }
}
?>

<?php if (!$core_available): ?>
    <div class="notice notice-error inline">
        <p><?php esc_html_e('Olama Core academic tables are not available. Activate Olama Core and run Oracle Sync.', 'olama-school'); ?></p>
    </div>
<?php else: ?>
    <div class="olama-card" style="background:#fff; padding:16px 20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:20px;">
        <div>
            <strong><?php esc_html_e('Source: Olama Core (Oracle)', 'olama-school'); ?></strong>
            <p style="margin:6px 0 0; color:#646970;">
                <?php
                printf(
                    esc_html__('Study year: %1$s · Last synchronized: %2$s', 'olama-school'),
                    esc_html($study_year ?: __('Not available', 'olama-school')),
                    esc_html($last_synced_at ?: __('Not available', 'olama-school'))
                );
                ?>
            </p>
        </div>
        <?php if (current_user_can('manage_options')): ?>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=olama-oracle-sync-manual')); ?>">
                <?php esc_html_e('Open Oracle Sync', 'olama-school'); ?>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="olama-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px;">
    <h2><?php esc_html_e('Oracle Grades', 'olama-school'); ?></h2>
    <p style="color:#646970;">
        <?php esc_html_e('Grade names, levels, and section membership are read from the latest Oracle data synchronized into Olama Core. Periods are managed here and remain unchanged when Oracle data is synchronized.', 'olama-school'); ?>
    </p>
    <form method="post">
        <?php wp_nonce_field('olama_save_grade_periods', 'olama_grade_periods_nonce'); ?>
        <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:90px;"><?php esc_html_e('School ID', 'olama-school'); ?></th>
                <th style="width:90px;"><?php esc_html_e('Oracle ID', 'olama-school'); ?></th>
                <th><?php esc_html_e('Grade Name', 'olama-school'); ?></th>
                <th style="width:90px;"><?php esc_html_e('Level', 'olama-school'); ?></th>
                <th style="width:90px;"><?php esc_html_e('Periods', 'olama-school'); ?></th>
                <th style="width:160px;"><?php esc_html_e('Sections', 'olama-school'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($grades): ?>
                <?php foreach ($grades as $grade): ?>
                    <tr<?php echo $selected_grade_id === (int) $grade->id ? ' style="background-color:#f0f7ff;"' : ''; ?>>
                        <td><?php echo esc_html($grade->id); ?></td>
                        <td><code><?php echo esc_html($grade->oracle_grade_id ?? $grade->core_grade_id); ?></code></td>
                        <td><strong><?php echo esc_html($grade->grade_name); ?></strong></td>
                        <td><?php echo esc_html($grade->grade_level); ?></td>
                        <td>
                            <label class="screen-reader-text" for="grade-periods-<?php echo esc_attr($grade->id); ?>">
                                <?php echo esc_html(sprintf(__('Periods for %s', 'olama-school'), $grade->grade_name)); ?>
                            </label>
                            <input
                                id="grade-periods-<?php echo esc_attr($grade->id); ?>"
                                name="grade_periods[<?php echo esc_attr($grade->id); ?>]"
                                type="number"
                                min="1"
                                max="127"
                                step="1"
                                value="<?php echo esc_attr($grade->periods_count ?? 8); ?>"
                                class="small-text"
                                required
                            >
                        </td>
                        <td>
                            <a class="button button-small button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'olama-school-academic', 'tab' => 'grades', 'manage_grade' => $grade->id), admin_url('admin.php'))); ?>">
                                <?php esc_html_e('View Sections', 'olama-school'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6"><?php esc_html_e('No synchronized Oracle grades were found.', 'olama-school'); ?></td></tr>
            <?php endif; ?>
        </tbody>
        </table>
        <?php if ($grades): ?>
            <p class="submit" style="margin-bottom:0; padding-bottom:0;">
                <button type="submit" name="save_grade_periods" class="button button-primary">
                    <?php esc_html_e('Save Periods', 'olama-school'); ?>
                </button>
            </p>
        <?php endif; ?>
    </form>
</div>

<?php if ($selected_grade): ?>
    <?php $grade_sections = Olama_School_Section::get_by_grade($selected_grade_id); ?>
    <div class="olama-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4;">
        <h2><?php echo esc_html(sprintf(__('Oracle Sections for %s', 'olama-school'), $selected_grade->grade_name)); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:90px;"><?php esc_html_e('School ID', 'olama-school'); ?></th>
                    <th style="width:90px;"><?php esc_html_e('Oracle ID', 'olama-school'); ?></th>
                    <th><?php esc_html_e('Section Name', 'olama-school'); ?></th>
                    <th><?php esc_html_e('Room', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($grade_sections): ?>
                    <?php foreach ($grade_sections as $section): ?>
                        <tr>
                            <td><?php echo esc_html($section->id); ?></td>
                            <td><code><?php echo esc_html($section->oracle_section_id ?? $section->core_section_id); ?></code></td>
                            <td><strong><?php echo esc_html($section->section_name); ?></strong></td>
                            <td><?php echo esc_html($section->room_number ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4"><?php esc_html_e('No synchronized Oracle sections were found for this grade and study year.', 'olama-school'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
