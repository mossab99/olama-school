<?php
/**
 * Academic Supervision - Analytics View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;
$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;

// 1. Visit Stats
$total_visits = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     WHERE sec.academic_year_id = %d",
    $active_year_id
));

$completed_visits = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     WHERE sec.academic_year_id = %d AND v.status = 'completed'",
    $active_year_id
));

$avg_score = $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(final_score) FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     WHERE sec.academic_year_id = %d AND v.status = 'completed'",
    $active_year_id
));

// 2. Score by Grade
$grade_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT g.grade_name, AVG(v.final_score) as avg_score, COUNT(v.id) as visit_count
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
     WHERE sec.academic_year_id = %d AND v.status = 'completed'
     GROUP BY g.id",
    $active_year_id
));

?>

<div class="olama-supervision-analytics-wrap">
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="olama-stat-card"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6;">
            <div
                style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">
                <?php _e('Total Scheduled Visits', 'olama-school'); ?>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo intval($total_visits); ?>
            </div>
        </div>

        <div class="olama-stat-card"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid #10b981;">
            <div
                style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">
                <?php _e('Completed Visits', 'olama-school'); ?>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo intval($completed_visits); ?>
            </div>
        </div>

        <div class="olama-stat-card"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid #8b5cf6;">
            <div
                style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">
                <?php _e('Average Score', 'olama-school'); ?>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo number_format((float) ($avg_score ?? 0), 1); ?>%
            </div>
        </div>
    </div>

    <div class="olama-card"
        style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <h3>
            <?php _e('Performance by Grade', 'olama-school'); ?>
        </h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <?php _e('Grade', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Visits', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Average Score', 'olama-school'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($grade_stats): ?>
                    <?php foreach ($grade_stats as $stat): ?>
                        <tr>
                            <td>
                                <?php echo esc_html($stat->grade_name); ?>
                            </td>
                            <td>
                                <?php echo intval($stat->visit_count); ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div
                                            style="height: 100%; background: #3b82f6; width: <?php echo $stat->avg_score; ?>%;">
                                        </div>
                                    </div>
                                    <span style="font-weight: 600;">
                                        <?php echo number_format($stat->avg_score, 1); ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #64748b; font-style: italic; padding: 20px;">
                            <?php _e('No data available.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>