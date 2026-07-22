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
        <?php esc_html_e('Subject names, Oracle IDs, grade membership, and active status come from Olama Core. School IDs, colors, planning codes, limits, and existing teacher assignments remain stable.', 'olama-school'); ?>
    </p>

    <?php if ($grouped_subjects): ?>
        <?php foreach ($grouped_subjects as $grade_group): ?>
            <h3 style="background:#f8f9fa; padding:12px 18px; border-left:4px solid #2271b1; margin-top:20px; font-size:1.1rem; border-radius:4px;">
                <?php echo esc_html($grade_group['grade_name']); ?>
            </h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:30px;">
                <thead>
                    <tr>
                        <th style="width:90px;"><?php esc_html_e('School ID', 'olama-school'); ?></th>
                        <th style="width:90px;"><?php esc_html_e('Oracle ID', 'olama-school'); ?></th>
                        <th><?php esc_html_e('Subject Name', 'olama-school'); ?></th>
                        <th style="width:140px;"><?php esc_html_e('Planning Code', 'olama-school'); ?></th>
                        <th style="width:80px; text-align:center;"><?php esc_html_e('Color', 'olama-school'); ?></th>
                        <th style="width:100px; text-align:center;"><?php esc_html_e('Status', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grade_group['subjects'] as $subject): ?>
                        <tr>
                            <td><?php echo esc_html($subject->id); ?></td>
                            <td><code><?php echo esc_html($subject->oracle_subject_id ?? $subject->core_subject_id); ?></code></td>
                            <td><strong><?php echo esc_html($subject->subject_name); ?></strong></td>
                            <td><code><?php echo esc_html($subject->subject_code ?: '—'); ?></code></td>
                            <td style="text-align:center;">
                                <span aria-label="<?php echo esc_attr($subject->color_code); ?>" style="display:inline-block; width:24px; height:24px; background:<?php echo esc_attr($subject->color_code ?: '#2271b1'); ?>; border:1px solid #ccc; border-radius:4px;"></span>
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
        <?php endforeach; ?>
    <?php else: ?>
        <p style="padding:20px; text-align:center; color:#666; font-style:italic;">
            <?php esc_html_e('No synchronized Oracle subjects were found for this study year.', 'olama-school'); ?>
        </p>
    <?php endif; ?>
</div>
