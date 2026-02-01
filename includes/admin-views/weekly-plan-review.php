<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// 1. Academic Scope
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

$is_admin = Olama_School_Permissions::can('olama_approve_plans');
$current_user_id = get_current_user_id();
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$selected_semester_id = isset($_GET['semester_id']) ? $_GET['semester_id'] : '';
if ($selected_semester_id === 'active' || empty($selected_semester_id)) {
    $selected_semester_id = $active_semester ? $active_semester->id : 0;
} else {
    $selected_semester_id = intval($selected_semester_id);
}

// 2. Filters
$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;

// Section filter (dependent on grade)
$sections = array();
if ($selected_grade_id) {
    $sections = Olama_School_Section::get_sections($selected_grade_id, $selected_year_id);
}
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

// Week filter with current week as default
$all_weeks = Olama_School_Academic::get_academic_weeks($selected_year_id, $selected_semester_id, true);
$today = date('Y-m-d');
$current_week_start = null;
foreach ($all_weeks as $week_start => $week_info) {
    $week_end = date('Y-m-d', strtotime($week_start) + (6 * 86400));
    if ($today >= $week_start && $today <= $week_end) {
        $current_week_start = $week_start;
        break;
    }
}
$selected_week = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $current_week_start;

// 3. Fetch Data
$where = "p.academic_year_id = %d AND p.semester_id = %d";
$params = array($selected_year_id, $selected_semester_id);

if ($selected_grade_id) {
    $where .= " AND s.grade_id = %d";
    $params[] = $selected_grade_id;
}

if ($selected_section_id) {
    $where .= " AND p.section_id = %d";
    $params[] = $selected_section_id;
}

if ($selected_week) {
    $week_end = date('Y-m-d', strtotime($selected_week) + (4 * 86400)); // Thursday
    $where .= " AND p.plan_date >= %s AND p.plan_date <= %s";
    $params[] = $selected_week;
    $params[] = $week_end;
}

if (!$is_admin) {
    $where .= " AND p.teacher_id = %d";
    $params[] = $current_user_id;
}

$query = "SELECT p.*, s.subject_name, s.color_code, sec.section_name, g.grade_name, g.id as grade_id, u.display_name as teacher_name,
               un.unit_name, l.lesson_title
          FROM {$wpdb->prefix}olama_plans p
          JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
          JOIN {$wpdb->prefix}olama_sections sec ON p.section_id = sec.id
          JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
          JOIN {$wpdb->users} u ON p.teacher_id = u.ID
          LEFT JOIN {$wpdb->prefix}olama_curriculum_units un ON p.unit_id = un.id
          LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id
          WHERE $where
          ORDER BY p.plan_date DESC, p.created_at DESC";

$all_plans = $wpdb->get_results($wpdb->prepare($query, ...$params));

$pending_plans = array_filter($all_plans, function ($p) {
    return in_array($p->status, array('submitted', 'needs_edit'));
});

$completed_plans = array_filter($all_plans, function ($p) {
    return in_array($p->status, array('approved', 'edited'));
});


?>

