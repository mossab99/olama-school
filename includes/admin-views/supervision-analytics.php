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

// 3. Performance by Supervisor
$supervisor_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT u.display_name, 
            COUNT(v.id) as total_planned,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as total_completed,
            AVG(CASE WHEN v.status = 'completed' THEN v.final_score ELSE NULL END) as avg_score
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
     WHERE sec.academic_year_id = %d
     GROUP BY u.ID
     ORDER BY total_planned DESC",
    $active_year_id
));

// 4. Performance by Teacher
$teacher_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT tu.display_name, 
            COUNT(v.id) as total_planned,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as total_completed,
            AVG(CASE WHEN v.status = 'completed' THEN v.final_score ELSE NULL END) as avg_score
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     JOIN {$wpdb->prefix}olama_teacher_assignments ta ON ta.section_id = sec.id AND ta.subject_id = s.subject_id AND ta.academic_year_id = sec.academic_year_id
     JOIN {$wpdb->users} tu ON ta.teacher_id = tu.ID
     WHERE sec.academic_year_id = %d
     GROUP BY tu.ID
     ORDER BY avg_score DESC",
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
                <p class="stat-label"><?php echo Olama_School_Helpers::translate('Total Scheduled Visits'); ?></p>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo intval($total_visits); ?>
            </div>
        </div>

        <div class="olama-stat-card"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid #10b981;">
            <div
                style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">
                <p class="stat-label"><?php echo Olama_School_Helpers::translate('Completed Visits'); ?></p>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo intval($completed_visits); ?>
            </div>
        </div>

        <div class="olama-stat-card"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid #8b5cf6;">
            <div
                style="color: #64748b; font-size: 14px; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">
                <?php echo Olama_School_Helpers::translate('Average Score'); ?>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: #1e293b;">
                <?php echo number_format((float) ($avg_score ?? 0), 1); ?>%
            </div>
        </div>
    </div>

    <div class="olama-card"
        style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <h3>
            <?php echo Olama_School_Helpers::translate('Performance by Grade'); ?>
        </h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <?php echo Olama_School_Helpers::translate('Grade'); ?>
                    </th>
                    <th>
                        <?php echo Olama_School_Helpers::translate('Visits'); ?>
                    </th>
                    <th>
                        <?php echo Olama_School_Helpers::translate('Average Score'); ?>
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
                            <?php echo Olama_School_Helpers::translate('No data available.'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Performance by Supervisor Section -->
    <div class="olama-card"
        style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-top: 30px;">
        <h3>
                    </th>
                    <th>
                        <?php _e('Total Planned Visits', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Total Completed Visits', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Average Score', 'olama-school'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($supervisor_stats): ?>
                    <?php foreach ($supervisor_stats as $stat): 
                        $score = $stat->avg_score ? number_format($stat->avg_score, 1) : 0;
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #334155;">
                                <?php echo esc_html($stat->display_name); ?>
                            </td>
                            <td>
                                <span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    <?php echo intval($stat->total_planned); ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    <?php echo intval($stat->total_completed); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div
                                            style="height: 100%; background: #3b82f6; width: <?php echo $score; ?>%;">
                                        </div>
                                    </div>
                                    <span style="font-weight: 600;">
                                        <?php echo $score; ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b; font-style: italic; padding: 20px;">
                            <?php _e('No data available.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Performance by Teacher Section -->
    <div class="olama-card"
        style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-top: 30px; margin-bottom: 30px;">
        <h3><?php echo Olama_School_Helpers::translate('Performance by Teacher'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 30%;"><?php echo Olama_School_Helpers::translate('Teacher'); ?></th>
                    <th style="width: 20%; text-align: center;"><?php echo Olama_School_Helpers::translate('Visits'); ?></th>
                    <th style="width: 20%; text-align: center;"><?php echo Olama_School_Helpers::translate('Completed'); ?></th>
                    <th style="width: 30%;"><?php echo Olama_School_Helpers::translate('Average Score'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($teacher_stats): ?>
                    <?php foreach ($teacher_stats as $stat): 
                        $score = $stat->avg_score ? number_format($stat->avg_score, 1) : 0;
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #334155;">
                                <?php echo esc_html($stat->display_name); ?>
                            </td>
                            <td>
                                <span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    <?php echo intval($stat->total_planned); ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;">
                                    <?php echo intval($stat->total_completed); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div
                                            style="height: 100%; background: #0ea5e9; width: <?php echo $score; ?>%;">
                                        </div>
                                    </div>
                                    <span style="font-weight: 600;">
                                        <?php echo $score; ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b; font-style: italic; padding: 20px;">
                            <?php _e('No teacher data available.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>