<?php
/**
 * Dashboard View
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$user_id = get_current_user_id();
$is_teacher = current_user_can('olama_create_plans');
$is_supervisor = current_user_can('olama_manage_plans');
$is_admin = current_user_can('olama_manage_settings');

// Fetch Extended Stats (Global for Admin/Supervisor)
$extended_stats = Olama_School_Admin::get_dashboard_extended_stats();
$system_alerts = Olama_School_Admin::get_system_alerts();

// Legacy compatibility / basic stats
$count_grades = Olama_School_Grade::get_grades() ? count(Olama_School_Grade::get_grades()) : 0;
$count_sections = Olama_School_Section::get_sections() ? count(Olama_School_Section::get_sections()) : 0;
$count_teachers = count(Olama_School_Teacher::get_teachers());

// Global Plan stats
$plan_stats = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$wpdb->prefix}olama_plans GROUP BY status");
$stats_by_status = array('draft' => 0, 'submitted' => 0, 'approved' => 0);
foreach ($plan_stats as $s) {
    if (isset($stats_by_status[$s->status])) {
        $stats_by_status[$s->status] = $s->count;
    }
}

// Teacher Specific Data
if ($is_teacher) {
    $teacher_schedule = Olama_School_Admin::get_teacher_daily_schedule($user_id);
    $teacher_stats = Olama_School_Admin::get_teacher_personal_stats($user_id);
    $teacher_progress = Olama_School_Admin::get_teacher_subjects_progress($user_id);
}

// Recent Activity
$recent_activity = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}olama_logs 
    ORDER BY created_at DESC LIMIT 8
");

// Recent Plans
$recent_plans = $wpdb->get_results("
    SELECT p.*, s.subject_name, sec.section_name, g.grade_name 
    FROM {$wpdb->prefix}olama_plans p
    JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
    JOIN {$wpdb->prefix}olama_sections sec ON p.section_id = sec.id
    JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
    ORDER BY p.created_at DESC LIMIT 5
");

// Pending Reviews (Phase 2.2)
$pending_plans = Olama_School_Admin::get_pending_plans_for_review();
$coverage_data = Olama_School_Admin::get_weekly_coverage_data();
?>
<div class="wrap olama-school-wrap">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin: 0;"><?php _e('Command Center', 'olama-school'); ?></h1>
        <div style="display: flex; align-items: center; gap: 15px;">
            <!-- Notification Bell (Phase 3) -->
            <div class="olama-notification-wrapper" style="position: relative; cursor: pointer;">
                <span class="dashicons dashicons-bell" style="font-size: 24px; width: 24px; height: 24px; color: #666;"></span>
                <?php 
                $unread_count = count(Olama_School_Admin::get_user_notifications($user_id, true));
                if ($unread_count > 0): 
                ?>
                    <span class="olama-notif-badge" style="position: absolute; top: -5px; right: -5px; background: #d63638; color: #fff; font-size: 10px; padding: 2px 5px; border-radius: 10px; font-weight: 700;">
                        <?php echo $unread_count; ?>
                    </span>
                <?php endif; ?>
                
                <!-- Notification Dropdown -->
                <div class="olama-notif-dropdown" style="display: none; position: absolute; top: 35px; right: 0; width: 300px; background: #fff; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; border: 1px solid #eee;">
                    <div style="padding: 12px 15px; border-bottom: 1px solid #eee; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                        <span><?php _e('Notifications', 'olama-school'); ?></span>
                        <a href="#" class="olama-clear-all" style="font-size: 0.8em; color: #2271b1; text-decoration: none;"><?php _e('Clear All', 'olama-school'); ?></a>
                    </div>
                    <div class="olama-notif-list" style="max-height: 350px; overflow-y: auto;">
                        <!-- JS populated -->
                        <div style="padding: 20px; text-align: center; color: #999;"><?php _e('No new notifications', 'olama-school'); ?></div>
                    </div>
                </div>
            </div>

            <div style="background: #fff; padding: 5px 15px; border-radius: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-weight: 600; color: #2271b1;">
                <?php 
                $active_year = Olama_School_Academic::get_active_year();
                echo $active_year ? esc_html($active_year->year_name) : __('No Active Year', 'olama-school');
                ?>
            </div>
        </div>
    </div>

    <!-- Teacher Personal KPI Row (Phase 2.3) -->
    <?php if ($is_teacher): ?>
        <div style="margin-bottom: 30px;">
            <h3 style="margin-top: 0; color: #666; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <?php _e('My Teaching Performance', 'olama-school'); ?>
            </h3>
            <div class="olama-stats-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                <div class="olama-kpi-card"
                    style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #2271b1;">
                    <div style="color: #666; font-size: 0.85em; font-weight: 600; margin-bottom: 5px;">
                        <?php _e('My Total Plans', 'olama-school'); ?></div>
                    <div style="font-size: 1.8em; font-weight: 800; color: #1d2327;"><?php echo $teacher_stats['total']; ?></div>
                </div>
                <div class="olama-kpi-card"
                    style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #00a32a;">
                    <div style="color: #666; font-size: 0.85em; font-weight: 600; margin-bottom: 5px;">
                        <?php _e('My Approved Plans', 'olama-school'); ?></div>
                    <div style="font-size: 1.8em; font-weight: 800; color: #1d2327;"><?php echo $teacher_stats['approved']; ?>
                    </div>
                </div>
                <div class="olama-kpi-card"
                    style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #dba617;">
                    <div style="color: #666; font-size: 0.85em; font-weight: 600; margin-bottom: 5px;">
                        <?php _e('My Pending Review', 'olama-school'); ?></div>
                    <div style="font-size: 1.8em; font-weight: 800; color: #1d2327;"><?php echo $teacher_stats['pending']; ?>
                    </div>
                </div>
                <div class="olama-kpi-card"
                    style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #999;">
                    <div style="color: #666; font-size: 0.85em; font-weight: 600; margin-bottom: 5px;">
                        <?php _e('My Drafts', 'olama-school'); ?></div>
                    <div style="font-size: 1.8em; font-weight: 800; color: #1d2327;"><?php echo $teacher_stats['draft']; ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Row (Admin/Supervisor) -->
    <?php if ($is_admin || $is_supervisor): ?>
    <div class="olama-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="olama-kpi-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #2271b1;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #666; font-size: 0.9em; font-weight: 600; margin-bottom: 5px;"><?php _e('Total Enrollment', 'olama-school'); ?></div>
                    <div style="font-size: 2em; font-weight: 800; color: #1d2327;"><?php echo $extended_stats['enrolled_students']; ?></div>
                </div>
                <div style="background: #f0f6fb; padding: 10px; border-radius: 10px; color: #2271b1;">
                    <span class="dashicons dashicons-groups" style="font-size: 32px; width: 32px; height: 32px;"></span>
                </div>
            </div>
            <div style="margin-top: 15px; font-size: 0.85em; color: #00a32a;">
                <span class="dashicons dashicons-arrow-up-alt2" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
                <?php echo $extended_stats['enrollment_pct']; ?>% <?php _e('of registry', 'olama-school'); ?>
            </div>
        </div>

        <div class="olama-kpi-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #dba617;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #666; font-size: 0.9em; font-weight: 600; margin-bottom: 5px;"><?php _e('Weekly Planning', 'olama-school'); ?></div>
                    <div style="font-size: 2em; font-weight: 800; color: #1d2327;"><?php echo $extended_stats['plan_compliance']; ?>%</div>
                </div>
                <div style="background: #fff9e7; padding: 10px; border-radius: 10px; color: #dba617;">
                    <span class="dashicons dashicons-welcome-write-blog" style="font-size: 32px; width: 32px; height: 32px;"></span>
                </div>
            </div>
            <div style="margin-top: 15px; font-size: 0.85em; color: #666;">
                <?php _e('Coverage for current week', 'olama-school'); ?>
            </div>
        </div>

        <div class="olama-kpi-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #72aee6;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #666; font-size: 0.9em; font-weight: 600; margin-bottom: 5px;"><?php _e('Active Teachers', 'olama-school'); ?></div>
                    <div style="font-size: 2em; font-weight: 800; color: #1d2327;"><?php echo $count_teachers; ?></div>
                </div>
                <div style="background: #f6f7f7; padding: 10px; border-radius: 10px; color: #50575e;">
                    <span class="dashicons dashicons-businessman" style="font-size: 32px; width: 32px; height: 32px;"></span>
                </div>
            </div>
            <div style="margin-top: 15px; font-size: 0.85em; color: #666;">
                <?php echo $count_sections; ?> <?php _e('total sections', 'olama-school'); ?>
            </div>
        </div>

        <div class="olama-kpi-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #00a32a;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #666; font-size: 0.9em; font-weight: 600; margin-bottom: 5px;"><?php _e('Approved Plans', 'olama-school'); ?></div>
                    <div style="font-size: 2em; font-weight: 800; color: #1d2327;"><?php echo $stats_by_status['approved']; ?></div>
                </div>
                <div style="background: #e7ffef; padding: 10px; border-radius: 10px; color: #00a32a;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 32px; width: 32px; height: 32px;"></span>
                </div>
            </div>
            <div style="margin-top: 15px; font-size: 0.85em; color: #666;">
                <?php echo $stats_by_status['submitted']; ?> <?php _e('pending review', 'olama-school'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Supervisor Section -->
    <?php if ($is_supervisor): ?>
    <div style="margin-bottom: 30px;">
        <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-clipboard" style="color: #2271b1;"></span>
                <?php _e('Plan Review Queue', 'olama-school'); ?>
                <span style="background: #d63638; color: #fff; font-size: 0.6em; padding: 2px 8px; border-radius: 10px; vertical-align: middle;"><?php echo count($pending_plans); ?></span>
            </h2>
            
            <div style="margin-top: 20px;">
                <?php if ($pending_plans): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                        <?php foreach ($pending_plans as $p): ?>
                            <div class="olama-pending-card" id="plan-card-<?php echo $p->id; ?>" style="border: 1px solid #f0f0f1; border-radius: 8px; padding: 15px; position: relative; transition: all 0.3s ease;">
                                <div style="font-weight: 700; color: #2271b1; margin-bottom: 5px;"><?php echo esc_html($p->subject_name); ?></div>
                                <div style="font-size: 0.9em; color: #1d2327; margin-bottom: 10px;">
                                    <strong><?php echo esc_html($p->grade_name . ' - ' . $p->section_name); ?></strong>
                                    <div style="color: #666; font-size: 0.85em; margin-top: 2px;">
                                        <?php printf(__('Submitted by %s on %s', 'olama-school'), '<strong>'.esc_html($p->teacher_name).'</strong>', date('M j, Y', strtotime($p->created_at))); ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button class="button button-primary olama-approve-btn" data-id="<?php echo $p->id; ?>"><?php _e('Approve', 'olama-school'); ?></button>
                                    <button class="button olama-reject-btn" data-id="<?php echo $p->id; ?>"><?php _e('Request Edits', 'olama-school'); ?></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px; opacity: 0.3;"></span>
                        <p><?php _e('All weekly plans have been reviewed.', 'olama-school'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        
        <!-- Center Column -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            
            <!-- Today's Teaching Schedule (Teacher Dashboard - Phase 2.3) -->
            <?php if ($is_teacher): ?>
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-calendar-alt" style="color: #2271b1;"></span>
                    <?php _e('Today\'s Teaching Schedule', 'olama-school'); ?>
                    <span style="font-size: 0.6em; background: #f0f6fb; color: #2271b1; padding: 4px 10px; border-radius: 15px; font-weight: 700;">
                        <?php echo date('l, M j'); ?>
                    </span>
                </h2>
                <div style="margin-top: 20px;">
                    <?php if ($teacher_schedule): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($teacher_schedule as $period): ?>
                                <div style="display: flex; items-center; justify-content: space-between; padding: 15px; background: #f9f9f9; border-radius: 8px; border-right: 4px solid #2271b1;">
                                    <div style="display: flex; items-center; gap: 20px;">
                                        <div style="background: #2271b1; color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                                            <?php echo $period->period_number; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #1d2327;"><?php echo esc_html($period->subject_name); ?></div>
                                            <div style="font-size: 0.85em; color: #666;"><?php echo esc_html($period->grade_name . ' - ' . $period->section_name); ?></div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <?php if ($period->plan_status): ?>
                                            <span style="font-size: 0.85em; font-weight: 600; color: <?php echo ($period->plan_status == 'approved' ? '#00a32a' : '#dba617'); ?>;">
                                                <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                                <?php echo ucfirst($period->plan_status); ?>
                                            </span>
                                            <a href="admin.php?page=olama-school-plans&action=edit&id=<?php echo $period->plan_id; ?>" class="button button-small"><?php _e('View Plan', 'olama-school'); ?></a>
                                        <?php else: ?>
                                            <span style="font-size: 0.85em; color: #d63638; font-weight: 600;">
                                                <span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
                                                <?php _e('Missing Plan', 'olama-school'); ?>
                                            </span>
                                            <a href="admin.php?page=olama-school-plans&action=create&section_id=<?php echo $period->section_id; ?>&subject_id=<?php echo $period->subject_id; ?>&period=<?php echo $period->period_number; ?>&date=<?php echo date('Y-m-d'); ?>" class="button button-primary button-small"><?php _e('Create Now', 'olama-school'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: #999;">
                            <span class="dashicons dashicons-calendar" style="font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 10px;"></span>
                            <p><?php _e('No classes scheduled for today.', 'olama-school'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Weekly Coverage Heatmap -->
            <?php if ($is_supervisor): ?>
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-grid-view" style="color: #dba617;"></span>
                    <?php _e('School-wide Weekly Coverage', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 20px; overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped" style="box-shadow: none; border: 1px solid #f0f0f1;">
                        <thead>
                            <tr>
                                <th style="width: 150px;"><?php _e('Section', 'olama-school'); ?></th>
                                <th><?php _e('Coverage Status', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coverage_data as $sid => $data): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo esc_html($data['name']); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <?php if (empty($data['plans'])): ?>
                                                <span style="color: #d63638; font-size: 0.85em; font-style: italic;"><?php _e('No plans found', 'olama-school'); ?></span>
                                            <?php else: ?>
                                                <?php foreach ($data['plans'] as $sub_id => $status): ?>
                                                    <div style="width: 25px; height: 10px; border-radius: 2px; background: <?php echo ($status == 'approved' ? '#00a32a' : ($status == 'submitted' ? '#dba617' : '#ccc')); ?>;" title="<?php echo esc_attr(ucfirst($status)); ?>"></div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- System Health & Alerts -->
            <?php if ($is_admin || $is_supervisor): ?>
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-shield-alt" style="color: #d63638;"></span>
                    <?php _e('System Health & Alerts', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 20px;">
                    <?php if ($system_alerts): ?>
                        <?php foreach ($system_alerts as $alert): ?>
                            <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: <?php echo ($alert['type'] == 'error' ? '#fcf0f1' : ($alert['type'] == 'warning' ? '#fff9e7' : '#f0f6fb')); ?>; border-radius: 8px; margin-bottom: 12px; border-right: 4px solid <?php echo ($alert['type'] == 'error' ? '#d63638' : ($alert['type'] == 'warning' ? '#dba617' : '#2271b1')); ?>;">
                                <span class="dashicons <?php echo $alert['icon']; ?>" style="color: <?php echo ($alert['type'] == 'error' ? '#d63638' : ($alert['type'] == 'warning' ? '#dba617' : '#2271b1')); ?>;"></span>
                                <div style="flex-grow: 1; font-weight: 600; color: #1d2327;">
                                    <?php echo esc_html($alert['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: #00a32a; font-weight: 600;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 40px; width: 40px; height: 40px; display: block; margin: 0 auto 10px;"></span>
                            <?php _e('All systems operational.', 'olama-school'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity Feed -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1;">
                    <?php _e('Recent Academic Activity', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 20px;">
                    <?php if ($recent_activity): ?>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <?php foreach ($recent_activity as $log): ?>
                                <div style="padding: 15px 0; border-bottom: 1px solid #f6f7f7; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?php echo esc_html($log->action); ?></strong>
                                        <div style="font-size: 0.85em; color: #666; margin-top: 3px;"><?php echo esc_html($log->details); ?></div>
                                    </div>
                                    <div style="font-size: 0.8em; color: #999; font-style: italic;">
                                        <?php echo Olama_School_Helpers::time_ago($log->created_at); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #999; padding: 20px;"><?php _e('No recent activity recorded.', 'olama-school'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            
            <!-- My Subject Progress (Teacher Dashboard - Phase 2.3) -->
            <?php if ($is_teacher): ?>
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1;">
                    <?php _e('Curriculum Progress', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 20px;">
                    <?php if ($teacher_progress): ?>
                        <?php foreach ($teacher_progress as $prog): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: #2271b1;"><?php echo esc_html($prog->subject_name); ?></span>
                                    <span style="font-size: 0.85em; font-weight: 700;"><?php echo $prog->percentage; ?>%</span>
                                </div>
                                <div style="font-size: 0.8em; color: #666; margin-bottom: 10px;"><?php echo esc_html($prog->grade_name . ' - ' . $prog->section_name); ?></div>
                                <div style="height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?php echo $prog->percentage; ?>%; background: #2271b1; height: 100%;"></div>
                                </div>
                                <div style="font-size: 0.75em; color: #999; margin-top: 5px; text-align: right;">
                                    <?php printf(__('%d of %d lessons covered', 'olama-school'), $prog->covered_lessons, $prog->total_lessons); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #999;"><?php _e('No subject assignments found.', 'olama-school'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Global Planning Progress Bar -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1;">
                    <?php _e('School-wide Planning', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 20px;">
                    <?php 
                    $total_plans = array_sum($stats_by_status);
                    $approved_pct = $total_plans > 0 ? ($stats_by_status['approved'] / $total_plans) * 100 : 0;
                    $submitted_pct = $total_plans > 0 ? ($stats_by_status['submitted'] / $total_plans) * 100 : 0;
                    ?>
                    <div style="height: 18px; background: #f0f0f1; border-radius: 9px; overflow: hidden; display: flex; margin-bottom: 20px;">
                        <div style="width: <?php echo $approved_pct; ?>%; background: #00a32a; height: 100%;" title="Approved"></div>
                        <div style="width: <?php echo $submitted_pct; ?>%; background: #dba617; height: 100%;" title="Submitted"></div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9em;">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #00a32a;"></span>
                                <?php _e('Approved', 'olama-school'); ?>
                            </span>
                            <span style="font-weight: 700;"><?php echo $stats_by_status['approved']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9em;">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #dba617;"></span>
                                <?php _e('Submitted', 'olama-school'); ?>
                            </span>
                            <span style="font-weight: 700;"><?php echo $stats_by_status['submitted']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Plans Short List -->
            <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1;">
                    <?php _e('Latest Plans', 'olama-school'); ?>
                </h2>
                <div style="margin-top: 15px;">
                    <?php if ($recent_plans): ?>
                        <?php foreach ($recent_plans as $rp): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid #f6f7f7;">
                                <div style="font-weight: 600; color: #2271b1;"><?php echo esc_html($rp->subject_name); ?></div>
                                <div style="font-size: 0.85em; color: #666;">
                                    <?php echo esc_html($rp->grade_name . ' - ' . $rp->section_name); ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.8em;">
                                    <span style="color: #999;"><?php echo date('M j', strtotime($rp->plan_date)); ?></span>
                                    <span style="font-weight: 600; color: <?php echo ($rp->status == 'approved' ? '#00a32a' : ($rp->status == 'submitted' ? '#dba617' : '#999')); ?>;">
                                        <?php echo __(ucfirst($rp->status), 'olama-school'); ?>
                                    </span>
                                </div>
                                <?php if ($is_supervisor && $rp->status !== 'approved'): ?>
                                    <div style="display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end;">
                                        <button class="button button-small button-primary olama-approve-btn" data-id="<?php echo $rp->id; ?>" style="font-size: 10px; padding: 0 8px; height: 24px; line-height: 22px;">
                                            <span class="dashicons dashicons-yes" style="font-size: 14px; width: 14px; height: 14px; margin-top: 4px;"></span> <?php _e('Approve', 'olama-school'); ?>
                                        </button>
                                        <button class="button button-small olama-reject-btn" data-id="<?php echo $rp->id; ?>" style="font-size: 10px; padding: 0 8px; height: 24px; line-height: 22px;">
                                            <span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; margin-top: 4px;"></span> <?php _e('Request Edits', 'olama-school'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div style="margin-top: 15px; text-align: center;">
                            <a href="admin.php?page=olama-school-plans" class="button button-link"><?php _e('View All Plans', 'olama-school'); ?></a>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #999;"><?php _e('No plans found.', 'olama-school'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // --- Phase 3: Notifications ---
    var notifWrapper = $('.olama-notification-wrapper');
    var notifDropdown = $('.olama-notif-dropdown');
    var notifList = $('.olama-notif-list');

    notifWrapper.on('click', function(e) {
        e.stopPropagation();
        notifDropdown.toggle();
        if (notifDropdown.is(':visible')) {
            fetchNotifications();
        }
    });

    $(document).on('click', function() {
        notifDropdown.hide();
    });

    function fetchNotifications() {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'olama_get_notifications',
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    response.data.forEach(function(n) {
                        html += '<div class="olama-notif-item ' + (n.is_read == '0' ? 'unread' : '') + '" data-id="' + n.id + '">';
                        html += '<div style="font-size: 0.9em; line-height: 1.4; color: #1d2327;">' + n.message + '</div>';
                        html += '<div style="font-size: 0.75em; color: #999; margin-top: 5px;">' + n.time_ago + '</div>';
                        html += '</div>';
                    });
                    notifList.html(html);
                } else {
                    notifList.html('<div style="padding: 20px; text-align: center; color: #999;"><?php _e('No new notifications', 'olama-school'); ?></div>');
                }
            }
        });
    }

    // Mark single notification as read
    notifList.on('click', '.olama-notif-item', function(e) {
        var id = $(this).data('id');
        $(this).removeClass('unread');
        $.post(ajaxurl, {
            action: 'olama_mark_notification_read',
            id: id,
            nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
        });
    });

    $('.olama-clear-all').on('click', function(e) {
        e.preventDefault();
        $.post(ajaxurl, {
            action: 'olama_mark_notification_read',
            id: 0,
            nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
        }, function() {
            $('.olama-notif-badge').remove();
            fetchNotifications();
        });
    });

    // --- Phase 3: Feedback Modal ---
    var currentPlanId = null;
    $('.olama-approve-btn').on('click', function() {
        var $btn = $(this);
        var planId = $btn.data('id');
        var card = $('#plan-card-' + planId); // Main queue card

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Approving...', 'olama-school'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'olama_handle_plan_approval',
                plan_id: planId,
                status: 'published',
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    if (card.length > 0) {
                        card.fadeOut(500, function() { $(this).remove(); });
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(response.data.message || 'Error processing request');
                    $btn.prop('disabled', false).html('<?php _e('Approve', 'olama-school'); ?>');
                }
            }
        });
    });

    $('.olama-reject-btn').on('click', function() {
        currentBtn = $(this);
        currentPlanId = currentBtn.data('id');
        currentCard = $('#plan-card-' + currentPlanId);
        $('#olama-feedback-text').val('');
        $('#olama-feedback-modal').css('display', 'flex');
    });

    $('.olama-modal-cancel').on('click', function() {
        $('#olama-feedback-modal').hide();
    });

    $('.olama-modal-submit').on('click', function() {
        var feedback = $('#olama-feedback-text').val();
        if (!feedback.trim()) {
            alert('<?php _e('Please enter some feedback.', 'olama-school'); ?>');
            return;
        }

        $(this).prop('disabled', true).text('<?php _e('Sending...', 'olama-school'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'olama_handle_plan_approval',
                plan_id: currentPlanId,
                status: 'draft', // Rejected back to draft
                feedback: feedback, // We'll handle this in backend
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            },
            success: function(response) {
                $('#olama-feedback-modal').hide();
                $('.olama-modal-submit').prop('disabled', false).text('<?php _e('Send & Request Edits', 'olama-school'); ?>');
                
                if (response.success) {
                    if (currentCard.length > 0) {
                        currentCard.css('background', '#fcf0f1').fadeOut(800, function() {
                            $(this).remove();
                            // Update badge count
                            var badge = $('.olama-school-wrap h2 span');
                            var count = parseInt(badge.text()) - 1;
                            if (count >= 0) badge.text(count);
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(response.data.message || 'Error processing request');
                }
            }
        });
    });
});
</script>

<!-- Feedback Modal HTML (Phase 3) -->
<div id="olama-feedback-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: #fff; padding: 30px; border-radius: 12px; width: 450px; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0;"><?php _e('Request Edits', 'olama-school'); ?></h3>
        <p style="color: #666; font-size: 0.9em;"><?php _e('Please provide feedback to the teacher about why this plan needs changes.', 'olama-school'); ?></p>
        <textarea id="olama-feedback-text" style="width: 100%; height: 120px; margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 6px; resize: none;" placeholder="<?php _e('Enter your comments here...', 'olama-school'); ?>"></textarea>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button class="button olama-modal-cancel"><?php _e('Cancel', 'olama-school'); ?></button>
            <button class="button button-primary olama-modal-submit"><?php _e('Send & Request Edits', 'olama-school'); ?></button>
        </div>
    </div>
</div>

<style>
.olama-notification-wrapper:hover .dashicons { color: #2271b1 !important; }
.olama-notif-item { padding: 12px 15px; border-bottom: 1px solid #f9f9f9; transition: background 0.2s; cursor: pointer; }
.olama-notif-item:hover { background: #f0f6fb; }
.olama-notif-item.unread { border-right: 3px solid #d63638; background: #fffcfc; }
</style>

<?php
// Clear cache on refresh
delete_transient('olama_dashboard_stats');
?>
