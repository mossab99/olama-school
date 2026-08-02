<?php
/**
 * Oracle-backed Academic Management - Subjects.
 */
if (!defined('ABSPATH')) {
    exit;
}

$subjects = Olama_School_Subject::get_subjects();
$study_year = Olama_School_Academic_Bridge::get_study_year();
$core_available = Olama_School_Academic_Bridge::is_available();
$last_synced_at = '';
$grouped_subjects = array();

foreach ($subjects as $subject) {
    $grade_key = (string) ($subject->oracle_grade_id ?? $subject->grade_id);
    if (!isset($grouped_subjects[$grade_key])) {
        $grouped_subjects[$grade_key] = array(
            'grade_name' => $subject->grade_name,
            'subjects' => array(),
        );
    }
    $grouped_subjects[$grade_key]['subjects'][] = $subject;
    if (!empty($subject->oracle_last_synced_at) && $subject->oracle_last_synced_at > $last_synced_at) {
        $last_synced_at = $subject->oracle_last_synced_at;
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
                    esc_html__('Study year: %1$s · Last synchronized: %2$s · Subjects: %3$d', 'olama-school'),
                    esc_html($study_year ?: __('Not available', 'olama-school')),
                    esc_html($last_synced_at ?: __('Not available', 'olama-school')),
                    count($subjects)
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

<div class="olama-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4;">
    <h2><?php esc_html_e('Oracle Subjects', 'olama-school'); ?></h2>
    <p style="color:#646970;">
        <?php esc_html_e('Subject names, Oracle IDs, grade membership, and active status come from Olama Core. Colors, visibility, and stationery requirements are managed here and remain stable after synchronization.', 'olama-school'); ?>
    </p>

    <?php if ($grouped_subjects): ?>
        <form method="post">
            <?php wp_nonce_field('olama_subject_display_settings', 'olama_subject_display_nonce'); ?>
        <?php foreach ($grouped_subjects as $grade_group): ?>
            <h3 style="background:#f8f9fa; padding:12px 18px; border-left:4px solid #2271b1; margin-top:20px; font-size:1.1rem; border-radius:4px;">
                <?php echo esc_html($grade_group['grade_name']); ?>
            </h3>
            <div class="olama-subjects-table-wrap">
            <table class="wp-list-table widefat striped olama-subjects-table" style="margin-bottom:30px;">
                <thead>
                    <tr>
                        <th style="width:90px;"><?php esc_html_e('School ID', 'olama-school'); ?></th>
                        <th style="width:90px;"><?php esc_html_e('Oracle ID', 'olama-school'); ?></th>
                        <th style="min-width:180px;"><?php esc_html_e('Subject Name', 'olama-school'); ?></th>
                        <th style="width:140px;"><?php esc_html_e('Planning Code', 'olama-school'); ?></th>
                        <th style="width:100px; text-align:center;"><?php esc_html_e('Color', 'olama-school'); ?></th>
                        <th style="width:160px; text-align:center;"><?php esc_html_e('Appear in Weekly Plan', 'olama-school'); ?></th>
                        <th style="width:150px; text-align:center;"><?php esc_html_e('Appear in Schedule', 'olama-school'); ?></th>
                        <th style="width:150px; text-align:center;"><?php esc_html_e('Stationery Required', 'olama-school'); ?></th>
                        <th style="width:100px; text-align:center;"><?php esc_html_e('Status', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grade_group['subjects'] as $subject): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="subject_ids[]" value="<?php echo esc_attr($subject->id); ?>" />
                                <?php echo esc_html($subject->id); ?>
                            </td>
                            <td><code><?php echo esc_html($subject->oracle_subject_id ?? $subject->core_subject_id); ?></code></td>
                            <td><strong><?php echo esc_html($subject->subject_name); ?></strong></td>
                            <td><code><?php echo esc_html($subject->subject_code ?: '—'); ?></code></td>
                            <td style="text-align:center;">
                                <input type="color" name="subject_color[<?php echo esc_attr($subject->id); ?>]"
                                    value="<?php echo esc_attr($subject->color_code ?: '#2271b1'); ?>"
                                    aria-label="<?php echo esc_attr(sprintf(__('Color for %s', 'olama-school'), $subject->subject_name)); ?>"
                                    style="width:42px; height:32px; padding:2px; cursor:pointer;" />
                            </td>
                            <td style="text-align:center;">
                                <label>
                                    <input type="checkbox" name="appear_in_weekly_plan[<?php echo esc_attr($subject->id); ?>]" value="1"
                                        <?php checked(!isset($subject->appear_in_weekly_plan) || (int) $subject->appear_in_weekly_plan === 1); ?> />
                                    <?php esc_html_e('Yes', 'olama-school'); ?>
                                </label>
                            </td>
                            <td style="text-align:center;">
                                <label>
                                    <input type="checkbox" name="appear_in_schedule[<?php echo esc_attr($subject->id); ?>]" value="1"
                                        <?php checked(!isset($subject->appear_in_schedule) || (int) $subject->appear_in_schedule === 1); ?> />
                                    <?php esc_html_e('Yes', 'olama-school'); ?>
                                </label>
                            </td>
                            <td style="text-align:center;">
                                <label>
                                    <input type="checkbox" name="requires_stationary[<?php echo esc_attr($subject->id); ?>]" value="1"
                                        <?php checked(isset($subject->requires_stationary) && (int) $subject->requires_stationary === 1); ?> />
                                    <?php esc_html_e('Yes', 'olama-school'); ?>
                                </label>
                            </td>
                            <td style="text-align:center;">
                                <?php if (!empty($subject->is_active)): ?>
                                    <span class="olama-status-pill olama-status-published" style="font-size:.7rem;"><?php esc_html_e('Active', 'olama-school'); ?></span>
                                <?php else: ?>
                                    <span class="olama-status-pill olama-status-draft" style="font-size:.7rem;"><?php esc_html_e('Inactive', 'olama-school'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endforeach; ?>
            <p style="position:sticky; bottom:12px; text-align:right; margin:0; padding:12px; background:rgba(255,255,255,.95); border-top:1px solid #dcdcde;">
                <button type="submit" name="olama_save_subject_display_settings" value="1" class="button button-primary button-large">
                    <?php esc_html_e('Save Subject Display Settings', 'olama-school'); ?>
                </button>
            </p>
        </form>
    <?php else: ?>
        <p style="padding:20px; text-align:center; color:#666; font-style:italic;">
            <?php esc_html_e('No synchronized Oracle subjects were found for this study year.', 'olama-school'); ?>
        </p>
    <?php endif; ?>
</div>

<style>
    .olama-subjects-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .olama-subjects-table {
        min-width: 1220px;
        table-layout: auto;
    }

    .olama-subjects-table th,
    .olama-subjects-table td {
        vertical-align: middle;
    }

    .olama-subjects-table th:nth-child(3),
    .olama-subjects-table td:nth-child(3) {
        min-width: 180px;
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
    }
</style>
