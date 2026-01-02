<?php
/**
 * Dashboard View
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Check cache first
$cache_key = 'olama_dashboard_stats';
$cached_data = get_transient($cache_key);

if ($cached_data !== false) {
    extract($cached_data);
} else {
    // Stats
    $count_grades = Olama_School_Grade::get_grades() ? count(Olama_School_Grade::get_grades()) : 0;
    $count_sections = Olama_School_Section::get_sections() ? count(Olama_School_Section::get_sections()) : 0;
    $count_teachers = count(Olama_School_Teacher::get_teachers());
    $count_students = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_students");

    // Plan stats
    $plan_stats = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$wpdb->prefix}olama_plans GROUP BY status");
    $stats_by_status = array('draft' => 0, 'submitted' => 0, 'approved' => 0);
    foreach ($plan_stats as $s) {
        $stats_by_status[$s->status] = $s->count;
    }

    // Recent Plans
    $recent_plans = $wpdb->get_results("
        SELECT p.*, s.subject_name, sec.section_name, g.grade_name 
        FROM {$wpdb->prefix}olama_plans p
        JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
        JOIN {$wpdb->prefix}olama_sections sec ON p.section_id = sec.id
        JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
        ORDER BY p.created_at DESC LIMIT 5
    ");

    // Cache for 5 minutes
    set_transient($cache_key, compact('count_grades', 'count_sections', 'count_teachers', 'count_students', 'stats_by_status', 'recent_plans'), 5 * MINUTE_IN_SECONDS);
}
?>
<div class="wrap olama-school-wrap">
    <h1><?php _e('School Dashboard', 'olama-school'); ?></h1>

    <div class="olama-stats-grid"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; margin-top: 20px;">
        <div class="olama-stat-card"
            style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
            <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_grades; ?></div>
            <div style="color: #666; font-weight: 600;"><?php _e('Grades', 'olama-school'); ?></div>
        </div>
        <div class="olama-stat-card"
            style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
            <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_sections; ?>
            </div>
            <div style="color: #666; font-weight: 600;"><?php _e('Sections', 'olama-school'); ?></div>
        </div>
        <div class="olama-stat-card"
            style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
            <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_teachers; ?>
            </div>
            <div style="color: #666; font-weight: 600;"><?php _e('Teachers', 'olama-school'); ?></div>
        </div>
        <div class="olama-stat-card"
            style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
            <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_students; ?>
            </div>
            <div style="color: #666; font-weight: 600;"><?php _e('Students', 'olama-school'); ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        <!-- Plan Overview -->
        <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <?php _e('Plans Overview', 'olama-school'); ?>
            </h2>
            <div style="margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span><?php _e('Drafts', 'olama-school'); ?></span>
                    <span style="font-weight: 700;"><?php echo $stats_by_status['draft']; ?></span>
                </div>
                <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                    <div
                        style="width: <?php echo $stats_by_status['draft'] > 0 ? 100 : 0; ?>%; height: 100%; background: #ccc;">
                    </div>
                </div>
            </div>
            <div style="margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span><?php _e('Submitted', 'olama-school'); ?></span>
                    <span style="font-weight: 700; color: #dba617;"><?php echo $stats_by_status['submitted']; ?></span>
                </div>
                <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                    <div
                        style="width: <?php echo $stats_by_status['submitted'] > 0 ? 100 : 0; ?>%; height: 100%; background: #dba617;">
                    </div>
                </div>
            </div>
            <div style="margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span><?php _e('Approved', 'olama-school'); ?></span>
                    <span style="font-weight: 700; color: #00a32a;"><?php echo $stats_by_status['approved']; ?></span>
                </div>
                <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                    <div
                        style="width: <?php echo $stats_by_status['approved'] > 0 ? 100 : 0; ?>%; height: 100%; background: #00a32a;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <?php _e('Recent Plans', 'olama-school'); ?>
            </h2>
            <table class="wp-list-table widefat fixed striped" style="box-shadow: none; border: none;">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'olama-school'); ?></th>
                        <th><?php _e('Details', 'olama-school'); ?></th>
                        <th><?php _e('Status', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_plans): ?>
                        <?php foreach ($recent_plans as $rp): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($rp->plan_date)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($rp->subject_name); ?></strong><br>
                                    <span
                                        style="font-size: 0.85em; color: #666;"><?php echo esc_html($rp->grade_name . ' - ' . $rp->section_name); ?></span>
                                </td>
                                <td>
                                    <span
                                        style="padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 600; background: <?php echo ($rp->status == 'approved' ? '#e7ffef' : ($rp->status == 'submitted' ? '#fff9e7' : '#f0f0f1')); ?>; color: <?php echo ($rp->status == 'approved' ? '#2271b1' : ($rp->status == 'submitted' ? '#996800' : '#50575e')); ?>;">
                                        <?php echo ucfirst($rp->status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #999;">
                                <?php _e('No recent plans found.', 'olama-school'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>