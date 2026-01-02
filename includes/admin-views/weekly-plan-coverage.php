<?php
/**
 * Weekly Plan Curriculum Coverage View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;

// 1. Get Academic Infrastructure
$active_year = Olama_School_Academic::get_active_year();
if (!$active_year) {
    echo '<div class="error"><p>' . __('Please activate an academic year first.', 'olama-school') . '</p></div>';
    return;
}

$semesters = Olama_School_Academic::get_semesters($active_year->id);
if (!$semesters) {
    echo '<div class="error"><p>' . __('Please create semesters for the active year.', 'olama-school') . '</p></div>';
    return;
}

// 2. Selection Handling
$selected_semester_id = isset($_GET['coverage_semester']) ? intval($_GET['coverage_semester']) : (isset($semesters[0]->id) ? intval($semesters[0]->id) : 0);
$selected_grade_id = isset($_GET['coverage_grade']) ? intval($_GET['coverage_grade']) : 0;

$sections = $selected_grade_id ? Olama_School_Section::get_by_grade($selected_grade_id) : [];
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

$grades = Olama_School_Grade::get_grades();
$subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id) : [];
?>

<div class="olama-coverage-container"
    style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="margin: 0; color: #1e293b; font-size: 24px; font-weight: 700;">
                <span class="dashicons dashicons-analytics"
                    style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1;"></span>
                <?php _e('Homework Curriculum Coverage', 'olama-school'); ?>
            </h1>
            <p class="description" style="font-size: 14px; margin-top: 5px;">
                <?php _e('Track how much of the curriculum is covered by weekly plans and monitor performance trends.', 'olama-school'); ?>
            </p>
        </div>

        <div
            style="display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <?php if ($selected_grade_id && !empty($sections)): ?>
                <div
                    style="display: flex; align-items: center; gap: 10px; border-inline-end: 1px solid #e2e8f0; padding-inline-end: 15px; margin-inline-end: 5px;">
                    <label style="font-weight: 600; color: #64748b;"><?php _e('Section:', 'olama-school'); ?></label>
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

            <label style="font-weight: 600; color: #64748b;"><?php _e('Semester:', 'olama-school'); ?></label>
            <select onchange="window.location.href=add_query_arg('coverage_semester', this.value)"
                style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?php echo $sem->id; ?>" <?php selected($selected_semester_id, $sem->id); ?>>
                        <?php echo esc_html($sem->semester_name); ?>
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
                <?php _e('Select Grade', 'olama-school'); ?>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($grades as $grade):
                    $is_active = (intval($grade->id) === $selected_grade_id);
                    $url = add_query_arg(array('coverage_grade' => $grade->id, 'coverage_section' => 0));
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

        <!-- Main Content: Subject Coverage -->
        <div style="flex-grow: 1;">
            <?php if (!$selected_grade_id): ?>
                <div
                    style="height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8;">
                    <span class="dashicons dashicons-arrow-left-alt"
                        style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 15px;"></span>
                    <p style="font-size: 16px; font-weight: 500;">
                        <?php _e('Please select a grade from the sidebar to view coverage analysis.', 'olama-school'); ?>
                    </p>
                </div>
            <?php else:
                $current_grade = Olama_School_Grade::get_grade($selected_grade_id);
                ?>
                <div class="olama-card" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                    <div
                        style="background: #f8fafc; padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                                <?php printf(__('Coverage Report: %s', 'olama-school'), esc_html($current_grade->grade_name)); ?>
                            </h3>
                        </div>
                        <div style="font-size: 13px; color: #64748b; font-weight: 500;">
                            <span class="dashicons dashicons-calendar-alt"
                                style="font-size: 16px; width: 16px; height: 16px; margin-inline-end: 5px;"></span>
                            <?php echo esc_html($current_semester->semester_name); ?>
                            (<?php echo date_i18n(get_option('date_format'), strtotime($current_semester->start_date)); ?> -
                            <?php echo date_i18n(get_option('date_format'), strtotime($current_semester->end_date)); ?>)
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                        <thead>
                            <tr style="background: #f1f5f9;">
                                <th style="padding: 15px 20px; font-weight: 700; color: #475569;">
                                    <?php _e('Subject', 'olama-school'); ?>
                                </th>
                                <th style="padding: 15px 20px; font-weight: 700; color: #475569; width: 220px;">
                                    <?php _e('Curriculum Coverage', 'olama-school'); ?>
                                </th>
                                <th
                                    style="padding: 15px 20px; font-weight: 700; color: #475569; text-align: center; width: 300px;">
                                    <?php _e('Performance Status', 'olama-school'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($subjects): ?>
                                <?php foreach ($subjects as $subject):
                                    // 1. Get ALL lessons for this subject/semester curriculum
                                    $curriculum_lessons = $wpdb->get_results($wpdb->prepare(
                                        "SELECT l.id, l.start_date, l.end_date 
                                         FROM {$wpdb->prefix}olama_curriculum_lessons l 
                                         JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id 
                                         WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d",
                                        $subject->id,
                                        $selected_grade_id,
                                        $selected_semester_id
                                    ));

                                    $total_lessons = count($curriculum_lessons);

                                    // 2. Get ALL plans for this section/subject in the semester
                                    $plans = $wpdb->get_results($wpdb->prepare(
                                        "SELECT p.plan_date, p.lesson_id 
                                         FROM {$wpdb->prefix}olama_plans p
                                         WHERE p.subject_id = %d AND p.section_id = %d 
                                         AND p.plan_date >= %s AND p.plan_date <= %s",
                                        $subject->id,
                                        $selected_section_id,
                                        $current_semester->start_date,
                                        $current_semester->end_date
                                    ));

                                    // 3. Map plans to lessons
                                    $plans_by_lesson = array();
                                    foreach ($plans as $plan) {
                                        if (!empty($plan->lesson_id)) {
                                            $lesson_id = intval($plan->lesson_id);
                                            if (!isset($plans_by_lesson[$lesson_id])) {
                                                $plans_by_lesson[$lesson_id] = array();
                                            }
                                            $plans_by_lesson[$lesson_id][] = $plan;
                                        }
                                    }

                                    // 4. Calculate coverage and status based on unique lessons
                                    $covered_lessons_count = 0;
                                    $tallies = ['ontime' => 0, 'delayed' => 0, 'bypass' => 0];

                                    foreach ($curriculum_lessons as $lesson) {
                                        $lesson_id = intval($lesson->id);

                                        if (isset($plans_by_lesson[$lesson_id])) {
                                            $covered_lessons_count++;

                                            if (!empty($lesson->start_date) && !empty($lesson->end_date)) {
                                                // Determine best status for this lesson
                                                $best_status_class = null;

                                                foreach ($plans_by_lesson[$lesson_id] as $plan) {
                                                    $status = $this->get_progress_status($plan->plan_date, $lesson->start_date, $lesson->end_date);
                                                    if ($status) {
                                                        $class = $status['class'];
                                                        // Priority: on-time > delayed > bypass
                                                        if ($class === 'status-ontime') {
                                                            $best_status_class = 'status-ontime';
                                                            break; // on-time is top priority
                                                        } elseif ($class === 'status-delayed') {
                                                            $best_status_class = 'status-delayed';
                                                        } elseif (!$best_status_class && $class === 'status-bypass') {
                                                            $best_status_class = 'status-bypass';
                                                        }
                                                    }
                                                }

                                                if ($best_status_class === 'status-ontime')
                                                    $tallies['ontime']++;
                                                elseif ($best_status_class === 'status-delayed')
                                                    $tallies['delayed']++;
                                                elseif ($best_status_class === 'status-bypass')
                                                    $tallies['bypass']++;
                                            }
                                        }
                                    }

                                    $percentage = $total_lessons > 0 ? min(100, round(($covered_lessons_count / $total_lessons) * 100)) : 0;
                                    ?>
                                    <tr>
                                        <td style="padding: 20px; vertical-align: middle;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span
                                                    style="width: 4px; height: 24px; border-radius: 2px; background: <?php echo esc_attr($subject->color_code); ?>;"></span>
                                                <span
                                                    style="font-weight: 600; font-size: 15px; color: #1e293b;"><?php echo esc_html($subject->subject_name); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 20px; vertical-align: middle;">
                                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                                <div
                                                    style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #64748b;">
                                                    <span><?php printf(__('%d / %d Lessons Covered', 'olama-school'), $covered_lessons_count, $total_lessons); ?></span>
                                                    <span style="color: #2271b1;"><?php echo $percentage; ?>%</span>
                                                </div>
                                                <div
                                                    style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                                    <div
                                                        style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, #2271b1, #3b82f6); border-radius: 4px; transition: width 0.6s ease-out;">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 20px; vertical-align: middle;">
                                            <div style="display: flex; justify-content: center; gap: 10px;">
                                                <div class="coverage-stat status-ontime"
                                                    title="<?php _e('On-time Plans', 'olama-school'); ?>">
                                                    <span class="count"><?php echo $tallies['ontime']; ?></span>
                                                    <span class="label"><?php _e('On-time', 'olama-school'); ?></span>
                                                </div>
                                                <div class="coverage-stat status-delayed"
                                                    title="<?php _e('Delayed Plans', 'olama-school'); ?>">
                                                    <span class="count"><?php echo $tallies['delayed']; ?></span>
                                                    <span class="label"><?php _e('Delayed', 'olama-school'); ?></span>
                                                </div>
                                                <div class="coverage-stat status-bypass"
                                                    title="<?php _e('Bypass Plans', 'olama-school'); ?>">
                                                    <span class="count"><?php echo $tallies['bypass']; ?></span>
                                                    <span class="label"><?php _e('Bypass', 'olama-school'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="padding: 40px; text-align: center; color: #94a3b8;">
                                        <span class="dashicons dashicons-warning"
                                            style="font-size: 30px; width: 30px; height: 30px; margin-bottom: 10px;"></span>
                                        <p style="font-size: 15px;">
                                            <?php _e('No subjects found for this grade.', 'olama-school'); ?>
                                        </p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    .coverage-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 80px;
        padding: 8px 5px;
        border-radius: 8px;
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .coverage-stat .count {
        font-size: 18px;
        font-weight: 700;
        line-height: 1.2;
    }

    .coverage-stat .label {
        font-size: 11px;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0.02em;
        opacity: 0.9;
    }

    .status-ontime {
        background: #ecfdf5;
        color: #065f46;
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.1);
    }

    .status-delayed {
        background: #fef2f2;
        color: #991b1b;
        box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.1);
    }

    .status-bypass {
        background: #eff6ff;
        color: #1e40af;
        box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.1);
    }
</style>