<div class="olama-admin-card" style="margin-top: 20px;">
    <!-- Header with Filters -->
    <div style="padding: 25px; border-bottom: 1px solid #e2e8f0; background: #fff; border-radius: 12px 12px 0 0;">
        <form method="get" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
            <input type="hidden" name="page" value="olama-school-plans">
            <input type="hidden" name="tab" value="review">

            <?php echo Olama_School_Helpers::academic_year_selector($selected_year_id); ?>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php echo Olama_School_Helpers::translate('Grade'); ?>
                </label>
                <select name="grade_id" class="olama-select" onchange="this.form.submit()">
                    <option value="0">
                        <?php echo Olama_School_Helpers::translate('All Grades'); ?>
                    </option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                            <?php echo esc_html($g->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Section Dropdown -->
            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php echo Olama_School_Helpers::translate('Section'); ?>
                </label>
                <select name="section_id" class="olama-select" onchange="this.form.submit()">
                    <option value="0">
                        <?php echo Olama_School_Helpers::translate('All Sections'); ?>
                    </option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                            <?php echo esc_html($sec->section_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Week Dropdown -->
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php echo Olama_School_Helpers::translate('Week'); ?>
                </label>
                <select name="week_start" class="olama-select" onchange="this.form.submit()">
                    <option value="">
                        <?php echo Olama_School_Helpers::translate('All Weeks'); ?>
                    </option>
                    <?php foreach ($all_weeks as $ws => $week_info):
                        $is_current = ($ws === $current_week_start);
                        $week_label = Olama_School_Helpers::translate('Week') . ' ' . $week_info['number'] . ' ' . $week_info['label'];
                        if ($is_current) {
                            $week_label .= ' (' . Olama_School_Helpers::translate('Current') . ')';
                        }
                        ?>
                        <option value="<?php echo esc_attr($ws); ?>" <?php selected($selected_week, $ws); ?>>
                            <?php echo esc_html($week_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-right: auto; display: flex; gap: 10px;">
                <button type="submit" class="button button-primary"
                    style="height: 42px; padding: 0 25px; border-radius: 8px;">
                    <?php echo Olama_School_Helpers::translate('Filter'); ?>
                </button>
            </div>
        </form>
    </div>

    <div style="padding: 25px;">
        <!-- Pending Section -->
        <div style="margin-bottom: 40px;">
            <h3
                style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-clock" style="color: #f59e0b;"></span>
                <?php echo Olama_School_Helpers::translate('Pending Edits'); ?>
                <span
                    style="background: #fef3c7; color: #d97706; padding: 2px 10px; border-radius: 12px; font-size: 0.85rem;">
                    <?php echo count($pending_plans); ?>
                </span>
            </h3>

            <?php if (empty($pending_plans)): ?>
                <div
                    style="padding: 40px; text-align: center; background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 12px; color: #64748b;">
                    <?php echo Olama_School_Helpers::translate('No pending reviews found.'); ?>
                </div>
            <?php else: ?>
                <div class="olama-table-wrapper" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <table class="wp-list-table widefat fixed striped" style="border: none;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <?php if ($is_admin): ?>
                                    <th style="padding: 15px; width: 150px;">
                                        <?php echo Olama_School_Helpers::translate('Teacher'); ?>
                                    </th>
                                <?php endif; ?>
                                <th style="padding: 15px; width: 120px;">
                                    <?php echo Olama_School_Helpers::translate('Plan Date'); ?>
                                </th>
                                <th style="padding: 15px;">
                                    <?php echo Olama_School_Helpers::translate('Subject & Topic'); ?>
                                </th>
                                <th style="padding: 15px; width: 120px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                                <th style="padding: 15px; width: 220px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Actions'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_plans as $plan):
                                $status_color = $plan->status === 'needs_edit' ? '#ef4444' : '#f59e0b';
                                $status_label = $plan->status === 'needs_edit' ? Olama_School_Helpers::translate('Needs Revision') : Olama_School_Helpers::translate('Submitted');
                                $has_feedback = !empty($plan->supervisor_feedback) || !empty($plan->teacher_response);
                                $col_count = $is_admin ? 5 : 4;

                                // Prepare plan data for the View Plan button
                                $plan_data = array(
                                    'id' => $plan->id,
                                    'subject_name' => $plan->subject_name,
                                    'unit_name' => isset($plan->unit_name) ? $plan->unit_name : '',
                                    'lesson_title' => isset($plan->lesson_title) ? $plan->lesson_title : '',
                                    'custom_topic' => $plan->custom_topic,
                                    'homework_sb' => isset($plan->homework_sb) ? $plan->homework_sb : '',
                                    'homework_eb' => isset($plan->homework_eb) ? $plan->homework_eb : '',
                                    'homework_nb' => isset($plan->homework_nb) ? $plan->homework_nb : '',
                                    'homework_ws' => isset($plan->homework_ws) ? $plan->homework_ws : '',
                                    'teacher_notes' => isset($plan->teacher_notes) ? $plan->teacher_notes : '',
                                );
                                ?>
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <td style="padding: 15px; vertical-align: middle;">
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?php echo esc_html($plan->teacher_name); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo esc_html($plan->grade_name . ' - ' . $plan->section_name); ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td style="padding: 15px; vertical-align: middle;">
                                        <div style="font-weight: 500;">
                                            <?php echo Olama_School_Helpers::format_date($plan->plan_date); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                            <span class="dashicons dashicons-book"
                                                style="color: <?php echo esc_attr($plan->color_code); ?>;"></span>
                                            <span style="font-weight: 600;">
                                                <?php echo esc_html($plan->subject_name); ?>
                                            </span>
                                        </div>
                                        <div
                                            style="font-size: 12px; color: #64748b; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo esc_html($plan->custom_topic); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle; text-align: center;">
                                        <span
                                            style="display: inline-block; padding: 4px 10px; border-radius: 6px; background: <?php echo $status_color; ?>15; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 11px; border: 1px solid <?php echo $status_color; ?>40;">
                                            <?php if ($plan->status === 'needs_edit'): ?>⚠️
                                            <?php endif; ?>
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle; text-align: center;">
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <?php if ($is_admin): ?>
                                                <button class="button button-small olama-view-plan-btn"
                                                    data-plan="<?php echo htmlspecialchars(json_encode($plan_data), ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="background: #6366f1; color: #fff; border: none;">
                                                    <span class="dashicons dashicons-visibility"
                                                        style="font-size: 14px; vertical-align: middle;"></span>
                                                    <?php echo Olama_School_Helpers::translate('View Plan'); ?>
                                                </button>
                                                <button class="button button-small olama-review-action"
                                                    data-id="<?php echo $plan->id; ?>" data-action="approve"
                                                    style="background: #10b981; color: #fff; border: none;">
                                                    <?php echo Olama_School_Helpers::translate('Final Approve'); ?>
                                                </button>
                                                <button class="button button-small olama-review-action"
                                                    data-id="<?php echo $plan->id; ?>" data-action="feedback"
                                                    style="background: #f59e0b; color: #fff; border: none;">
                                                    <?php echo Olama_School_Helpers::translate('Request Edits'); ?>
                                                </button>
                                            <?php else:
                                                $plan_date_ts = strtotime($plan->plan_date);
                                                $day_index = (int) date('w', $plan_date_ts);
                                                $week_start = date('Y-m-d', $plan_date_ts - ($day_index * 86400));
                                                $plan_month = date('Y-m', $plan_date_ts);
                                                $active_day = date('l', $plan_date_ts);

                                                $edit_url = add_query_arg(array(
                                                    'page' => 'olama-school-plans',
                                                    'tab' => 'creation',
                                                    'academic_year_id' => $plan->academic_year_id,
                                                    'semester_id' => $plan->semester_id,
                                                    'grade_id' => $plan->grade_id,
                                                    'section_id' => $plan->section_id,
                                                    'plan_id' => $plan->id,
                                                    'week_start' => $week_start,
                                                    'plan_month' => $plan_month,
                                                    'active_day' => $active_day
                                                ), admin_url('admin.php'));
                                                ?>
                                                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"
                                                    style="background: #6366f1; color: #fff; border: none; text-decoration: none; padding: 5px 10px;">
                                                    <span class="dashicons dashicons-edit"
                                                        style="font-size: 16px; margin-top: 2px;"></span>
                                                    <?php echo Olama_School_Helpers::translate('Edit Plan'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Feedback Row (expandable) -->
                                <?php if ($has_feedback): ?>
                                    <tr class="olama-feedback-row" style="background: #fefce8;">
                                        <td colspan="<?php echo $col_count; ?>" style="padding: 0 15px 15px 15px;">
                                            <div style="display: flex; flex-wrap: wrap; gap: 15px; padding-top: 10px;">
                                                <?php if (!empty($plan->supervisor_feedback)):
                                                    $feedback_lines = explode("\n", $plan->supervisor_feedback);
                                                    ?>
                                                    <div style="flex: 1; min-width: 300px;">
                                                        <div
                                                            style="font-weight: 700; font-size: 12px; color: #991b1b; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-warning"
                                                                style="color: #ef4444; font-size: 16px;"></span>
                                                            <?php echo Olama_School_Helpers::translate('Admin Feedback'); ?>
                                                        </div>
                                                        <ul
                                                            style="margin: 0; padding-left: 20px; font-size: 12px; color: #7f1d1d; background: #fef2f2; border-radius: 8px; padding: 10px 10px 10px 25px; list-style: disc;">
                                                            <?php foreach ($feedback_lines as $line):
                                                                if (trim($line)):
                                                                    ?>
                                                                    <li style="margin-bottom: 4px;"><?php echo esc_html(trim($line)); ?></li>
                                                                <?php endif; endforeach; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($plan->teacher_response)): ?>
                                                    <div style="flex: 1; min-width: 300px;">
                                                        <div
                                                            style="font-weight: 700; font-size: 12px; color: #1e40af; margin-bottom: 8px; display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-admin-comments"
                                                                style="color: #3b82f6; font-size: 16px;"></span>
                                                            <?php echo Olama_School_Helpers::translate('Teacher Response'); ?>
                                                        </div>
                                                        <div
                                                            style="font-size: 12px; color: #1e3a5f; background: #eff6ff; border-radius: 8px; padding: 10px;">
                                                            <?php echo nl2br(esc_html($plan->teacher_response)); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Section -->
        <div>
            <h3
                style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                <?php echo Olama_School_Helpers::translate('Approved History'); ?>
                <span
                    style="background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 12px; font-size: 0.85rem;">
                    <?php echo count($completed_plans); ?>
                </span>
            </h3>

            <?php if (empty($completed_plans)): ?>
                <div
                    style="padding: 40px; text-align: center; background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 12px; color: #64748b;">
                    <?php echo Olama_School_Helpers::translate('No completed reviews found.'); ?>
                </div>
            <?php else: ?>
                <div class="olama-table-wrapper" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <table class="wp-list-table widefat fixed striped" style="border: none;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <?php if ($is_admin): ?>
                                    <th style="padding: 15px; width: 150px;">
                                        <?php echo Olama_School_Helpers::translate('Teacher'); ?>
                                    </th>
                                <?php endif; ?>
                                <th style="padding: 15px; width: 120px;">
                                    <?php echo Olama_School_Helpers::translate('Plan Date'); ?>
                                </th>
                                <th style="padding: 15px;">
                                    <?php echo Olama_School_Helpers::translate('Subject & Topic'); ?>
                                </th>
                                <th style="padding: 15px; width: 120px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                                <th style="padding: 15px;">
                                    <?php echo Olama_School_Helpers::translate('Review Content'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_plans as $plan):
                                $status_color = $plan->status === 'approved' ? '#10b981' : '#6366f1';
                                $status_label = $plan->status === 'approved' ? Olama_School_Helpers::translate('Approved') : Olama_School_Helpers::translate('Edited');
                                ?>
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <td style="padding: 15px; vertical-align: middle;">
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?php echo esc_html($plan->teacher_name); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo esc_html($plan->grade_name . ' - ' . $plan->section_name); ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td style="padding: 15px; vertical-align: middle;">
                                        <div style="font-weight: 500;">
                                            <?php echo Olama_School_Helpers::format_date($plan->plan_date); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                                            <span class="dashicons dashicons-book"
                                                style="color: <?php echo esc_attr($plan->color_code); ?>;"></span>
                                            <span style="font-weight: 600;">
                                                <?php echo esc_html($plan->subject_name); ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo esc_html($plan->custom_topic); ?>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle; text-align: center;">
                                        <span
                                            style="display: inline-block; padding: 4px 10px; border-radius: 6px; background: <?php echo $status_color; ?>15; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 11px; border: 1px solid <?php echo $status_color; ?>40;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; vertical-align: middle;">
                                        <?php if (!empty($plan->supervisor_feedback)): ?>
                                            <div
                                                style="background: #f8fafc; border-right: 2px solid #cbd5e1; padding: 8px 12px; border-radius: 4px; font-size: 11px; color: #64748b;">
                                                <strong>
                                                    <?php echo Olama_School_Helpers::translate('Feedback History'); ?>:
                                                </strong><br>
                                                <?php echo nl2br(esc_html($plan->supervisor_feedback)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Action Modal -->
<div id="olama-review-modal" class="olama-modal"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div
        style="background: #fff; width: 450px; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div
            style="padding: 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h4 id="review-modal-title" style="margin: 0; font-weight: 700;"></h4>
            <button onclick="document.getElementById('olama-review-modal').style.display='none'"
                style="border: none; background: none; cursor: pointer;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div style="padding: 25px;">
            <input type="hidden" id="review-plan-id">
            <input type="hidden" id="review-action-type">
            <div id="feedback-field" style="display: none;">
                <label style="display: block; font-weight: 600; margin-bottom: 10px;">
                    <?php echo Olama_School_Helpers::translate('Supervisor Feedback'); ?>
                </label>
                <textarea id="review-feedback"
                    style="width: 100%; height: 120px; border-radius: 8px; border: 1px solid #d1d5db; padding: 12px;"></textarea>
            </div>
            <div id="review-confirm-text" style="margin-bottom: 20px; color: #4b5563;"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="document.getElementById('olama-review-modal').style.display='none'" class="button">
                    <?php echo Olama_School_Helpers::translate('Cancel'); ?>
                </button>
                <button id="confirm-review-btn" class="button button-primary">
                    <?php echo Olama_School_Helpers::translate('Confirm'); ?>
                </button>
            </div>
        </div>
    </div>

<!-- View Plan Modal -->
<div id="olama-view-plan-modal"
    style="display: none; position: fixed; inset: 0 !important; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999 !important; justify-content: center; align-items: center;">
        <div
            style="background: #fff; border-radius: 16px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div
                style="padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 16px 16px 0 0;">
                <h3 id="view-plan-modal-title" style="margin: 0; color: #fff; font-size: 1.25rem;">
                    <?php echo Olama_School_Helpers::translate('Plan Details'); ?>
                </h3>
                <button onclick="document.getElementById('olama-view-plan-modal').style.display='none'"
                    style="background: rgba(255,255,255,0.2); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; color: #fff; font-size: 18px;">
                    ✕
                </button>
            </div>
            <div style="padding: 25px;">
                <!-- Plan Details Form -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <!-- Subject -->
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">
                            <?php echo Olama_School_Helpers::translate('Subject'); ?>
                        </label>
                        <input type="text" id="view-plan-subject" readonly
                            style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                    </div>
                    <!-- Unit -->
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">
                            <?php echo Olama_School_Helpers::translate('Unit'); ?>
                        </label>
                        <input type="text" id="view-plan-unit" readonly
                            style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                    </div>
                    <!-- Lesson -->
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">
                            <?php echo Olama_School_Helpers::translate('Lesson'); ?>
                        </label>
                        <input type="text" id="view-plan-lesson" readonly
                            style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                    </div>
                    <!-- Custom Topic -->
                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">
                            <?php echo Olama_School_Helpers::translate('Custom Topic'); ?>
                        </label>
                        <input type="text" id="view-plan-topic" readonly
                            style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                    </div>
                </div>

                <!-- Homework Section -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <h4
                        style="margin: 0 0 15px 0; color: #6366f1; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-welcome-write-blog" style="font-size: 18px;"></span>
                        <?php echo Olama_School_Helpers::translate('Homework'); ?>
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">SB</label>
                            <input type="text" id="view-plan-sb" readonly
                                style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">EB</label>
                            <input type="text" id="view-plan-eb" readonly
                                style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">NB</label>
                            <input type="text" id="view-plan-nb" readonly
                                style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px; color: #374151; font-size: 13px;">WS</label>
                            <input type="text" id="view-plan-ws" readonly
                                style="width: 100%; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937;">
                        </div>
                    </div>
                </div>

                <!-- Teacher Notes -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151; font-size: 13px;">
                        <span class="dashicons dashicons-format-aside"
                            style="font-size: 16px; vertical-align: middle;"></span>
                        <?php echo Olama_School_Helpers::translate('Teacher Notes'); ?>
                    </label>
                    <textarea id="view-plan-notes" readonly
                        style="width: 100%; height: 80px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; color: #1f2937; resize: none;"></textarea>
                </div>

                <!-- Close Button -->
                <div style="margin-top: 25px; text-align: center;">
                    <button onclick="document.getElementById('olama-view-plan-modal').style.display='none'"
                        class="button button-primary" style="padding: 10px 40px; border-radius: 8px;">
                        <?php echo Olama_School_Helpers::translate('Close'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            // Use delegated events for better reliability
            $(document).on('click', '.olama-view-plan-btn', function (e) {
                e.preventDefault();
                const planData = $(this).data('plan');
                const modal = $('#olama-view-plan-modal');

            if (!planData) {
                alert('Error: No plan data found on this button.');
                console.error('No plan data found on button');
                return;
            }

            // Debug alert to confirm click
            console.log('Opening modal for:', planData.subject_name);

            // Populate the modal fields
            $('#view-plan-subject').val(planData.subject_name || '-');
            $('#view-plan-unit').val(planData.unit_name || '-');
            $('#view-plan-lesson').val(planData.lesson_title || '-');
            $('#view-plan-topic').val(planData.custom_topic || '-');
            $('#view-plan-sb').val(planData.homework_sb || '-');
            $('#view-plan-eb').val(planData.homework_eb || '-');
            $('#view-plan-nb').val(planData.homework_nb || '-');
            $('#view-plan-ws').val(planData.homework_ws || '-');
            $('#view-plan-notes').val(planData.teacher_notes || '-');

            modal.css('display', 'flex').show(); // Force show just in case
            });

            // Review action handlers
            $('.olama-review-action').on('click', function () {
                const id = $(this).data('id');
                const action = $(this).data('action');
                const modal = $('#olama-review-modal');

                $('#review-plan-id').val(id);
                $('#review-action-type').val(action);
                $('#feedback-field').hide();
                $('#review-confirm-text').text('');

                if (action === 'approve') {
                    $('#review-modal-title').text('<?php echo Olama_School_Helpers::translate('Final Approve'); ?>');
                    $('#review-confirm-text').text('<?php echo Olama_School_Helpers::translate('Are you sure you want to approve this plan for parents and students?'); ?>');
                } else if (action === 'feedback') {
                    $('#review-modal-title').text('<?php echo Olama_School_Helpers::translate('Request Edits'); ?>');
                    $('#feedback-field').show();
                }

                modal.css('display', 'flex');
            });

            $('#confirm-review-btn').on('click', function () {
                const btn = $(this);
                const planId = $('#review-plan-id').val();
                const action = $('#review-action-type').val();
                const feedback = $('#review-feedback').val();

                let status = 'approved';
                if (action === 'feedback') status = 'needs_edit';

                btn.prop('disabled', true).text('<?php echo Olama_School_Helpers::translate('Processing...'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'olama_handle_plan_approval',
                        nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>',
                        plan_id: planId,
                        status: status,
                        feedback: feedback
                    },
                    success: function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || 'Error');
                            btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Confirm'); ?>');
                        }
                    }
                });
            });
        });
    </script>