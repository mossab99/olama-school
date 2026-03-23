<?php
/**
 * Weekly Plan Curriculum Coverage View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// 1. Get Academic Infrastructure (locked to active year)
$active_year = Olama_School_Academic::get_active_year();

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

// 2. Selection Handling (locked to active semester)
$selected_semester_id = $active_semester ? intval($active_semester->id) : (isset($semesters[0]->id) ? intval($semesters[0]->id) : 0);
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
// Getting subjects with limits (Strictly Active Only)
Olama_School_Subject::clear_cache();
$subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id, true) : [];

// 3. Determine Effective Date Range for filtering plans (Sunday to Thursday alignment)
$semester_weeks = Olama_School_Academic::get_academic_weeks($active_year->id, $selected_semester_id, true);
$effective_start = $current_semester->start_date;
$effective_end = $current_semester->end_date;

// Week Filter Logic
$selected_week = isset($_GET['coverage_week']) ? sanitize_text_field($_GET['coverage_week']) : '';

// If no week selected, try to find the current academic week
if (empty($selected_week) && !empty($semester_weeks)) {
    $today = date('Y-m-d');
    foreach ($semester_weeks as $start => $data) {
        // Find if today is within this week's 7-day window (Sunday to Saturday)
        $week_start_ts = strtotime($start);
        $week_end_ts = $week_start_ts + (7 * 86400) - 1;
        if (strtotime($today) >= $week_start_ts && strtotime($today) <= $week_end_ts) {
            $selected_week = $start;
            break;
        }
    }
    // If today is not in any week (e.g. before semester), default to first week
    if (empty($selected_week)) {
        $week_dates = array_keys($semester_weeks);
        $selected_week = $week_dates[0];
    }
}

if (!empty($semester_weeks)) {
    if ($selected_week && isset($semester_weeks[$selected_week])) {
        // Filter by specific week
        $effective_start = $semester_weeks[$selected_week]['start'];
        $effective_end = $semester_weeks[$selected_week]['end'];
    } else {
        // Fallback or specific logic
        $effective_start = $current_semester->start_date;
        $effective_end = $current_semester->end_date;
    }
}

// 4. Analysis is strictly weekly
$num_weeks = 1;
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
            <div
                style="padding: 4px 10px; background: #e2e8f0; border-radius: 4px; font-weight: 600; color: #1e293b; cursor: not-allowed;">
                <?php echo esc_html($active_year->year_name); ?>
                <span
                    style="font-size: 0.8em; color: #10b981;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
            </div>

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
            <div
                style="padding: 4px 10px; background: #e2e8f0; border-radius: 4px; font-weight: 600; color: #1e293b; cursor: not-allowed;">
                <?php echo esc_html($active_semester ? Olama_School_Helpers::translate($active_semester->semester_name) : '—'); ?>
                <span
                    style="font-size: 0.8em; color: #10b981;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
            </div>

            <label
                style="font-weight: 600; color: #64748b; margin-left: 10px;"><?php echo Olama_School_Helpers::translate('Week:'); ?></label>
            <select onchange="window.location.href=add_query_arg('coverage_week', this.value)"
                style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
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
                            <?php echo esc_html(Olama_School_Helpers::translate($current_semester->semester_name)); ?>
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
                                    style="padding: 15px 20px; font-weight: 700; color: #475569; text-align: left; min-width: 180px;">
                                    <?php echo Olama_School_Helpers::translate('Subject Name'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 140px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Total number of plans and reviews'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 100px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Total number of all lesson'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 100px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Total number of covered lessons'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 140px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Current Lesson Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; width: 140px; text-align: center;">
                                    <?php echo Olama_School_Helpers::translate('Total Lesson Coverage'); ?>
                                </th>
                                <th
                                    style="padding: 15px 10px; font-weight: 700; color: #475569; text-align: center; width: 110px;">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($subjects):
                                $total_plans_global = 0;
                                $total_lessons_global = 0;
                                $total_covered_global = 0;
                                $total_expected_global = 0;
                                ?>
                                <?php foreach ($subjects as $subject):
                                    $analysis_start_date = $current_semester->start_date;

                                    // 1. Total number of plans (Approved from start to effective_end)
                                    $total_plans = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(id) FROM {$wpdb->prefix}olama_plans 
                                         WHERE subject_id = %d AND section_id = %d 
                                         AND (status = 'approved' OR plan_type = 'review')
                                         AND plan_date >= %s AND plan_date <= %s",
                                        $subject->id,
                                        $selected_section_id,
                                        $analysis_start_date,
                                        $effective_end
                                    ));                                     $total_plans = intval($total_plans);

                                     // 2. Total number of all lesson (Entire Semester)
                                     $total_lessons = $wpdb->get_var($wpdb->prepare(
                                         "SELECT COUNT(l.id) FROM {$wpdb->prefix}olama_curriculum_lessons l
                                          INNER JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
                                          WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d",
                                         $subject->id, $selected_grade_id, $selected_semester_id
                                     ));
                                     $total_lessons = intval($total_lessons);

                                     // Skip non-academic or unmonitored subjects (those with zero lessons planned and marked inactive)
                                     // Special check: even if active, if it has 0 lessons for the whole semester, it's not an academic target
                                     if ($total_lessons === 0 && (isset($_GET['hide_empty']) || true)) {
                                         // If a subject has NO lessons in curriculum, it shouldn't be in this report
                                         // unless it has plans (which shouldn't happen if no curriculum exists)
                                         $has_any_plans = $wpdb->get_var($wpdb->prepare(
                                             "SELECT COUNT(id) FROM {$wpdb->prefix}olama_plans WHERE subject_id = %d AND section_id = %d AND (status = 'approved' OR plan_type = 'review') LIMIT 1",
                                             $subject->id, $selected_section_id
                                         ));
                                         if (!$has_any_plans) {
                                             continue;
                                         }
                                     }

                                     $total_plans_global += $total_plans;
                                     $total_lessons_global += $total_lessons;



                                    // 3. Total number of covered lessons
                                    $covered_lessons = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(DISTINCT lesson_id) FROM {$wpdb->prefix}olama_plans 
                                         WHERE subject_id = %d AND section_id = %d 
                                         AND (status = 'approved' OR plan_type = 'review') 
                                         AND lesson_id IS NOT NULL AND lesson_id > 0
                                         AND plan_date >= %s AND plan_date <= %s",
                                        $subject->id,
                                        $selected_section_id,
                                        $analysis_start_date,
                                        $effective_end
                                    ));
                                    $covered_lessons = intval($covered_lessons);
                                    $total_covered_global += $covered_lessons;
                                    // 4. Expected lessons up to the selected week (Timeline bound)
                                    $expected_lessons = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(l.id) FROM {$wpdb->prefix}olama_curriculum_lessons l
                                         INNER JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id
                                         WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d
                                         AND l.end_date <= %s AND l.end_date IS NOT NULL",
                                        $subject->id, $selected_grade_id, $selected_semester_id, $effective_end
                                    ));
                                    $expected_lessons = intval($expected_lessons);
                                    $total_expected_global += $expected_lessons;

                                    // 5. Coverage Calcs
                                    // Total coverage: covered / total semester lessons
                                    $total_cov_pct = $total_lessons > 0 ? min(100, round(($covered_lessons / $total_lessons) * 100, 1)) : 0;
                                    
                                    // Current coverage: covered / expected by timeline
                                    $current_cov_pct = $expected_lessons > 0 ? round(($covered_lessons / $expected_lessons) * 100, 1) : 0;

                                    // Status based on Current (Timeline) Lesson Coverage
                                    $status_label = Olama_School_Helpers::translate('Optimal');
                                    $status_color = '#10b981';
                                    $status_icon = 'dashicons-yes';
                                    $bg_color = 'rgba(16, 185, 129, 0.1)';

                                    if ($expected_lessons == 0) {
                                        $status_label = Olama_School_Helpers::translate('N/A');
                                        $status_color = '#94a3b8';
                                        $status_icon = 'dashicons-minus';
                                        $bg_color = 'rgba(148, 163, 184, 0.1)';
                                    } elseif ($current_cov_pct >= 95) {
                                        // Optimal
                                    } elseif ($current_cov_pct >= 80) {
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
                                                    style="color: <?php echo esc_attr($subject->color_code); ?>;"></span>
                                                <span
                                                    style="font-weight: 600; color: #1e293b;"><?php echo esc_html($subject->subject_name); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                            <?php echo $total_plans; ?>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                            <?php echo $total_lessons; ?>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center; font-weight: 600;">
                                            <?php echo $covered_lessons; ?>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo $current_cov_pct; ?>%</div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo $covered_lessons . ' / ' . $expected_lessons; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 15px 10px; text-align: center;">
                                            <div style="font-weight: 700; color: #1e293b;"><?php echo $total_cov_pct; ?>%</div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo $covered_lessons . ' / ' . $total_lessons; ?>
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

                                <!-- Total Row -->
                                <tr style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                                    <td style="padding: 20px; font-weight: 800; color: #1e293b;">
                                        <?php echo Olama_School_Helpers::translate('Total Grade Coverage'); ?>
                                    </td>
                                    <td style="padding: 20px; text-align: center; font-weight: 800;">
                                        <?php echo $total_plans_global; ?>
                                    </td>
                                    <td style="padding: 20px; text-align: center; font-weight: 800;">
                                        <?php echo $total_lessons_global; ?>
                                    </td>                                    <td style="padding: 20px; text-align: center; font-weight: 800;">
                                        <?php echo $total_covered_global; ?>
                                    </td>
                                    <td style="padding: 20px; text-align: center;">
                                        <?php $global_current_pct = $total_expected_global > 0 ? round(($total_covered_global / $total_expected_global) * 100, 1) : 0; ?>
                                        <div style="font-weight: 800; color: #1e293b;"><?php echo $global_current_pct; ?>%</div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo $total_covered_global . ' / ' . $total_expected_global; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 20px; text-align: center;">
                                        <?php $global_total_pct = $total_lessons_global > 0 ? round(($total_covered_global / $total_lessons_global) * 100, 1) : 0; ?>
                                        <div style="font-weight: 800; color: #1e293b;"><?php echo $global_total_pct; ?>%</div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo $total_covered_global . ' / ' . $total_lessons_global; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 20px; text-align: center;">
                                        <?php
                                        $status_color = '#10b981';
                                        $status_icon = 'dashicons-yes';
                                        $status_label = Olama_School_Helpers::translate('Optimal');
                                        $bg_color = 'rgba(16, 185, 129, 0.1)';

                                        if ($total_expected_global == 0) {
                                            $status_label = Olama_School_Helpers::translate('N/A');
                                            $status_color = '#94a3b8';
                                            $status_icon = 'dashicons-minus';
                                            $bg_color = 'rgba(148, 163, 184, 0.1)';
                                        } elseif ($global_current_pct >= 95) {
                                            // Optimal
                                        } elseif ($global_current_pct >= 80) {
                                            $status_color = '#f59e0b';
                                            $status_icon = 'dashicons-arrow-up-alt';
                                            $status_label = Olama_School_Helpers::translate('High');
                                            $bg_color = 'rgba(245, 158, 11, 0.1)';
                                        } else {
                                            $status_color = '#ef4444';
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
                                    <td colspan="7" style="padding: 40px; text-align: center; color: #94a3b8;">
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
                                    <strong><?php echo Olama_School_Helpers::translate('Current Lesson Coverage'); ?></strong>
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #4b66b9; line-height: 1.5;">
                                    <?php echo Olama_School_Helpers::translate('Percentage of uniquely covered lessons up to the selected week compared to the planned lessons on the curriculum timeline.'); ?>
                                </p>
                            </div>
                            <div>
                                <p style="margin: 0 0 8px 0; color: #1e40af;">
                                    <strong><?php echo Olama_School_Helpers::translate('Total Lesson Coverage'); ?></strong>
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #4b66b9; line-height: 1.5;">
                                    <?php echo Olama_School_Helpers::translate('Percentage of uniquely covered lessons up to the selected week compared to the total curriculum lessons for the entire semester.'); ?>
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