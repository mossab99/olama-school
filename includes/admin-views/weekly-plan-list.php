<?php
/**
 * Weekly Plan List View
 */
if (!defined('ABSPATH')) {
    exit;
}

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

// Reuse week selection logic
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);

$current_semesters = Olama_School_Academic::get_semesters($selected_year_id);
// $selected_semester_id and $all_weeks are already defined in class-admin.php
$months_weeks = array();
foreach ($all_weeks as $val => $label) {
    $m_key_start = date('Y-m', strtotime($val));
    $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

    // Check if week ends in a different month
    $m_key_end = date('Y-m', strtotime($val . ' +4 days'));
    if ($m_key_end !== $m_key_start) {
        $months_weeks[$m_key_end][] = array('val' => $val, 'label' => $label);
    }
}

$today = time();
$today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
$initial_week = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;
$selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : date('Y-m', strtotime($initial_week));

if (!isset($months_weeks[$selected_month]) && !empty($months_weeks)) {
    $m_keys = array_keys($months_weeks);
    $selected_month = $m_keys[0];
}

$current_month_weeks = $months_weeks[$selected_month] ?? array();
$week_start = $initial_week;
$valid_week = false;
foreach ($current_month_weeks as $w) {
    if ($w['val'] === $week_start) {
        $valid_week = true;
        break;
    }
}
if (!$valid_week && !empty($current_month_weeks)) {
    $week_start = $current_month_weeks[0]['val'] ?? '';
}

$days = array(
    'Sunday' => date('Y-m-d', strtotime($week_start)),
    'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
    'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
    'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
    'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
);

$all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));

// Group plans by date
$grouped_plans = array();
foreach ($days as $day_name => $date) {
    $grouped_plans[$date] = array_filter($all_plans, function ($p) use ($date) {
        return $p->plan_date === $date;
    });
}

// Coverage Analysis Logic
$current_semester_id = 0;
if ($selected_year_id) {
    $semesters = Olama_School_Academic::get_semesters($selected_year_id);
    $week_sunday = strtotime($week_start);
    $week_thursday = $week_sunday + (4 * 86400);

    foreach ($semesters as $sem) {
        $sem_start = strtotime($sem->start_date);
        $sem_end = strtotime($sem->end_date);

        // Check if the week overlaps with the semester
        if ($sem_start <= $week_thursday && $sem_end >= $week_sunday) {
            $current_semester_id = $sem->id;
            break;
        }
    }

    // Fallback if no exact overlap (e.g. holiday week), use first semester of the year
    if (!$current_semester_id && !empty($semesters)) {
        $current_semester_id = $semesters[0]->id;
    }
}

$analysis_data = [];
if ($current_semester_id && $selected_grade_id) {
    // 1. Get all subjects assigned to this grade (to match Curriculum tab)
    $all_grade_subjects = Olama_School_Subject::get_by_grade($selected_grade_id);

    // 2. Get Curriculum Stats
    $curr_stats = Olama_School_Curriculum::get_curriculum_stats($current_semester_id, $selected_grade_id);
    $stats_map = [];
    foreach ($curr_stats as $cs) {
        $stats_map[$cs->subject_id] = $cs;
    }

    // 3. Count Plans in current week per subject
    $plan_subject_counts = [];
    foreach ($all_plans as $plan) {
        $plan_subject_counts[$plan->subject_id] = ($plan_subject_counts[$plan->subject_id] ?? 0) + 1;
    }
    $total_plans_count = count($all_plans);

    // 4. Calculate total lessons for displayed subjects only
    $total_curr_lessons = 0;
    foreach ($all_grade_subjects as $sub) {
        if (isset($stats_map[$sub->id])) {
            $total_curr_lessons += $stats_map[$sub->id]->lesson_count;
        }
    }

    // 5. Build Analysis Data
    foreach ($all_grade_subjects as $sub) {
        $cs = $stats_map[$sub->id] ?? null;
        if (!$cs || $cs->lesson_count == 0) {
            continue; // Skip subjects not in curriculum for this semester
        }

        $p_count = $plan_subject_counts[$sub->id] ?? 0;
        $p_coverage = $total_plans_count > 0 ? ($p_count / $total_plans_count) : 0;
        $c_coverage = $total_curr_lessons > 0 ? ($cs->lesson_count / $total_curr_lessons) : 0;

        $analysis_data[] = [
            'name' => $sub->subject_name,
            'plan_coverage' => $p_coverage,
            'curr_coverage' => $c_coverage,
            'plan_count' => $p_count,
            'curr_lessons' => $cs->lesson_count
        ];
    }

    // Find the selected grade to get its limits
    $selected_grade_obj = null;
    foreach ($grades as $g) {
        if (intval($g->id) === $selected_grade_id) {
            $selected_grade_obj = $g;
            break;
        }
    }
    $grade_max_plans = $selected_grade_obj ? intval($selected_grade_obj->max_weekly_plans) : 0;
}
?>

