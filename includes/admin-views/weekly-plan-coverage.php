<?php
/**
 * Weekly Plan Curriculum Coverage View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// 1. Get Academic Infrastructure
$requested_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : 0;
if ($requested_year_id) {
    $active_year = Olama_School_Academic::get_year($requested_year_id);
} else {
    $active_year = Olama_School_Academic::get_active_year();
}

if (!$active_year) {
    echo '<div class="error"><p>' . __('Please activate an academic year first.', 'olama-school') . '</p></div>';
    return;
}

$semesters = Olama_School_Academic::get_semesters($active_year->id);
$active_semester = Olama_School_Academic::get_active_semester($active_year->id);

if (!$semesters) {
    echo '<div class="error"><p>' . __('Please create and activate a semester for the active year.', 'olama-school') . '</p></div>';
    return;
}

// 2. Selection Handling
$active_semester = Olama_School_Academic::get_active_semester($active_year->id);
$default_semester_id = $active_semester ? intval($active_semester->id) : (isset($semesters[0]->id) ? intval($semesters[0]->id) : 0);

$requested_semester_id = isset($_GET['coverage_semester']) ? $_GET['coverage_semester'] : 'active';
if ($requested_semester_id === 'active' || empty($requested_semester_id)) {
    $selected_semester_id = $default_semester_id;
} else {
    $selected_semester_id = intval($requested_semester_id);
}
$selected_grade_id = isset($_GET['coverage_grade']) ? intval($_GET['coverage_grade']) : 0;

$sections = $selected_grade_id ? Olama_School_Section::get_by_grade($selected_grade_id, $active_year->id) : [];
$selected_section_id = (isset($_GET['coverage_section']) && $_GET['coverage_section'] != '0') ? intval($_GET['coverage_section']) : (isset($sections[0]->id) ? intval($sections[0]->id) : 0);

$current_semester = null;
foreach ($semesters as $sem) {
    if (intval($sem->id) === $selected_semester_id) {
        $current_semester = $sem;
        break;
    }
}

// Fallback for current_semester if selection is invalid for active year
if (!$current_semester && !empty($semesters)) {
    $current_semester = $semesters[0];
    $selected_semester_id = intval($current_semester->id);
}

$years = Olama_School_Academic::get_years();
$grades = Olama_School_Grade::get_grades();
// Getting subjects with limits
$subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id) : [];

// 3. Determine Effective Date Range for filtering plans (Sunday to Thursday alignment)
$semester_weeks = Olama_School_Academic::get_academic_weeks($active_year->id, $selected_semester_id, true);
$effective_start = $current_semester->start_date;
$effective_end = $current_semester->end_date;

// Week Filter Logic
$selected_week = isset($_GET['coverage_week']) ? sanitize_text_field($_GET['coverage_week']) : '';

if (!empty($semester_weeks)) {
    if ($selected_week && isset($semester_weeks[$selected_week])) {
        // Filter by specific week
        $effective_start = $semester_weeks[$selected_week]['start'];
        $effective_end = $semester_weeks[$selected_week]['end'];
    } else {
        // Default to semester range
        $week_dates = array_keys($semester_weeks);
        sort($week_dates);
        if (!empty($week_dates)) {
            $effective_start = $week_dates[0];
            // End date is Thursday of the last week
            $last_week_start = end($week_dates);
            $effective_end = date('Y-m-d', strtotime($last_week_start . ' +4 days'));
        }
    }
}

// 4. Calculate Number of Weeks for Scaling Limits
$num_weeks = 1;
if (!$selected_week && !empty($semester_weeks)) {
    $datetime1 = new DateTime($effective_start);
    $datetime2 = new DateTime($effective_end);
    $interval = $datetime1->diff($datetime2);
    $days = $interval->days + 1;
    $num_weeks = max(1, ceil($days / 7));
}
?>

<div class="olama-coverage-container"
    style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="margin: 0; color: #1e293b; font-size: 24px; font-weight: 700;">
                <span class="dashicons dashicons-analytics"
                    style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1;"></span>
                <?php echo Olama_School_Helpers::translate('Weekly Plan Analysis'); ?>
            </h1>
            <p class="description" style="font-size: 14px; margin-top: 5px;">
                <?php echo Olama_School_Helpers::translate('Overall metrics for plan coverage and curriculum progress.'); ?>
            </p>
        </div>

        <div
            style="display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0;">

            <label
                style="font-weight: 600; color: #64748b;"><?php echo Olama_School_Helpers::translate('Year:'); ?></label>
            <select onchange="window.location.href=add_query_arg('academic_year_id', this.value)"
                style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                <?php foreach ($years as $yr): ?>
                    <option value="<?php echo $yr->id; ?>" <?php selected($active_year->id, $yr->id); ?>>
                        <?php echo esc_html($yr->year_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div style="border-left: 1px solid #e2e8f0; height: 20px; margin: 0 5px;"></div>

            <?php if ($selected_grade_id && !empty($sections)): ?>
                <div
                    style="display: flex; align-items: center; gap: 10px; border-inline-end: 1px solid #e2e8f0; padding-inline-end: 15px; margin-inline-end: 5px;">
                    <label
                        style="font-weight: 600; color: #64748b;"><?php echo Olama_School_Helpers::translate('Section:'); ?></label>
                    <select onchange="window.location.href=add_query_arg('coverage_section', this.value)"
                        style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                <?php echo esc_html($sec->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <label
                style="font-weight: 600; color: #64748b;"><?php echo Olama_School_Helpers::translate('Semester:'); ?></label>
            <select onchange="window.location.href=add_query_arg('coverage_semester', this.value)"
                style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                <option value="active" <?php selected($requested_semester_id, 'active'); ?>>
                    <?php echo Olama_School_Helpers::translate('Active Semester'); ?></option>
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?php echo $sem->id; ?>" <?php selected($selected_semester_id, $sem->id); ?>>
                        <?php echo esc_html($sem->semester_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label
                style="font-weight: 600; color: #64748b; margin-left: 10px;"><?php echo Olama_School_Helpers::translate('Week:'); ?></label>
            <select onchange="window.location.href=add_query_arg('coverage_week', this.value)"
                style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                <option value=""><?php echo Olama_School_Helpers::translate('All Weeks'); ?></option>
                <?php foreach ($semester_weeks as $start_date => $week_data):
                    $label = sprintf(Olama_School_Helpers::translate('Week %d (%s)'), $week_data['number'], Olama_School_Helpers::format_date($start_date));
                    ?>
                    <option value="<?php echo esc_attr($start_date); ?>" <?php selected($selected_week, $start_date); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div style="display: flex; gap: 30px;">
        <!-- Left Sidebar: Grades -->
        <div style="width: 250px; flex-shrink: 0;">
            <h3
                style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 15px; padding-left: 5px;">
                <?php echo Olama_School_Helpers::translate('Select Grade'); ?>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($grades as $grade):
                    $is_active = (intval($grade->id) === $selected_grade_id);
                    $url = add_query_arg(array('coverage_grade' => $grade->id, 'coverage_section' => 0, 'academic_year_id' => $active_year->id));
                    ?>
                    <a href="<?php echo esc_url($url); ?>"
                        style="padding: 12px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s;
          <?php echo $is_active ? 'background: #2271b1; color: #fff; box-shadow: 0 4px 12px rgba(34,113,177,0.2);' : 'background: #f1f5f9; color: #475569;'; ?>">
                        <?php echo esc_html($grade->grade_name); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"
                            style="margin-inline-start: auto; font-size: 18px; margin-top: 2px; <?php echo $is_active ? 'opacity: 1;' : 'opacity: 0.3;'; ?>"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main Content: Analysis -->
        <div style="flex-grow: 1;">
            <?php if (!$selected_grade_id): ?>
                <div
                    style="height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8;">
                    <span class="dashicons dashicons-arrow-left-alt"
                        style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 15px;"></span>
                    <p style="font-size: 16px; font-weight: 500;">
                        <?php echo Olama_School_Helpers::translate('Please select a grade from the sidebar to view analysis.'); ?>
                    </p>
                </div>
            <?php else:
                $current_grade = Olama_School_Grade::get_grade($selected_grade_id);
                $grade_max_plans = intval($current_grade->max_weekly_plans) * $num_weeks;
                ?>
                <div class="olama-card" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <div
                        style="background: #f8fafc; padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                                <?php printf(Olama_School_Helpers::translate('Analysis Report: %s'), esc_html($current_grade->grade_name)); ?>
                            </h3>
                        </div>
                        <div style="font-size: 13px; color: #64748b; font-weight: 500;">
                            <span class="dashicons dashicons-calendar-alt"
                                style="font-size: 16px; width: 16px; height: 16px; margin-inline-end: 5px;"></span>
                            <?php echo esc_html($current_semester->semester_name); ?>
                            <?php if ($selected_week && isset($semester_weeks[$selected_week])): ?>
                                <span style="margin-left:5px; color:#2271b1;">
                                    (<?php echo sprintf(Olama_School_Helpers::translate('Week %d'), $semester_weeks[$selected_week]['number']); ?>)
                                </span>
                            <?php else: ?>
                                (<?php echo Olama_School_Helpers::format_date($effective_start); ?> -
                                <?php echo Olama_School_Helpers::format_date($effective_end); ?>)
                            <?php endif; ?>
                        </div>
                    </div>

                    <table class="wp-list-table widefat striped"
                        style="border: none; box-shadow: none; table-layout: auto;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th
                                    style="padding: 15px 20px; font-weight: 700; color: #475569; text-align: left; min-width: 200px;">
                                    <?php echo Olama_School_Helpers::translate('Subject Name'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 120px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Subject Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 120px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Plan Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 150px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Curriculum Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; text-align: center; width: 110px;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($subjects):
                                $total_plans_count = 0;
                                $total_plans_across_subjects = 0;
                                $total_subject_limits_sum = 0;
                                ?>
                                <?php foreach ($subjects as $subject):
                                    // 1. Get ALL lessons for this subject/semester curriculum
                                    $curriculum_lessons = $wpdb->get_results($wpdb->prepare(
                                        "SELECT l.id FROM {$wpdb->prefix}olama_curriculum_lessons l 
                                         JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id 
                                         WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d",
                                        $subject->id,
                                        $selected_grade_id,
                                        $selected_semester_id
                                    ));
                                    $total_lessons = count($curriculum_lessons);

                                    // 2. Get ALL plans for this section/subject in the range
                                    $plans = $wpdb->get_results($wpdb->prepare(
                                        "SELECT p.plan_date, p.lesson_id FROM {$wpdb->prefix}olama_plans p
                                         WHERE p.subject_id = %d AND p.section_id = %d 
                                         AND p.plan_date >= %s AND p.plan_date <= %s",
                                        $subject->id,
                                        $selected_section_id,
                                        $effective_start,
                                        $effective_end
                                    ));
                                    $plans_count = count($plans);
                                    $total_plans_count += $plans_count;

                                    // 3. Plan Coverage (vs Grade Max)
                                    $plan_cov_pct = $grade_max_plans > 0 ? min(100, round(($plans_count / $grade_max_plans) * 100, 1)) : 0;

                                    // 4. Subject Coverage (vs Subject Limit)
                                    $subject_limit = intval($subject->max_weekly_plans) * $num_weeks;
                                    $subj_cov_pct = $subject_limit > 0 ? min(100, round(($plans_count / $subject_limit) * 100, 1)) : 0;

                                    $total_plans_across_subjects += $plans_count;
                                    $total_subject_limits_sum += $subject_limit;

                                    // 5. Curriculum Coverage (Lessons)
                                    $plans_by_lesson = array_fill_keys(array_column($curriculum_lessons, 'id'), 0);
                                    foreach ($plans as $p) {
                                        if (isset($plans_by_lesson[$p->lesson_id]))
                                            $plans_by_lesson[$p->lesson_id]++;
                                    }
                                    $covered_lessons = count(array_filter($plans_by_lesson));
                                    $curriculum_cov_pct = $total_lessons > 0 ? min(100, round(($covered_lessons / $total_lessons) * 100, 1)) : 0;

                                    // Status logic compares Plan Coverage (actual) with Curriculum Coverage (optimal)
                                    $plan_cov_fraction = $grade_max_plans > 0 ? ($plans_count / $grade_max_plans) : 0;
                                    $curr_cov_fraction = $total_lessons > 0 ? ($covered_lessons / $total_lessons) : 0; // This is a bit tricky, user might mean lesson weight in grade
                        
                                    // Better Curriculum Optimal Weight: lessons for this sub / total lessons in grade
                                    // Need to sum all lessons for all subjects in this grade/semester
                                    static $total_grade_lessons = null;
                                    if ($total_grade_lessons === null) {
                                        $total_grade_lessons = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(l.id) FROM {$wpdb->prefix}olama_curriculum_lessons l
                                             JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
                                             WHERE u.grade_id = %d AND u.semester_id = %d",
                                            $selected_grade_id,
                                            $selected_semester_id
                                        ));
                                    }

                                    $plan_coverage_pct = $plan_cov_pct;
                                    $curriculum_weight_pct = $total_grade_lessons > 0 ? round(($total_lessons / $total_grade_lessons) * 100, 1) : 0;

                                    $diff = $plan_coverage_pct - $curriculum_weight_pct;

                                    $status_label = Olama_School_Helpers::translate('Optimal');
                                    $status_class = 'status-ontime';
                                    $status_color = '#10b981';
                                    $status_icon = 'dashicons-yes';
                                    $bg_color = 'rgba(16, 185, 129, 0.1)';

                                    if ($diff > 3) {
                                        $status_label = Olama_School_Helpers::translate('High');
                                        $status_color = '#f59e0b'; // Amber
                                        $status_icon = 'dashicons-arrow-up-alt';
                                        $bg_color = 'rgba(245, 158, 11, 0.1)';
                                    } elseif ($diff < -3) {
                                        $status_label = Olama_School_Helpers::translate('Low');
                                        $status_color = '#ef4444'; // Red
                                        $status_icon = 'dashicons-arrow-down-alt';
                                        $bg_color = 'rgba(239, 68, 68, 0.1)';
                                    }
                                    ?>
                                    <tr>
                                        <td style="padding: 15px 20px; text-align: left;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span class="dashicons dashicons-book"
                                                    style="color: <?php echo esc_attr($subject->color_code); ?>;"></span>
                                                <span
                                                    style="font-weight: 600; color: #1e293b;"><?php echo esc_html($subject->subject_name); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <?php
                                            $sc_color = '#1e293b'; // Default
                                            if ($subj_cov_pct >= 80)
                                                $sc_color = '#10b981'; // Green
                                            elseif ($subj_cov_pct < 50)
                                                $sc_color = '#ef4444'; // Red
                                            ?>
                                            <div style="font-weight: 700; color: <?php echo $sc_color; ?>;">
                                                <?php echo $subj_cov_pct; ?>%
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo $plans_count . ' / ' . $subject_limit; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo $plan_cov_pct; ?>%</div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo $plans_count . ' / ' . $grade_max_plans; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo $curriculum_weight_pct; ?>%
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php printf(Olama_School_Helpers::translate('%d / %d Lessons'), $total_lessons, $total_grade_lessons); ?>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <?php
                                            $status_color = '#10b981'; // Green
                                            $status_icon = 'dashicons-yes';
                                            $status_label = Olama_School_Helpers::translate('Optimal');
                                            $bg_color = 'rgba(16, 185, 129, 0.1)';

                                            if ($subj_cov_pct >= 100) {
                                                // Optimal
                                            } elseif ($subj_cov_pct >= 80) {
                                                $status_color = '#f59e0b'; // Amber
                                                $status_icon = 'dashicons-arrow-up-alt';
                                                $status_label = Olama_School_Helpers::translate('High');
                                                $bg_color = 'rgba(245, 158, 11, 0.1)';
                                            } else {
                                                $status_color = '#ef4444'; // Red
                                                $status_icon = 'dashicons-arrow-down-alt';
                                                $status_label = Olama_School_Helpers::translate('Low');
                                                $bg_color = 'rgba(239, 68, 68, 0.1)';
                                            }
                                            ?>
                                            <div
                                                style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 14px; border-radius: 20px; background: <?php echo $bg_color; ?>; color: <?php echo $status_color; ?>; font-weight: 700; font-size: 0.85rem; border: 1px solid <?php echo $status_color; ?>30; min-width: 90px;">
                                                <span class="dashicons <?php echo $status_icon; ?>"
                                                    style="font-size: 16px; width: 16px; height: 16px;"></span>
                                                <?php echo $status_label; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Total Row -->
                                <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                                    <td style="padding: 20px; font-weight: 800; color: #1e293b;">
                                        <?php echo Olama_School_Helpers::translate('Total Grade Coverage'); ?>
                                    </td>
                                    <td style="padding: 20px; text-align: center;">
                                        <?php
                                        $total_sc_pct = $total_subject_limits_sum > 0 ? round(($total_plans_across_subjects / $total_subject_limits_sum) * 100, 1) : 0;
                                        ?>
                                        <div style="font-weight: 800; color: #1e293b;"><?php echo $total_sc_pct; ?>%</div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo $total_plans_across_subjects . ' / ' . $total_subject_limits_sum; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 20px; text-align: center;">
                                        <div style="font-weight: 800; color: #1e293b;">
                                            <?php
                                            $total_pt_pct = $grade_max_plans > 0 ? round(($total_plans_count / $grade_max_plans) * 100, 1) : 0;
                                            echo $total_pt_pct;
                                            ?>%
                                        </div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo $total_plans_count . ' / ' . $grade_max_plans; ?>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td style="padding: 20px; text-align: center;">
                                        <?php
                                        // Global status for footer usually reflects the overall plan completeness vs target
                                        // Comparing total plans vs grade max
                                        $total_pct_val = $grade_max_plans > 0 ? ($total_plans_count / $grade_max_plans) * 100 : 0;

                                        $status_color = '#10b981'; // Green
                                        $status_icon = 'dashicons-yes';
                                        $status_label = Olama_School_Helpers::translate('Optimal');
                                        $bg_color = 'rgba(16, 185, 129, 0.1)';

                                        if ($total_pct_val >= 95) {
                                            // Optimal
                                        } elseif ($total_pct_val >= 80) {
                                            $status_color = '#f59e0b'; // Amber
                                            $status_icon = 'dashicons-arrow-up-alt';
                                            $status_label = Olama_School_Helpers::translate('High');
                                            $bg_color = 'rgba(245, 158, 11, 0.1)';
                                        } else {
                                            $status_color = '#ef4444'; // Red
                                            $status_icon = 'dashicons-arrow-down-alt';
                                            $status_label = Olama_School_Helpers::translate('Low');
                                            $bg_color = 'rgba(239, 68, 68, 0.1)';
                                        }
                                        ?>
                                        <div
                                            style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 18px; border-radius: 20px; background: <?php echo $bg_color; ?>; color: <?php echo $status_color; ?>; font-weight: 800; font-size: 0.9rem; border: 1px solid <?php echo $status_color; ?>30; min-width: 100px;">
                                            <span class="dashicons <?php echo $status_icon; ?>"
                                                style="font-size: 18px; width: 18px; height: 18px;"></span>
                                            <?php echo $status_label; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="padding: 40px; text-align: center; color: #94a3b8;">
                                        <span class="dashicons dashicons-warning"
                                            style="font-size: 30px; width: 30px; height: 30px; margin-bottom: 10px;"></span>
                                        <p><?php echo Olama_School_Helpers::translate('No subjects found for this grade.'); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Info Block -->
                    <div style="background: #eff6ff; padding: 25px; border-top: 1px solid #dbeafe;">
                        <h4
                            style="margin: 0 0 15px 0; color: #1e40af; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-info" style="color: #3b82f6;"></span>
                            <?php echo Olama_School_Helpers::translate('Understanding the columns'); ?>
                        </h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <p style="margin: 0 0 8px 0; color: #1e40af;">
                                    <strong><?php echo Olama_School_Helpers::translate('Plan Coverage'); ?></strong>
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #4b66b9; line-height: 1.5;">
                                    <?php echo Olama_School_Helpers::translate('Shows the percentage of coverage of the required total number of plans (how many did you cover out of the required number per week compared to all subjects).'); ?>
                                </p>
                            </div>
                            <div>
                                <p style="margin: 0 0 8px 0; color: #1e40af;">
                                    <strong><?php echo Olama_School_Helpers::translate('Subject Coverage'); ?></strong>
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #4b66b9; line-height: 1.5;">
                                    <?php echo Olama_School_Helpers::translate('Shows the percentage of coverage of the required subject total number of plans (how many did you cover out of the required number per subject during the week).'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    if (typeof add_query_arg !== 'function') {
        function add_query_arg(key, value) {
            var url = new URL(window.location.href);
            url.searchParams.set(key, value);
            return url.href;
        }
    }
</script>

<style>
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        min-width: 80px;
    }

    .status-ontime {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #d1fae5;
    }

    .status-high {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fef3c7;
    }

    .status-delayed {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fee2e2;
    }

    .status-bypass {
        background: #eff6ff;
        color: #1e40af;
        border: 1px solid #dbeafe;
    }
</style>