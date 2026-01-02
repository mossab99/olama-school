<?php
/**
 * Weekly Plan Creation View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();

if (!current_user_can('manage_options')) {
    $user_id = get_current_user_id();
    global $wpdb;
    $assigned_grade_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT grade_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d",
        $user_id
    ));
    $grades = array_filter($grades, function ($g) use ($assigned_grade_ids) {
        return in_array($g->id, $assigned_grade_ids);
    });
    $grades = array_values($grades);
}

if (!$grades) {
    echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
    return;
}

$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
$sections = Olama_School_Section::get_by_grade($selected_grade_id);

if (!current_user_can('manage_options')) {
    $user_id = get_current_user_id();
    global $wpdb;
    $assigned_section_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d AND grade_id = %d",
        $user_id,
        $selected_grade_id
    ));
    $sections = array_filter($sections, function ($s) use ($assigned_section_ids) {
        return in_array($s->id, $assigned_section_ids);
    });
    $sections = array_values($sections);
}

$selected_section_id = 0;
if (!empty($sections)) {
    $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);
}

$all_weeks = Olama_School_Academic::get_academic_weeks();
$months_weeks = array();
foreach ($all_weeks as $val => $label) {
    $m_key = date('Y-m', strtotime($val));
    $months_weeks[$m_key][] = array('val' => $val, 'label' => $label);
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

$active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : 'Sunday';
$days = array(
    'Sunday' => date('Y-m-d', strtotime($week_start)),
    'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
    'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
    'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
    'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
);
$selected_date = $days[$active_day];

$all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));
?>

<div class="olama-plan-creation-container">
    <!-- Section 1: Top Navigation & Filters -->
    <div class="olama-card"
        style="margin-bottom: 25px; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <form method="get" id="olama-plan-filters"
            style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="olama-school-plans" />
            <input type="hidden" name="tab" value="creation" />
            <input type="hidden" name="active_day" id="active_day_input" value="<?php echo esc_attr($active_day); ?>" />

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Month', 'olama-school'); ?>
                </label>
                <select name="plan_month" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Week', 'olama-school'); ?>
                </label>
                <select name="week_start" class="olama-select" onchange="this.form.submit()">
                    <?php
                    $w_count = 1;
                    foreach ($current_month_weeks as $w): ?>
                        <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                            <?php echo "Week $w_count (" . esc_html($w['label']) . ")"; ?>
                        </option>
                        <?php $w_count++; endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Grade', 'olama-school'); ?>
                </label>
                <select name="grade_id" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Section', 'olama-school'); ?>
                </label>
                <select name="section_id" class="olama-select" onchange="this.form.submit()">
                    <?php if ($sections): ?>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                                <?php echo esc_html($section->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="0">
                            <?php _e('No sections found', 'olama-school'); ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Section 2: Days Tabs -->
    <div class="olama-tabs-wrapper" style="margin-bottom: 20px;">
        <ul class="olama-tabs"
            style="display: flex; list-style: none; margin: 0; padding: 0; border-bottom: 1px solid #ddd;">
            <?php foreach ($days as $day_name => $date): ?>
                <li class="olama-tab <?php echo $active_day === $day_name ? 'active' : ''; ?>"
                    style="padding: 10px 20px; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; background: <?php echo $active_day === $day_name ? '#fff' : '#f1f1f1'; ?>; <?php echo $active_day === $day_name ? 'border-color: #ddd; margin-bottom: -1px;' : ''; ?>"
                    onclick="document.getElementById('active_day_input').value='<?php echo $day_name; ?>'; document.getElementById('olama-plan-filters').submit();">
                    <strong>
                        <?php echo esc_html($day_name); ?>
                    </strong><br>
                    <small>
                        <?php echo date('M d', strtotime($date)); ?>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="olama-two-column" style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
        <!-- Left Column: Form -->
        <div class="olama-form-col"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h2 style="margin-top: 0; color: #1d2327;">
                <?php echo esc_html($active_day); ?>'s Plan -
                <?php echo date('Y-m-d', strtotime($selected_date)); ?>
            </h2>
            <form method="post" id="olama-weekly-plan-form">
                <?php wp_nonce_field('olama_save_plan', 'olama_plan_nonce'); ?>
                <input type="hidden" name="plan_id" id="olama-plan-id" value="0" />
                <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                <input type="hidden" name="plan_date" value="<?php echo $selected_date; ?>" />
                <input type="hidden" name="teacher_id" value="<?php echo get_current_user_id(); ?>" />
                <input type="hidden" name="period_number" value="0" />
                <input type="hidden" name="status" id="olama-plan-status" value="draft" />

                <div id="olama-edit-status-container"
                    style="display: none; margin-bottom: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span
                                style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase;">
                                <?php _e('Current Status', 'olama-school'); ?>:
                            </span>
                            <span id="olama-current-status-badge" style="margin-left: 8px;"></span>
                        </div>
                        <label
                            style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-weight: 500;">
                            <input type="checkbox" id="olama-revert-draft-check" />
                            <?php _e('Revert to Draft', 'olama-school'); ?>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Subject', 'olama-school'); ?>
                        </label>
                        <select name="subject_id" id="olama-subject-select" style="width: 100%; height: 40px;" required>
                            <option value="">
                                <?php _e('-- Select Subject --', 'olama-school'); ?>
                            </option>
                            <?php
                            $active_year = Olama_School_Academic::get_active_year();
                            $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : [];
                            $semester_id = $semesters[0]->id ?? 0;
                            $scheduled_subjects = Olama_School_Schedule::get_unique_subjects_for_day($selected_section_id, $active_day, $semester_id);

                            if (!current_user_can('manage_options')) {
                                $teacher_id = get_current_user_id();
                                $assigned_ids = Olama_School_Teacher::get_assigned_subjects($teacher_id, $selected_section_id);
                                $scheduled_subjects = array_filter($scheduled_subjects, function ($subj) use ($assigned_ids) {
                                    return in_array($subj->id, $assigned_ids);
                                });
                            }

                            foreach ($scheduled_subjects as $subj): ?>
                                <option value="<?php echo $subj->id; ?>">
                                    <?php echo esc_html($subj->subject_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Unit', 'olama-school'); ?>
                        </label>
                        <select name="unit_id" id="olama-unit-select" style="width: 100%; height: 40px;" disabled>
                            <option value="">
                                <?php _e('-- Select Unit --', 'olama-school'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Lesson', 'olama-school'); ?>
                        </label>
                        <select name="lesson_id" id="olama-lesson-select" style="width: 100%; height: 40px;" disabled>
                            <option value="">
                                <?php _e('-- Select Lesson --', 'olama-school'); ?>
                            </option>
                        </select>
                        <div id="olama-lesson-progress-check"
                            style="margin-top: 10px; display: none; text-align: center;"></div>
                    </div>
                </div>

                <div id="olama-questions-area" style="margin-bottom: 20px; display: none;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <?php _e('Questions to Cover', 'olama-school'); ?>
                    </label>
                    <div id="olama-questions-list"
                        style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto;">
                        <!-- AJAX populated -->
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Homework (Student Book)', 'olama-school'); ?>
                        </label>
                        <textarea name="homework_sb" rows="3" style="width: 100%;"
                            placeholder="Page numbers or details..."></textarea>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Homework (Exercise Book)', 'olama-school'); ?>
                        </label>
                        <textarea name="homework_eb" rows="3" style="width: 100%;"
                            placeholder="Page numbers or details..."></textarea>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Homework (Notebook)', 'olama-school'); ?>
                        </label>
                        <textarea name="homework_nb" rows="3" style="width: 100%;"
                            placeholder="Notebook instructions..."></textarea>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php _e('Homework (Worksheet)', 'olama-school'); ?>
                        </label>
                        <textarea name="homework_ws" rows="3" style="width: 100%;"
                            placeholder="Worksheet details..."></textarea>
                    </div>
                </div>

                <div class="olama-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <?php _e('Teacher\'s Notes', 'olama-school'); ?>
                    </label>
                    <textarea name="teacher_notes" rows="3" style="width: 100%;"
                        placeholder="Additional notes..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 30px;">
                    <button type="button" id="olama-cancel-edit-btn" class="button button-large"
                        style="margin-right: 15px; display: none; height: 46px; font-weight: 600;">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                    <input type="submit" name="save_plan" id="olama-save-plan-btn"
                        class="button button-primary button-large"
                        style="height: 46px; padding: 0 30px; font-weight: 600;"
                        value="<?php _e('Save as Draft', 'olama-school'); ?>" />
                </div>
            </form>
        </div>

        <!-- Right Column: Today's Summary -->
        <div class="olama-list-col"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h2 style="margin-top: 0; color: #1d2327;">
                <?php _e('Saved Plans for Today', 'olama-school'); ?>
            </h2>
            <?php
            $today_plans = array_filter($all_plans, function ($p) use ($selected_date) {
                return $p->plan_date === $selected_date;
            });
            if ($today_plans): ?>
                <?php foreach ($today_plans as $plan):
                    $q_ids = Olama_School_Plan::get_plan_questions($plan->id);
                    $plan_json = wp_json_encode([
                        'id' => $plan->id,
                        'grade_id' => $selected_grade_id,
                        'section_id' => $selected_section_id,
                        'subject_id' => $plan->subject_id,
                        'unit_id' => $plan->unit_id,
                        'lesson_id' => $plan->lesson_id,
                        'homework_sb' => $plan->homework_sb,
                        'homework_eb' => $plan->homework_eb,
                        'homework_nb' => $plan->homework_nb,
                        'homework_ws' => $plan->homework_ws,
                        'teacher_notes' => $plan->teacher_notes,
                        'question_ids' => $q_ids,
                        'status' => $plan->status
                    ]);
                    ?>
                    <div class="olama-plan-item" data-plan="<?php echo esc_attr($plan_json); ?>"
                        style="border-left: 4px solid <?php echo esc_attr($plan->color_code); ?>; padding: 15px; margin-bottom: 15px; background: #fcfcfc; border-radius: 0 8px 8px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="font-size: 1.1em; color: <?php echo esc_attr($plan->color_code); ?>;">
                                <?php echo esc_html($plan->subject_name); ?>
                            </strong>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span class="status-badge <?php echo esc_attr($plan->status); ?>"
                                    style="font-size: 0.8em; padding: 2px 8px; border-radius: 12px; background: #eee; color: #666;">
                                    <?php echo ucfirst($plan->status); ?>
                                </span>
                                <a href="#" class="olama-edit-plan" title="<?php _e('Edit', 'olama-school'); ?>"
                                    style="color: #666; text-decoration: none;"><i class="dashicons dashicons-edit"></i></a>
                                <a href="#" class="olama-delete-plan" title="<?php _e('Delete', 'olama-school'); ?>"
                                    style="color: #d63638; text-decoration: none;"><i class="dashicons dashicons-trash"></i></a>
                            </div>
                        </div>
                        <div style="font-size: 0.95em; color: #444; margin-bottom: 5px;">
                            <?php echo esc_html($plan->unit_name); ?> -
                            <strong>
                                <?php echo esc_html($plan->lesson_title); ?>
                            </strong>
                        </div>
                        <?php if ($plan->homework_sb || $plan->homework_eb): ?>
                            <div style="font-size: 0.85em; color: #666;">
                                <i class="dashicons dashicons-book-alt" style="font-size: 16px; margin-right: 5px;"></i>
                                <?php echo $plan->homework_sb ? 'SB: ' . esc_html($plan->homework_sb) : ''; ?>
                                <?php echo $plan->homework_eb ? ' EB: ' . esc_html($plan->homework_eb) : ''; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div
                    style="text-align: center; color: #999; padding: 40px 20px; border: 2px dashed #eee; border-radius: 12px;">
                    <i class="dashicons dashicons-calendar-alt"
                        style="font-size: 40px; margin-bottom: 10px; width: 40px; height: 40px;"></i>
                    <p>
                        <?php _e('No plans saved for this day.', 'olama-school'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>