<div class="olama-plan-list-container">

    <!-- Filters -->
    <div class="olama-filter-section"
        style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <form method="get" class="olama-filter-row">
            <input type="hidden" name="page" value="olama-school-plans" />
            <input type="hidden" name="tab" value="list" />

            <?php echo Olama_School_Helpers::academic_year_selector($selected_year_id); ?>

            <div class="olama-filter-item">
                <label><?php _e('Semester', 'olama-school'); ?></label>
                <select name="semester_id" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($current_semesters as $sem): ?>
                        <option value="<?php echo $sem->id; ?>" <?php selected($selected_semester_id, $sem->id); ?>>
                            <?php echo esc_html(Olama_School_Helpers::translate($sem->semester_name)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Grade', 'olama-school'); ?></label>
                <select name="grade_id" onchange="this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Section', 'olama-school'); ?></label>
                <select name="section_id" onchange="this.form.submit()">
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                            <?php echo esc_html($section->section_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Month', 'olama-school'); ?></label>
                <select name="plan_month" onchange="this.form.submit()">
                    <?php foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Week Start', 'olama-school'); ?></label>
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

            <div style="margin-left: auto;">
                <button type="button" id="olama-bulk-approve-btn" class="button button-primary"
                    style="height: 35px; background: #10b981; border-color: #059669; font-weight: 600; margin-top: 20px;"
                    data-section="<?php echo $selected_section_id; ?>" data-week="<?php echo esc_attr($week_start); ?>"
                    data-nonce="<?php echo wp_create_nonce('olama_admin_nonce'); ?>">
                    <span class="dashicons dashicons-yes-alt" style="margin-top: 5px; margin-right: 5px;"></span>
                    <?php _e('Approve All', 'olama-school'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Weekly Grid -->
    <div class="olama-weekly-list-grid"
        style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; align-items: stretch;">
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
                                $status_badge_text = $plan->status === 'published' ? Olama_School_Helpers::translate('Published') : Olama_School_Helpers::translate('Draft');
                                $status_badge_color = $plan->status === 'published' ? '#10b981' : '#64748b';
                                $status_badge_bg = $plan->status === 'published' ? '#dcfce7' : '#f1f5f9';
                                ?>
                                <div class="olama-status-badge"
                                    style="display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-bottom: 5px; color: <?php echo $status_badge_color; ?>; background: <?php echo $status_badge_bg; ?>;">
                                    <?php echo esc_html($status_badge_text); ?>
                                </div>

                                <div
                                    style="font-weight: 700; color: <?php echo esc_attr($plan->color_code); ?>; font-size: 0.9em; margin-bottom: 4px;">
                                    <?php echo esc_html($plan->subject_name); ?>
                                </div>
                                <div style="font-size: 0.85em; color: #333; margin-bottom: 6px; line-height: 1.3;">
                                    <?php echo esc_html($plan->lesson_title); ?>
                                </div>
                                <?php if ($plan->homework_sb || $plan->homework_eb): ?>
                                    <div style="font-size: 0.75em; color: #777; border-top: 1px solid #eee; pt: 6px; margin-top: 6px;">
                                        <i class="dashicons dashicons-book-alt"
                                            style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></i>
                                        <?php echo $plan->homework_sb ? __('SB:', 'olama-school') . ' ' . esc_html($plan->homework_sb) : ''; ?>
                                        <?php echo $plan->homework_eb ? ' ' . __('EB:', 'olama-school') . ' ' . esc_html($plan->homework_eb) : ''; ?>
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
                    <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th style="font-weight: 700; background: #f8fafc; color: #475569;">
                                    <?php echo Olama_School_Helpers::translate('Subject Name'); ?>
                                </th>
                                <th style="font-weight: 700; background: #f8fafc; color: #475569; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Plan Coverage'); ?>
                                </th>
                                <th style="font-weight: 700; background: #f8fafc; color: #475569; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Curriculum Coverage'); ?>
                                </th>
                                <th style="font-weight: 700; background: #f8fafc; color: #475569; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysis_data as $row):
                                $diff = $row['plan_coverage'] - $row['curr_coverage'];
                                $status_color = '#10b981'; // Green
                                $status_icon = 'dashicons-yes';
                                $status_text = Olama_School_Helpers::translate('Optimal');

                                if ($diff > 0.05) {
                                    $status_color = '#f59e0b'; // Amber
                                    $status_icon = 'dashicons-arrow-up-alt';
                                    $status_text = Olama_School_Helpers::translate('High');
                                } elseif ($diff < -0.05) {
                                    $status_color = '#ef4444'; // Red
                                    $status_icon = 'dashicons-arrow-down-alt';
                                    $status_text = Olama_School_Helpers::translate('Low');
                                }
                                ?>
                                <tr>
                                    <td style="font-weight: 600; color: #334155;"><?php echo esc_html($row['name']); ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; flex-direction: column; align-items: center;">
                                            <span
                                                style="font-weight: 700; color: #1e293b;"><?php echo number_format($row['plan_coverage'] * 100, 1); ?>%</span>
                                            <small
                                                style="color: #64748b; font-size: 0.75rem;"><?php printf(Olama_School_Helpers::translate('(%d plans)'), $row['plan_count']); ?></small>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; flex-direction: column; align-items: center;">
                                            <span
                                                style="font-weight: 700; color: #1e293b;"><?php echo number_format($row['curr_coverage'] * 100, 1); ?>%</span>
                                            <small
                                                style="color: #64748b; font-size: 0.75rem;"><?php printf(Olama_School_Helpers::translate('(%d lessons)'), $row['curr_lessons']); ?></small>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div
                                            style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; background: <?php echo $status_color; ?>15; color: <?php echo $status_color; ?>; font-weight: 600; font-size: 0.85rem;">
                                            <span class="dashicons <?php echo $status_icon; ?>"
                                                style="font-size: 16px; width: 16px; height: 16px;"></span>
                                            <?php echo $status_text; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if ($grade_max_plans > 0):
                            $weekly_coverage_pct = $total_plans_count / $grade_max_plans;
                            $status_color = '#10b981'; // Green
                            $status_text = Olama_School_Helpers::translate('Optimal');
                            $status_icon = 'dashicons-yes';

                            if ($weekly_coverage_pct > 1.05) {
                                $status_color = '#f59e0b'; // Amber
                                $status_icon = 'dashicons-arrow-up-alt';
                                $status_text = Olama_School_Helpers::translate('High');
                            } elseif ($weekly_coverage_pct < 0.95) {
                                $status_color = '#ef4444'; // Red
                                $status_icon = 'dashicons-arrow-down-alt';
                                $status_text = Olama_School_Helpers::translate('Low');
                            }
                            ?>
                            <tfoot>
                                <tr style="background: #f1f5f9; border-top: 2px solid #e2e8f0;">
                                    <td style="font-weight: 800; color: #1e293b; padding: 15px;">
                                        <?php echo Olama_School_Helpers::translate('Weekly Coverage Total'); ?>
                                    </td>
                                    <td style="text-align: center; padding: 15px;">
                                        <div style="display: flex; flex-direction: column; align-items: center;">
                                            <span style="font-weight: 800; color: #1e293b; font-size: 1.1rem;">
                                                <?php echo number_format($weekly_coverage_pct * 100, 1); ?>%
                                            </span>
                                            <small style="color: #475569; font-weight: 600;">
                                                <?php printf(Olama_School_Helpers::translate('%d / %d Plans'), $total_plans_count, $grade_max_plans); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td style="text-align: center; color: #94a3b8; padding: 15px; font-style: italic;">
                                        -
                                    </td>
                                    <td style="text-align: center; padding: 15px;">
                                        <div
                                            style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 14px; border-radius: 20px; background: <?php echo $status_color; ?>25; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 0.9rem; border: 1px solid <?php echo $status_color; ?>40;">
                                            <span class="dashicons <?php echo $status_icon; ?>"
                                                style="font-size: 18px; width: 18px; height: 18px;"></span>
                                            <?php echo $status_text; ?>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <div
                    style="margin-top: 15px; padding: 12px; background: #f0f9ff; border-radius: 6px; border: 1px solid #e0f2fe; color: #0369a1; font-size: 0.85rem;">
                    <i class="dashicons dashicons-info"
                        style="font-size: 16px; vertical-align: middle; margin-right: 4px;"></i>
                    <?php echo Olama_School_Helpers::translate('Plan Coverage is calculated as (Plans for Subject / Total Plans for the Week). Curriculum Coverage is calculated based on the total lessons assigned to this grade in the current semester.'); ?>
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
</div>