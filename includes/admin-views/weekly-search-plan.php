<?php
/**
 * Weekly Search Plan View
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$grades = Olama_School_Grade::get_grades();
if (!$grades) {
    echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
    return;
}

$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
$sections = Olama_School_Section::get_by_grade($selected_grade_id);

$selected_section_id = 0;
if (!empty($sections)) {
    $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);

    // Validate section belongs to the selected grade
    $is_valid_section = false;
    foreach ($sections as $sec) {
        if (intval($sec->id) === $selected_section_id) {
            $is_valid_section = true;
            break;
        }
    }

    if (!$is_valid_section) {
        $selected_section_id = intval($sections[0]->id);
    }
}

// Get subjects for the selected grade
$all_grade_subjects = Olama_School_Subject::get_by_grade($selected_grade_id, true);
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : (isset($all_grade_subjects[0]->id) ? intval($all_grade_subjects[0]->id) : 0);

// $selected_year_id, $selected_semester_id and $all_weeks are already defined in class-admin.php
// $all_weeks is already defined in class-admin.php
$months_weeks = array();
foreach ($all_weeks as $val => $label) {
    if (empty($val))
        continue;
    $m_key_start = date('Y-m', strtotime($val));
    $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

    $week_range = Olama_School_Helpers::get_week_range($val);
    $m_key_end = date('Y-m', strtotime($week_range['end']));
    if ($m_key_end !== $m_key_start) {
        $months_weeks[$m_key_end][] = array('val' => $val, 'label' => $label);
    }
}

// Sort months chronologically
ksort($months_weeks);

// Determine the month to show — default to current month
$selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '';
if (empty($selected_month) || !isset($months_weeks[$selected_month])) {
    $today_month = date('Y-m');
    if (isset($months_weeks[$today_month])) {
        $selected_month = $today_month;
    } elseif (!empty($months_weeks)) {
        $m_keys = array_keys($months_weeks);
        $selected_month = $m_keys[0];
    }
}

$current_month_weeks = $months_weeks[$selected_month] ?? array();

// Determine the week to show — smart current-week detection
$week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '';
$valid_week = false;
if (!empty($week_start)) {
    foreach ($current_month_weeks as $w) {
        if ($w['val'] === $week_start) {
            $valid_week = true;
            break;
        }
    }
}
if (!$valid_week && !empty($current_month_weeks)) {
    $today = date('Y-m-d');
    $found_current = false;
    foreach ($current_month_weeks as $w) {
        $w_range = Olama_School_Helpers::get_week_range($w['val']);
        if ($today >= $w_range['start'] && $today <= $w_range['end']) {
            $week_start = $w['val'];
            $found_current = true;
            break;
        }
    }
    if (!$found_current) {
        $week_start = $current_month_weeks[0]['val'] ?? '';
    }
}

$week_range = Olama_School_Helpers::get_week_range($week_start);
$week_end = $week_range['end'];
$school_days = Olama_School_Helpers::get_school_days();
$days = array();
foreach ($school_days as $day_en) {
    // Find the date for this day within the week
    $date = date('Y-m-d', strtotime("next $day_en", strtotime($week_start . " -1 day")));
    $days[$day_en] = $date;
}

// Filter by subject if selected
$all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, $week_end, $selected_subject_id);

// Group plans by date
$grouped_plans = array();
foreach ($days as $day_name => $date) {
    $grouped_plans[$date] = array_filter($all_plans, function ($p) use ($date) {
        return $p->plan_date === $date;
    });
}

// Coverage Analysis Logic (Filtered by selected subject)
$current_semester_id = 0;
if ($selected_year_id) {
    $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
    $semesters = $active_semester ? array($active_semester) : [];
    $week_start_ts = strtotime($week_start);
    $week_end_ts = strtotime($week_end);

    foreach ($semesters as $sem) {
        $sem_start = strtotime($sem->start_date);
        $sem_end = strtotime($sem->end_date);

        if ($sem_start <= $week_end_ts && $sem_end >= $week_start_ts) {
            $current_semester_id = $sem->id;
            break;
        }
    }

    if (!$current_semester_id && !empty($semesters)) {
        $current_semester_id = $semesters[0]->id;
    }
}

$analysis_data = [];
if ($current_semester_id && $selected_grade_id && $selected_subject_id) {
    $sub = Olama_School_Subject::get_subject($selected_subject_id);
    if ($sub && intval($sub->grade_id) === $selected_grade_id) {

        $required_plans = intval($sub->max_weekly_plans);

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN plan_type = 'homework' THEN 1 ELSE 0 END) as approved_homework,
                SUM(CASE WHEN plan_type = 'review' THEN 1 ELSE 0 END) as approved_reviews
             FROM {$wpdb->prefix}olama_plans 
             WHERE subject_id = %d AND section_id = %d 
             AND status = 'approved'
             AND plan_date >= %s AND plan_date <= %s",
            $sub->id,
            $selected_section_id,
            $week_start,
            $week_end
        ));

        $approved_plans = intval($stats->approved_homework);
        $approved_reviews = intval($stats->approved_reviews);

        $analysis_date = $week_start;
        $schedule_type = Olama_School_Schedule::is_ramadan($analysis_date) ? 'ramadan' : 'normal';

        $sched_periods = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_schedule 
             WHERE subject_id = %d AND section_id = %d AND semester_id = %d AND schedule_type = %s",
            $sub->id,
            $selected_section_id,
            $current_semester_id,
            $schedule_type
        ));
        $total_sched_capacity = intval($sched_periods);

        $teacher_cov_pct = $required_plans > 0 ? min(100, round(($approved_plans / $required_plans) * 100, 1)) : 0;
        $schedule_cov_pct = $total_sched_capacity > 0 ? min(100, round((($approved_plans + $approved_reviews) / $total_sched_capacity) * 100, 1)) : 0;

        $analysis_data[] = [
            'id' => $sub->id,
            'name' => $sub->subject_name,
            'color' => $sub->color_code,
            'required' => $required_plans,
            'approved' => $approved_plans,
            'reviews' => $approved_reviews,
            'teacher_coverage' => $teacher_cov_pct,
            'schedule_coverage' => $schedule_cov_pct,
            'sched_capacity' => $total_sched_capacity
        ];
    }
}
?>

<div class="olama-plan-list-container">
    <!-- Filters -->
    <div class="olama-filter-section"
        style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <form method="get" class="olama-filter-row">
            <input type="hidden" name="page" value="olama-school-plans" />
            <input type="hidden" name="tab" value="search" />

            <div class="olama-filter-item">
                <label><?php echo Olama_School_Helpers::translate('Academic Year'); ?></label>
                <input type="hidden" name="academic_year_id" value="<?php echo esc_attr($selected_year_id); ?>" />
                <div
                    style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php echo esc_html($active_year ? $active_year->year_name : '—'); ?>
                    <span
                        style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Semester', 'olama-school'); ?></label>
                <input type="hidden" name="semester_id" value="<?php echo esc_attr($selected_semester_id); ?>" />
                <div
                    style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php
                    $semester_display = '—';
                    if ($active_semester) {
                        $semester_display = Olama_School_Helpers::translate($active_semester->semester_name);
                    }
                    echo esc_html($semester_display);
                    ?>
                    <span
                        style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>

            <div class="olama-filter-item">
                <label>
                    <?php _e('Grade', 'olama-school'); ?>
                </label>
                <select name="grade_id" onchange="this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label>
                    <?php _e('Section', 'olama-school'); ?>
                </label>
                <select name="section_id" onchange="this.form.submit()">
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                            <?php echo esc_html($section->section_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label>
                    <?php echo Olama_School_Helpers::translate('Subject'); ?>
                </label>
                <select name="subject_id" onchange="this.form.submit()">
                    <option value="">
                        <?php echo Olama_School_Helpers::translate('Select Subject'); ?>
                    </option>
                    <?php foreach ($all_grade_subjects as $subject): ?>
                        <option value="<?php echo $subject->id; ?>" <?php selected($selected_subject_id, $subject->id); ?>>
                            <?php echo esc_html($subject->subject_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label>
                    <?php _e('Month', 'olama-school'); ?>
                </label>
                <select name="plan_month" onchange="this.form.submit()">
                    <?php foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label>
                    <?php _e('Week Start', 'olama-school'); ?>
                </label>
                <select name="week_start" onchange="this.form.submit()">
                    <?php
                    $w_count = 1;
                    foreach ($current_month_weeks as $w): ?>
                        <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                            <?php echo sprintf(__('%s %d', 'olama-school'), __('Week', 'olama-school'), $w_count) . ' ' . esc_html($w['label']); ?>
                        </option>
                        <?php $w_count++; endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="olama-weekly-list-grid"
        style="display: grid; grid-template-columns: repeat(<?php echo count($days); ?>, 1fr); gap: 15px; align-items: stretch;">
        <?php foreach ($days as $day_name => $date): ?>
            <div class="olama-day-column"
                style="background: #fbfbfb; border-radius: 8px; border: 1px solid #eee; display: flex; flex-direction: column;">
                <div class="day-header"
                    style="background: #f1f1f1; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; border-radius: 8px 8px 0 0;">
                    <strong style="display: block; color: #1d2327;">
                        <?php echo __($day_name, 'olama-school'); ?>
                    </strong>
                    <small style="color: #666;">
                        <?php echo Olama_School_Helpers::format_date($date, false, 'M d'); ?>
                    </small>
                </div>
                <div class="day-content" style="padding: 10px; flex-grow: 1;">
                    <?php if (!empty($grouped_plans[$date])): ?>
                        <?php foreach ($grouped_plans[$date] as $plan):
                            $status_data = Olama_School_Helpers::get_progress_status($plan->plan_date, $plan->lesson_start_date, $plan->lesson_end_date);
                            ?>
                            <div class="olama-plan-card" data-plan='<?php echo esc_attr(wp_json_encode($plan)); ?>'
                                style="border-left: 4px solid <?php echo esc_attr($plan->color_code); ?>; background: #fff; padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 10px; cursor: pointer; position: relative;">

                                <?php if ($status_data): ?>
                                    <div class="olama-progress-badge <?php echo esc_attr($status_data['class']); ?>"
                                        title="<?php echo esc_attr($status_data['label']); ?>">
                                        <?php echo esc_html($status_data['label']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $status = $plan->status;
                                $status_badge_text = Olama_School_Helpers::translate('Draft');
                                $status_badge_color = '#64748b';
                                $status_badge_bg = '#f1f5f9';

                                if ($status === 'approved' || $status === 'published') {
                                    $status_badge_text = Olama_School_Helpers::translate('Approved');
                                    $status_badge_color = '#10b981';
                                    $status_badge_bg = '#dcfce7';
                                } elseif ($status === 'submitted') {
                                    $status_badge_text = Olama_School_Helpers::translate('Submitted');
                                    $status_badge_color = '#d97706';
                                    $status_badge_bg = '#fef3c7';
                                } elseif ($status === 'needs_edit') {
                                    $status_badge_text = Olama_School_Helpers::translate('Needs Revision');
                                    $status_badge_color = '#ef4444';
                                    $status_badge_bg = '#fef2f2';
                                } elseif ($status === 'edited') {
                                    $status_badge_text = Olama_School_Helpers::translate('Edited');
                                    $status_badge_color = '#6366f1';
                                    $status_badge_bg = '#eef2ff';
                                }
                                ?>
                                <div class="olama-status-badge"
                                    style="display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-bottom: 5px; color: <?php echo $status_badge_color; ?>; background: <?php echo $status_badge_bg; ?>;">
                                    <?php echo esc_html($status_badge_text); ?>
                                </div>

                                <?php if (isset($plan->plan_type) && $plan->plan_type === 'review'): ?>
                                    <div class="olama-plan-type-badge"
                                        style="display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-bottom: 5px; margin-left: 5px; color: #7c3aed; background: #f3e8ff;">
                                        🔄
                                        <?php echo Olama_School_Helpers::translate('Review'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($plan->status === 'needs_edit'): ?>
                                    <span class="olama-feedback-warning" title="<?php echo esc_attr($plan->supervisor_feedback); ?>"
                                        style="position: absolute; top: 8px; right: 8px; font-size: 18px; cursor: help; z-index: 5;">
                                        ⚠️
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($plan->teacher_name)): ?>
                                    <div class="olama-teacher-badge"
                                        style="display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-bottom: 5px; color: #475569; background: #e2e8f0; margin-left: 4px;">
                                        <i class="dashicons dashicons-admin-users"
                                            style="font-size: 12px; width: 12px; height: 12px; margin-top: 1px;"></i>
                                        <?php echo esc_html($plan->teacher_name); ?>
                                    </div>
                                <?php endif; ?>

                                <div
                                    style="font-weight: 700; color: <?php echo esc_attr($plan->color_code); ?>; font-size: 0.9em; margin-bottom: 4px;">
                                    <?php echo esc_html($plan->subject_name); ?>
                                </div>
                                <div style="font-size: 0.85em; color: #333; margin-bottom: 6px; line-height: 1.3;">
                                    <?php echo esc_html($plan->lesson_title); ?>
                                </div>
                                <?php if ($plan->homework_sb || $plan->homework_eb || $plan->homework_nb || $plan->homework_ws): ?>
                                    <div
                                        style="font-size: 0.75em; color: #777; border-top: 1px solid #eee; padding-top: 6px; margin-top: 6px;">
                                        <i class="dashicons dashicons-book-alt"
                                            style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></i>
                                        <?php echo $plan->homework_sb ? Olama_School_Helpers::translate('SB:') . ' ' . esc_html($plan->homework_sb) : ''; ?>
                                        <?php echo $plan->homework_eb ? ' ' . Olama_School_Helpers::translate('EB:') . ' ' . esc_html($plan->homework_eb) : ''; ?>
                                        <?php echo $plan->homework_nb ? ' ' . Olama_School_Helpers::translate('NB:') . ' ' . esc_html($plan->homework_nb) : ''; ?>
                                        <?php echo $plan->homework_ws ? ' ' . Olama_School_Helpers::translate('WS:') . ' ' . esc_html($plan->homework_ws) : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #ccc; font-style: italic; font-size: 0.85em; margin-top: 20px;">
                            <?php _e('No plans', 'olama-school'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Coverage Analysis Table -->
    <?php if (!empty($analysis_data)): ?>
        <div class="olama-analysis-section" style="margin-top: 30px;">
            <div class="olama-card"
                style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <h3
                    style="margin-top: 0; color: #1e293b; font-size: 1.1rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-chart-bar" style="color: #6366f1;"></span>
                    <?php echo Olama_School_Helpers::translate('Weekly Plan Analysis'); ?>
                </h3>

                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat striped" style="border: none; box-shadow: none;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th
                                    style="padding: 15px 20px; font-weight: 700; color: #475569; text-align: left; min-width: 180px;">
                                    <?php echo Olama_School_Helpers::translate('Subject Name'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 100px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Required Plans'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 100px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Approved Plans'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 100px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Reviews'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 140px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Teacher Plan Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 140px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Schedule Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; text-align: center; width: 110px;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysis_data as $row):
                                // Status logic
                                $status_label = Olama_School_Helpers::translate('Optimal');
                                $status_color = '#10b981';
                                $status_icon = 'dashicons-yes';
                                $bg_color = 'rgba(16, 185, 129, 0.1)';

                                if ($row['teacher_coverage'] >= 95) {
                                } elseif ($row['teacher_coverage'] >= 80) {
                                    $status_label = Olama_School_Helpers::translate('High');
                                    $status_color = '#f59e0b';
                                    $status_icon = 'dashicons-arrow-up-alt';
                                    $bg_color = 'rgba(245, 158, 11, 0.1)';
                                } else {
                                    $status_label = Olama_School_Helpers::translate('Low');
                                    $status_color = '#ef4444';
                                    $status_icon = 'dashicons-arrow-down-alt';
                                    $bg_color = 'rgba(239, 68, 68, 0.1)';
                                }
                                ?>
                                <tr>
                                    <td style="padding: 15px 20px; text-align: left;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="dashicons dashicons-book"
                                                style="color: <?php echo esc_attr($row['color']); ?>;"></span>
                                            <span style="font-weight: 600; color: #1e293b;">
                                                <?php echo esc_html($row['name']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                        <?php echo $row['required']; ?>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                        <?php echo $row['approved']; ?>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                        <?php echo $row['reviews']; ?>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center;">
                                        <div style="font-weight: 700; color: #1e293b;">
                                            <?php echo $row['teacher_coverage']; ?>%
                                        </div>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center;">
                                        <div style="font-weight: 700; color: #1e293b;">
                                            <?php echo $row['schedule_coverage']; ?>%
                                        </div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo ($row['approved'] + $row['reviews']) . ' / ' . $row['sched_capacity']; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 15px 10px; text-align: center;">
                                        <div
                                            style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 14px; border-radius: 20px; background: <?php echo $bg_color; ?>; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 0.85rem; border: 1px solid <?php echo $status_color; ?>30; min-width: 90px;">
                                            <span class="dashicons <?php echo $status_icon; ?>"
                                                style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            <?php echo $status_label; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plan Details Section -->
    <div id="olama-plan-details-container" style="margin-top: 30px;">
        <div id="olama-plan-details-card"
            style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); display: none;">
            <!-- Content will be injected by JS -->
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="olama-feedback-modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div
            style="background: #fff; padding: 30px; border-radius: 12px; width: 400px; max-width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0; color: #1e293b;"><?php echo Olama_School_Helpers::translate('Request Edits'); ?>
            </h3>
            <textarea id="olama-feedback-text"
                style="width: 100%; height: 120px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; font-family: inherit;"
                placeholder="<?php echo Olama_School_Helpers::translate('Enter your feedback here...'); ?>"></textarea>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button class="button olama-modal-cancel"
                    style="height: 35px;"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
                <button id="olama-confirm-feedback-btn" class="button button-primary olama-modal-submit"
                    style="height: 35px;"><?php echo Olama_School_Helpers::translate('Send & Request Edits'); ?></button>
            </div>
        </div>
    </div>
</div>