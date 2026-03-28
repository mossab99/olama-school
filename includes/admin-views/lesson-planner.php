<?php
/**
 * Lesson Planner View V2 — Pedagogical Engine
 * Stage Builder + Outcomes Builder + Compliance Scoring
 */
if (!defined('ABSPATH'))
    exit;

$current_user_id = get_current_user_id();
global $wpdb;
$is_admin = Olama_School_Permissions::can('olama_manage_evaluation_mgmt') || Olama_School_Permissions::can('olama_approve_plans');
$academic = new Olama_School_Academic();
$years = $academic->get_years();
$active_year = $academic->get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
$semesters = $academic->get_semesters($selected_year_id);
$active_semester = null;
foreach ($semesters as $s) {
    if ($s->is_active) {
        $active_semester = $s;
        break;
    }
}
$selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : 0);

$grade_obj = new Olama_School_Grade();
$all_grades = $grade_obj->get_grades();
$grades = Olama_School_Helpers::filter_by_assignment($all_grades, $current_user_id, 'grades');
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

$sections = array();
if ($selected_grade_id) {
    $sections = Olama_School_Section::get_by_grade($selected_grade_id, $selected_year_id);
    if (!$is_admin) {
        $sections = Olama_School_Helpers::filter_by_assignment($sections, $current_user_id, 'sections', $selected_grade_id);
    }
}
$subject_obj = new Olama_School_Subject();
$subjects = array();
if ($selected_grade_id && $selected_section_id) {
    if ($is_admin) {
        $subjects = $subject_obj->get_subjects();
    } else {
        $subjects = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT s.* FROM {$wpdb->prefix}olama_subjects s INNER JOIN {$wpdb->prefix}olama_teacher_assignments ta ON s.id = ta.subject_id WHERE ta.teacher_id = %d AND ta.grade_id = %d AND ta.section_id = %d ORDER BY s.subject_name",
            $current_user_id,
            $selected_grade_id,
            $selected_section_id
        ));
    }
}

$mode = isset($_GET['lp_action']) ? sanitize_text_field($_GET['lp_action']) : 'list';
$edit_plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;
$edit_plan = null;

if ($mode === 'edit' && $edit_plan_id) {
    $edit_plan = Olama_School_Lesson_Planner::get_plan($edit_plan_id, $is_admin ? 0 : $current_user_id);
    if ($edit_plan) {
        $selected_grade_id = $edit_plan->grade_id;
        $selected_section_id = $edit_plan->section_id;
        $selected_subject_id = $edit_plan->subject_id;
        $selected_year_id = $edit_plan->academic_year_id;
        $selected_semester_id = $edit_plan->semester_id;
        $sections = Olama_School_Section::get_by_grade($selected_grade_id, $selected_year_id);
        if (!$is_admin) {
            $sections = Olama_School_Helpers::filter_by_assignment($sections, $current_user_id, 'sections', $selected_grade_id);
        }
        if ($is_admin) {
            $subjects = $subject_obj->get_subjects();
        } else {
            $subjects = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT s.* FROM {$wpdb->prefix}olama_subjects s INNER JOIN {$wpdb->prefix}olama_teacher_assignments ta ON s.id = ta.subject_id WHERE ta.teacher_id = %d AND ta.grade_id = %d AND ta.section_id = %d ORDER BY s.subject_name",
                $current_user_id,
                $selected_grade_id,
                $selected_section_id
            ));
        }
    } else {
        $mode = 'list';
        echo '<div class="notice notice-error"><p>' . $t('Lesson plan not found or you do not have permission to access it.') . '</p></div>';
    }
}

// Retrieve error message if any (from admin_init handler)
if (isset($_GET['lp_err'])) {
    $error_message = get_transient('lp_error_' . get_current_user_id());
    delete_transient('lp_error_' . get_current_user_id());
} else {
    $error_message = '';
}

// Redirected to class-admin.php: handle_lesson_planner_actions()


// Get plans for list
$plans = array();
if ($mode === 'list') {
    $filters = array('academic_year_id' => $selected_year_id, 'semester_id' => $selected_semester_id);
    if (!$is_admin)
        $filters['teacher_id'] = $current_user_id;
    if ($selected_grade_id)
        $filters['grade_id'] = $selected_grade_id;
    if ($selected_section_id)
        $filters['section_id'] = $selected_section_id;
    if ($selected_subject_id)
        $filters['subject_id'] = $selected_subject_id;
    $plans = Olama_School_Lesson_Planner::get_plans($filters);
}

// Get units for form
$units = array();
if (($mode === 'create' || $mode === 'edit') && $selected_subject_id && $selected_grade_id) {
    $unit_obj = new Olama_School_Unit();
    $units = $unit_obj->get_units($selected_subject_id, $selected_grade_id, $selected_semester_id);
}

// Get config for dropdowns
$lp_config = Olama_Lesson_Planner_Config::get_js_config();
$lp_stages_config = Olama_Lesson_Planner_Config::get_stages();
$lp_teaching_strategies = Olama_Lesson_Planner_Config::get_teaching_strategies();
$lp_assessment_strategies = Olama_Lesson_Planner_Config::get_assessment_strategies();
$lp_assessment_tools = Olama_Lesson_Planner_Config::get_assessment_tools();
$is_arabic = Olama_School_Helpers::is_arabic();
$lang = $is_arabic ? 'ar' : 'en';

$t = function ($text) {
    return Olama_School_Helpers::translate($text);
};

// Decode edit plan JSON fields
$plan_outcomes = array();
$plan_stages = array();
if ($edit_plan) {
    $plan_outcomes = json_decode($edit_plan->learning_outcomes ?? '[]', true) ?: array();
    $plan_stages = json_decode($edit_plan->stages ?? '{}', true) ?: array();
}
?>

<div class="olama-lesson-planner-wrap">
    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible" style="margin-left:0;margin-right:0;">
            <p>
                <?php $msg = sanitize_text_field($_GET['message']);
                if ($msg === 'lp_saved')
                    echo $t('Lesson plan saved successfully.');
                elseif ($msg === 'lp_deleted')
                    echo $t('Lesson plan deleted.');
                else
                    echo $t($msg); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="notice notice-error is-dismissible" style="margin-left:0;margin-right:0;">
            <p>
                <strong><?php echo $t('Error saving lesson plan:'); ?></strong> <?php echo esc_html($error_message); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="olama-header-section"
        style="margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h2 style="margin:0;font-size:1.5em;"><?php echo $t('Lesson Planner'); ?></h2>
            <p class="description"><?php echo $t('Create and manage daily lesson plans.'); ?></p>
        </div>
        <?php if ($mode === 'list'): ?>
            <a href="<?php echo admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&lp_action=create'); ?>"
                class="button button-primary button-large" style="background:#6366f1;border-color:#6366f1;">
                <span class="dashicons dashicons-plus-alt2"
                    style="margin-top:4px;margin-right:3px;"></span><?php echo $t('Add New Lesson Plan'); ?>
            </a>
        <?php else: ?>
            <a href="<?php echo admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner'); ?>"
                class="button button-large">
                <span class="dashicons dashicons-arrow-left-alt"
                    style="margin-top:4px;margin-right:3px;"></span><?php echo $t('Back to List'); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($mode === 'list'): ?>
        <!-- ===== LIST MODE ===== -->
        <div class="olama-card"
            style="background:#fff;padding:20px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:25px;">
            <form method="get"><input type="hidden" name="page" value="olama-school-evaluation"><input type="hidden"
                    name="tab" value="lesson_planner">
                <div style="display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end;">
                    <?php
                    $y_name = $active_year ? $active_year->year_name : '';
                    echo Olama_School_Helpers::locked_filter_render(
                        $t('Academic Year'),
                        $t($y_name),
                        'academic_year_id',
                        $selected_year_id
                    );

                    $s_name = $active_semester ? $active_semester->semester_name : '';
                    echo Olama_School_Helpers::locked_filter_render(
                        $t('Semester'),
                        $t($s_name),
                        'semester_id',
                        $selected_semester_id
                    );
                    ?>
                    <div style="flex:1;min-width:140px;"><label class="olama-label"><?php echo $t('Grade'); ?></label>
                        <select name="grade_id" onchange="this.form.submit()" style="width:100%;">
                            <option value=""><?php echo $t('-- Select Grade --'); ?></option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                    <?php echo esc_html($t($g->grade_name)); ?>
                                </option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selected_grade_id): ?>
                        <div style="flex:1;min-width:140px;"><label class="olama-label"><?php echo $t('Section'); ?></label>
                            <select name="section_id" onchange="this.form.submit()" style="width:100%;">
                                <option value=""><?php echo $t('-- Select --'); ?></option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                        <?php echo esc_html($t($sec->section_name)); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="olama-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <?php if (empty($plans)): ?>
                <div style="padding:40px;text-align:center;color:#94a3b8;">
                    <span class="dashicons dashicons-welcome-write-blog"
                        style="font-size:48px;width:48px;height:48px;margin-bottom:15px;"></span>
                    <p style="font-size:1.1em;"><?php echo $t('No lesson plans found.'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&lp_action=create'); ?>"
                        class="button button-primary"
                        style="background:#6366f1;border-color:#6366f1;"><?php echo $t('Add New Lesson Plan'); ?></a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th><?php echo $t('Lesson Title'); ?></th>
                            <th><?php echo $t('Subject'); ?></th>
                            <th><?php echo $t('Grade'); ?> / <?php echo $t('Section'); ?></th>
                            <th><?php echo $t('Date'); ?></th>
                            <th style="width:100px;"><?php echo $t('Compliance'); ?></th>
                            <th style="width:80px;"><?php echo $t('Status'); ?></th>
                            <th style="width:120px;"><?php echo $t('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $idx => $plan):
                            $cs = intval($plan->compliance_score ?? 0);
                            $cs_color = $cs >= 80 ? '#10b981' : ($cs >= 50 ? '#f59e0b' : '#ef4444');
                            $cs_bg = $cs >= 80 ? '#dcfce7' : ($cs >= 50 ? '#fef3c7' : '#fef2f2');
                            ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><strong><?php echo esc_html($plan->lesson_title); ?></strong>
                                    <?php if ($plan->unit_name): ?><br><small
                                            style="color:#94a3b8;"><?php echo esc_html($plan->unit_name); ?></small><?php endif; ?></td>
                                <td><?php echo esc_html($t($plan->subject_name ?? '')); ?></td>
                                <td><?php echo esc_html($t($plan->grade_name ?? '')); ?> /
                                    <?php echo esc_html($t($plan->section_name ?? '')); ?>
                                </td>
                                <td><?php echo Olama_School_Helpers::format_date($plan->start_date); ?><?php if (!empty($plan->end_date) && $plan->end_date !== $plan->start_date): ?>
                                        – <?php echo Olama_School_Helpers::format_date($plan->end_date); ?><?php endif; ?></td>
                                <td><span
                                        style="background:<?php echo $cs_bg; ?>;color:<?php echo $cs_color; ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;"><?php echo $cs; ?>%</span>
                                </td>
                                <td><?php if ($plan->status === 'final'): ?><span
                                            style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?php echo $t('Final'); ?></span>
                                    <?php else: ?><span
                                            style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?php echo $t('Draft'); ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&lp_action=edit&plan_id=' . $plan->id); ?>"
                                        class="button button-small"><span class="dashicons dashicons-edit"
                                            style="font-size:16px;width:16px;height:16px;margin-top:3px;"></span></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-evaluation&tab=lesson_planner&lp_action=delete&plan_id=' . $plan->id), 'olama_lp_delete_' . $plan->id); ?>"
                                        class="button button-small" style="color:#dc2626;"
                                        onclick="return confirm('<?php echo esc_js($t('Are you sure you want to delete this lesson plan?')); ?>');"><span
                                            class="dashicons dashicons-trash"
                                            style="font-size:16px;width:16px;height:16px;margin-top:3px;"></span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- ===== CREATE/EDIT MODE ===== -->

        <!-- Compliance Badge (floating) -->
        <div id="lp-compliance-badge"
            style="position:fixed;top:40px;right:30px;z-index:9999;background:#fff;border:2px solid #e2e8f0;border-radius:16px;padding:12px 20px;box-shadow:0 10px 25px rgba(0,0,0,0.1);text-align:center;min-width:120px;">
            <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px;"><?php echo $t('Compliance'); ?>
            </div>
            <div id="lp-compliance-score" style="font-size:28px;font-weight:800;color:#ef4444;">0%</div>
            <div style="background:#e2e8f0;height:6px;border-radius:3px;margin-top:6px;overflow:hidden;">
                <div id="lp-compliance-bar"
                    style="height:100%;width:0%;border-radius:3px;transition:all 0.3s;background:#ef4444;"></div>
            </div>
            <div id="lp-compliance-details" style="display:none;margin-top:10px;text-align:left;font-size:11px;"></div>
            <button type="button" onclick="jQuery('#lp-compliance-details').toggle();"
                style="background:none;border:none;color:#94a3b8;font-size:10px;cursor:pointer;margin-top:4px;"><?php echo $t('Details'); ?>
                ▼</button>
        </div>

        <form method="post" action="" id="lesson-plan-form">
            <?php wp_nonce_field('olama_lesson_plan_nonce', 'olama_lesson_plan_nonce'); ?>
            <input type="hidden" name="olama_lesson_plan_save" value="1">
            <?php if ($edit_plan): ?><input type="hidden" name="plan_id"
                    value="<?php echo $edit_plan->id; ?>"><?php endif; ?>

            <!-- Section 1: Basic Info -->
            <div class="olama-card lp-section"
                style="background:#fff;padding:25px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
                <h3 class="lp-section-title"
                    style="margin-top:0;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">
                    <span class="dashicons dashicons-info-outline"
                        style="margin-right:8px;color:#6366f1;"></span><?php echo $t('Basic Information'); ?>
                </h3>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;">
                    <?php
                    $y_name = $active_year ? $active_year->year_name : '';
                    echo Olama_School_Helpers::locked_filter_render(
                        $t('Academic Year'),
                        $t($y_name),
                        'academic_year_id',
                        $selected_year_id
                    );

                    $s_name = $active_semester ? $active_semester->semester_name : '';
                    echo Olama_School_Helpers::locked_filter_render(
                        $t('Semester'),
                        $t($s_name),
                        'semester_id',
                        $selected_semester_id
                    );
                    ?>
                    <div><label class="olama-label"><?php echo $t('Grade'); ?></label>
                        <select name="grade_id" id="lp-grade" style="width:100%;" required>
                            <option value=""><?php echo $t('-- Select Grade --'); ?></option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                    <?php echo esc_html($t($g->grade_name)); ?>
                                </option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="olama-label"><?php echo $t('Section'); ?></label>
                        <select name="section_id" id="lp-section" style="width:100%;" required>
                            <option value=""><?php echo $t('-- Select --'); ?></option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                    <?php echo esc_html($t($sec->section_name)); ?>
                                </option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- Row 2: Subject, Unit, Lesson, Periods, Period Duration -->
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-top:15px;">
                    <div><label class="olama-label"><?php echo $t('Subject'); ?></label>
                        <select name="subject_id" id="lp-subject" style="width:100%;" required>
                            <option value=""><?php echo $t('-- Select Subject --'); ?></option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?php echo $sub->id; ?>" <?php selected($selected_subject_id, $sub->id); ?>>
                                    <?php echo esc_html($t($sub->subject_name)); ?>
                                </option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="olama-label"><?php echo $t('Unit'); ?></label>
                        <select name="unit_id" id="lp-unit" style="width:100%;">
                            <option value=""><?php echo $t('-- Select Unit --'); ?></option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?php echo $u->id; ?>" <?php selected($edit_plan ? $edit_plan->unit_id : 0, $u->id); ?>><?php echo esc_html($u->unit_name); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="olama-label"><?php echo $t('Lesson'); ?></label>
                        <select name="lesson_id" id="lp-lesson" style="width:100%;">
                            <option value=""><?php echo $t('-- Select Lesson --'); ?></option>
                        </select>
                    </div>
                    <div><label class="olama-label"><?php echo $t('Number of Classes'); ?></label>
                        <input type="number" name="number_of_classes" id="lp-num-classes" min="1" max="10"
                            value="<?php echo esc_attr($edit_plan ? $edit_plan->number_of_classes : 1); ?>"
                            style="width:100%;" readonly>
                    </div>
                    <div><label class="olama-label"><?php echo $t('Period Duration (min)'); ?></label>
                        <input type="number" name="period_duration" id="lp-period-duration" min="1" max="120"
                            value="<?php echo esc_attr($edit_plan && isset($edit_plan->period_duration) ? $edit_plan->period_duration : 45); ?>"
                            style="width:100%;">
                    </div>
                </div>
                <!-- Row 3: Lesson Title (full width) -->
                <div style="margin-top:15px;"><label class="olama-label"><?php echo $t('Lesson Title'); ?></label>
                    <input type="text" name="lesson_title" id="lp-lesson-title"
                        value="<?php echo esc_attr($edit_plan ? $edit_plan->lesson_title : ''); ?>"
                        placeholder="<?php echo esc_attr($t('Auto-filled from timeline')); ?>"
                        style="width:100%;font-size:1.1em;padding:8px 12px;" readonly>
                </div>
                <!-- Row 4: Start Date, End Date -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px;">
                    <div><label class="olama-label"><?php echo $t('Start Date'); ?></label>
                        <input type="date" name="start_date" id="lp-start-date"
                            value="<?php echo esc_attr($edit_plan ? $edit_plan->start_date : ''); ?>" style="width:100%;"
                            readonly>
                    </div>
                    <div><label class="olama-label"><?php echo $t('End Date'); ?></label>
                        <input type="date" name="end_date" id="lp-end-date"
                            value="<?php echo esc_attr($edit_plan ? $edit_plan->end_date : ''); ?>" style="width:100%;"
                            readonly>
                    </div>
                </div>
                <div style="margin-top:15px;"><label class="olama-label"><?php echo $t('Prior Learning'); ?></label>
                    <textarea name="prior_learning" rows="2" style="width:100%;"
                        placeholder="<?php echo esc_attr($t('What should students already know before this lesson?')); ?>"><?php echo esc_textarea($edit_plan ? $edit_plan->prior_learning : ''); ?></textarea>
                </div>
            </div>

            <!-- Section 2: Learning Outcomes Builder -->
            <div class="olama-card lp-section"
                style="background:#fff;padding:25px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
                <h3 class="lp-section-title"
                    style="margin-top:0;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">
                    <span class="dashicons dashicons-yes-alt"
                        style="margin-right:8px;color:#10b981;"></span><?php echo $t('Learning Outcomes'); ?>
                    <small style="color:#94a3b8;font-weight:400;font-size:12px;"> —
                        <?php echo $t('SMART: Verb + Content + Performance Level'); ?></small>
                </h3>
                <div id="lp-outcomes-container">
                    <?php
                    $outcomes_to_show = !empty($plan_outcomes) ? $plan_outcomes : array(array('verb' => '', 'content' => '', 'level' => ''));
                    foreach ($outcomes_to_show as $idx => $oc): ?>
                        <div class="lp-outcome-row"
                            style="display:flex;gap:10px;margin-bottom:10px;align-items:center;flex-wrap:wrap;">
                            <span class="lp-outcome-num"
                                style="background:#6366f1;color:#fff;min-width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;"><?php echo $idx + 1; ?></span>
                            <select name="outcome_verb[]" class="lp-outcome-verb" style="min-width:140px;">
                                <option value=""><?php echo $t('Select Verb'); ?></option>
                                <?php foreach (Olama_Lesson_Planner_Config::get_blooms_verbs() as $level_key => $level):
                                    $label = $level['label_' . $lang]; ?>
                                    <optgroup label="<?php echo esc_attr($label); ?>">
                                        <?php foreach ($level['verbs'] as $verb):
                                            $verb_text = $verb[$lang];
                                            $sel = (isset($oc['verb']) && $oc['verb'] === $verb_text) ? 'selected' : ''; ?>
                                            <option value="<?php echo esc_attr($verb_text); ?>" <?php echo $sel; ?>>
                                                <?php echo esc_html($verb_text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="outcome_content[]" value="<?php echo esc_attr($oc['content'] ?? ''); ?>"
                                placeholder="<?php echo esc_attr($t('Content / concept')); ?>" style="flex:2;padding:6px 10px;">
                            <input type="text" name="outcome_level[]" value="<?php echo esc_attr($oc['level'] ?? ''); ?>"
                                placeholder="<?php echo esc_attr($t('Performance level')); ?>" style="flex:1;padding:6px 10px;">
                            <button type="button" class="button lp-remove-outcome" style="color:#dc2626;"><span
                                    class="dashicons dashicons-no-alt" style="margin-top:3px;"></span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="lp-add-outcome" class="button" style="margin-top:5px;">
                    <span class="dashicons dashicons-plus"
                        style="margin-top:3px;margin-right:3px;"></span><?php echo $t('Add Outcome'); ?>
                </button>
            </div>

            <!-- Section 3: Stage Builder (Tabbed) -->
            <div class="olama-card lp-section"
                style="background:#fff;padding:25px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
                <h3 class="lp-section-title"
                    style="margin-top:0;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">
                    <span class="dashicons dashicons-editor-table"
                        style="margin-right:8px;color:#f59e0b;"></span><?php echo $t('Lesson Stages'); ?>
                    <span id="lp-time-indicator" style="float:right;font-size:13px;font-weight:600;color:#94a3b8;"></span>
                </h3>

                <!-- Stage Tabs -->
                <div class="lp-stage-tabs" style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px;">
                    <?php 
                    $stage_colors = array('#3b82f6', '#f59e0b', '#ec4899', '#10b981'); // Adjusted colors for 4 stages
                    $i = 0;
                    foreach ($lp_stages_config as $sk => $sdata): ?>
                        <button type="button" class="lp-stage-tab <?php echo $i === 0 ? 'active' : ''; ?>"
                            data-stage="<?php echo $sk; ?>"
                            style="padding:10px 18px;border:none;background:<?php echo $i === 0 ? '#f8fafc' : 'transparent'; ?>;cursor:pointer;font-weight:600;font-size:13px;color:<?php echo $i === 0 ? $stage_colors[$i] : '#64748b'; ?>;border-bottom:<?php echo $i === 0 ? '3px solid ' . $stage_colors[$i] : '3px solid transparent'; ?>;transition:all 0.2s;">
                            <?php echo $sdata['label_' . $lang]; ?>
                        </button>
                        <?php $i++; endforeach; ?>
                </div>

                <!-- Stage Content Panels -->
                <?php $i = 0;
                foreach ($lp_stages_config as $sk => $sdata):
                    $stage_data = $plan_stages[$sk] ?? array(); ?>
                    <div class="lp-stage-panel" data-stage="<?php echo $sk; ?>"
                        style="<?php echo $i > 0 ? 'display:none;' : ''; ?>">
                        <div
                            style="background:#f0f9ff;padding:12px 16px;border-radius:8px;margin-bottom:15px;border-left:4px solid <?php echo $stage_colors[$i]; ?>;">
                            <strong
                                style="color:<?php echo $stage_colors[$i]; ?>;"><?php echo $sdata['label_' . $lang]; ?></strong>
                            <p style="margin:5px 0 0;font-size:12px;color:#475569;">
                                <?php echo $sdata['description_' . $lang]; ?>
                            </p>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                            <div><label class="olama-label"><?php echo $t('Teacher Action'); ?></label>
                                <textarea name="stage[<?php echo $sk; ?>][teacher_action]" rows="3" style="width:100%;"
                                    class="lp-stage-field" data-field="teacher_action"
                                    placeholder="<?php echo esc_attr(implode(' | ', $sdata['hints'][$lang])); ?>"><?php echo esc_textarea($stage_data['teacher_action'] ?? ''); ?></textarea>
                            </div>
                            <div><label class="olama-label"><?php echo $t('Learner Action'); ?></label>
                                <textarea name="stage[<?php echo $sk; ?>][learner_action]" rows="3" style="width:100%;"
                                    class="lp-stage-field"
                                    data-field="learner_action"><?php echo esc_textarea($stage_data['learner_action'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 100px;gap:15px;margin-top:15px;">
                            <div><label class="olama-label"><?php echo $t('Teaching Strategy'); ?></label>
                                <select name="stage[<?php echo $sk; ?>][teaching_strategy]" style="width:100%;"
                                    class="lp-stage-field" data-field="teaching_strategy">
                                    <option value=""><?php echo $t('-- Select --'); ?></option>
                                    <?php foreach ($lp_teaching_strategies as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($stage_data['teaching_strategy'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo esc_html($label[$lang]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="olama-label"><?php echo $t('Assessment Strategy'); ?></label>
                                <select name="stage[<?php echo $sk; ?>][assessment_strategy]" style="width:100%;"
                                    class="lp-stage-field lp-assessment-strategy" data-field="assessment_strategy"
                                    data-stage-key="<?php echo $sk; ?>">
                                    <option value=""><?php echo $t('-- Select --'); ?></option>
                                    <?php foreach ($lp_assessment_strategies as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($stage_data['assessment_strategy'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo esc_html($label[$lang]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="olama-label"><?php echo $t('Assessment Tool'); ?></label>
                                <select name="stage[<?php echo $sk; ?>][assessment_tool]" style="width:100%;"
                                    class="lp-stage-field lp-assessment-tool" data-stage-key="<?php echo $sk; ?>"
                                    data-field="assessment_tool">
                                    <option value=""><?php echo $t('-- Select --'); ?></option>
                                    <?php foreach ($lp_assessment_tools as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($stage_data['assessment_tool'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo esc_html($label[$lang]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="lp-tool-hint" data-stage-key="<?php echo $sk; ?>"
                                    style="color:#6366f1;font-size:11px;display:none;"></small>
                            </div>
                            <div><label class="olama-label"><?php echo $t('Time'); ?> <small>(min)</small></label>
                                <input type="number" name="stage[<?php echo $sk; ?>][time_minutes]" min="0" max="90"
                                    value="<?php echo intval($stage_data['time_minutes'] ?? 0); ?>" style="width:100%;"
                                    class="lp-stage-field lp-time-input" data-field="time_minutes">
                            </div>
                        </div>
                    </div>
                    <?php $i++; endforeach; ?>
            </div>

            <!-- Section 4: Resources & Reflections -->
            <div class="olama-card lp-section"
                style="background:#fff;padding:25px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;">
                <h3 class="lp-section-title"
                    style="margin-top:0;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">
                    <span class="dashicons dashicons-category"
                        style="margin-right:8px;color:#0ea5e9;"></span><?php echo $t('Resources & Reflection'); ?>
                </h3>

                <div class="lp-res-tabs" style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px;">
                    <button type="button" class="lp-res-tab active" data-res="teaching_resources"
                            style="padding:10px 18px;border:none;background:#f8fafc;cursor:pointer;font-weight:600;font-size:13px;color:#0ea5e9;border-bottom:3px solid #0ea5e9;transition:all 0.2s;">
                        <?php echo $t('Teaching Resources'); ?>
                    </button>
                    <button type="button" class="lp-res-tab" data-res="self_reflection"
                            style="padding:10px 18px;border:none;background:transparent;cursor:pointer;font-weight:600;font-size:13px;color:#64748b;border-bottom:3px solid transparent;transition:all 0.2s;">
                        <?php echo $t('Self-Reflection'); ?>
                    </button>
                    <button type="button" class="lp-res-tab" data-res="homework"
                            style="padding:10px 18px;border:none;background:transparent;cursor:pointer;font-weight:600;font-size:13px;color:#64748b;border-bottom:3px solid transparent;transition:all 0.2s;">
                        <?php echo $t('Homework'); ?>
                    </button>
                </div>

                <div class="lp-res-panel" data-res="teaching_resources">
                    <textarea name="resources" rows="4" style="width:100%;" class="lp-compliance-field"
                        placeholder="<?php echo esc_attr($t('Teaching materials, technology, and resources...')); ?>"><?php echo esc_textarea($edit_plan ? $edit_plan->resources : ''); ?></textarea>
                </div>

                <div class="lp-res-panel" data-res="self_reflection" style="display:none;">
                    <textarea name="self_reflection" rows="4" style="width:100%;" class="lp-compliance-field"
                        placeholder="<?php echo esc_attr($t('Were the learning outcomes achieved? What would you change?')); ?>"><?php echo esc_textarea($edit_plan ? $edit_plan->self_reflection : ''); ?></textarea>
                </div>

                <div class="lp-res-panel" data-res="homework" style="display:none;">
                    <textarea name="homework" rows="4" style="width:100%;" class="lp-compliance-field"
                        placeholder="<?php echo esc_attr($t('Homework assignment details...')); ?>"><?php echo esc_textarea($edit_plan ? $edit_plan->homework : ''); ?></textarea>
                </div>
            </div>

            <!-- Form Footer -->
            <div class="lp-form-footer"
                style="position:sticky;bottom:0;background:#fff;padding:20px;border-top:1px solid #e2e8f0;border-radius:8px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 -4px 6px -1px rgba(0,0,0,0.05);">
                <div style="display:flex;gap:10px;">
                    <button type="submit" name="plan_status" value="draft" class="button button-large">
                        <span class="dashicons dashicons-edit"
                            style="margin-top:4px;margin-right:3px;"></span><?php echo $t('Save as Draft'); ?>
                    </button>
                    <button type="submit" name="plan_status" value="final" class="button button-primary button-large"
                        style="background:#10b981;border-color:#10b981;">
                        <span class="dashicons dashicons-yes"
                            style="margin-top:4px;margin-right:3px;"></span><?php echo $t('Save as Final'); ?>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
    .olama-lesson-planner-wrap {
        max-width: 950px;
        margin-top: 20px;
    }

    .olama-lesson-planner-wrap .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #374151;
        font-size: 13px;
    }

    .olama-lesson-planner-wrap select,
    .olama-lesson-planner-wrap input[type="text"],
    .olama-lesson-planner-wrap input[type="number"],
    .olama-lesson-planner-wrap input[type="date"],
    .olama-lesson-planner-wrap textarea {
        border-radius: 6px;
        border: 1px solid #d1d5db;
        padding: 6px 10px;
    }

    .olama-lesson-planner-wrap textarea {
        resize: vertical;
    }

    .lp-stage-tab:hover {
        background: #f1f5f9 !important;
    }

    .lp-stage-tab.active {
        background: #f8fafc !important;
    }

    <?php if ($is_arabic): ?>
        .olama-lesson-planner-wrap {
            direction: rtl;
        }

        #lp-compliance-badge {
            right: auto !important;
            left: 30px;
        }

        #lp-time-indicator {
            float: left !important;
        }

    <?php endif; ?>
</style>

<script>
    jQuery(document).ready(function ($) {
        var lpConfig = <?php echo wp_json_encode($lp_config); ?>;
        var stageColors = ['#3b82f6', '#f59e0b', '#ec4899', '#10b981'];
        var stageKeys = Object.keys(lpConfig.stages);

        // === Stage Tabs ===
        $('.lp-stage-tab').on('click', function () {
            var stage = $(this).data('stage');
            var idx = stageKeys.indexOf(stage);
            $('.lp-stage-tab').each(function (i) {
                $(this).removeClass('active').css({ background: 'transparent', color: '#64748b', borderBottom: '3px solid transparent' });
            });
            $(this).addClass('active').css({ background: '#f8fafc', color: stageColors[idx], borderBottom: '3px solid ' + stageColors[idx] });
            $('.lp-stage-panel').hide();
            $('.lp-stage-panel[data-stage="' + stage + '"]').show();
        });

        // === Resource Tabs ===
        $('.lp-res-tab').on('click', function() {
            var res = $(this).data('res');
            var color = '#0ea5e9';
            $('.lp-res-tab').removeClass('active').css({ background: 'transparent', color: '#64748b', borderBottom: '3px solid transparent' });
            $(this).addClass('active').css({ background: '#f8fafc', color: color, borderBottom: '3px solid ' + color });
            $('.lp-res-panel').hide();
            $('.lp-res-panel[data-res="' + res + '"]').show();
        });

        // === Cascading Dropdowns: Grade → Section → Subject → Unit → Lesson → Auto-fill ===
        var adminNonce = '<?php echo wp_create_nonce("olama_admin_nonce"); ?>';
        var curriculumNonce = '<?php echo wp_create_nonce("olama_curriculum_nonce"); ?>';
        var editLessonId = <?php echo $edit_plan ? intval($edit_plan->lesson_id) : 0; ?>;
        var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

        // Grade → load Sections
        $('#lp-grade').on('change', function () {
            var gradeId = $(this).val();
            $('#lp-section').html('<option value=""><?php echo esc_js($t('-- Select --')); ?></option>');
            $('#lp-subject').html('<option value=""><?php echo esc_js($t('-- Select Subject --')); ?></option>');
            $('#lp-unit').html('<option value=""><?php echo esc_js($t('-- Select Unit --')); ?></option>');
            $('#lp-lesson').html('<option value=""><?php echo esc_js($t('-- Select Lesson --')); ?></option>');
            clearAutoFill();
            if (!gradeId) return;
            $.post(ajaxurl, { action: 'olama_get_sections_by_grade', nonce: curriculumNonce, grade_id: gradeId }, function (res) {
                if (res.success && res.data) {
                    var opts = '<option value=""><?php echo esc_js($t('-- Select --')); ?></option>';
                    $.each(res.data, function (i, s) {
                        opts += '<option value="' + s.id + '">' + s.section_name + '</option>';
                    });
                    $('#lp-section').html(opts);
                }
            });
        });

        // Section → load Subjects
        $('#lp-section').on('change', function () {
            var sectionId = $(this).val();
            var gradeId = $('#lp-grade').val();
            $('#lp-subject').html('<option value=""><?php echo esc_js($t('-- Select Subject --')); ?></option>');
            $('#lp-unit').html('<option value=""><?php echo esc_js($t('-- Select Unit --')); ?></option>');
            $('#lp-lesson').html('<option value=""><?php echo esc_js($t('-- Select Lesson --')); ?></option>');
            clearAutoFill();
            if (!sectionId || !gradeId) return;
            if (isAdmin) {
                $.post(ajaxurl, { action: 'olama_get_subjects_by_grade', nonce: curriculumNonce, grade_id: gradeId }, function (res) {
                    if (res.success && res.data) {
                        var opts = '<option value=""><?php echo esc_js($t('-- Select Subject --')); ?></option>';
                        $.each(res.data, function (i, sub) { opts += '<option value="' + sub.id + '">' + sub.subject_name + '</option>'; });
                        $('#lp-subject').html(opts);
                    }
                });
            } else {
                $.post(ajaxurl, { action: 'olama_lp_get_teacher_subjects', nonce: adminNonce, grade_id: gradeId, section_id: sectionId }, function (res) {
                    if (res.success && res.data) {
                        var opts = '<option value=""><?php echo esc_js($t('-- Select Subject --')); ?></option>';
                        $.each(res.data, function (i, sub) { opts += '<option value="' + sub.id + '">' + sub.subject_name + '</option>'; });
                        $('#lp-subject').html(opts);
                    }
                });
            }
        });

        // Subject → load Units
        $('#lp-subject').on('change', function () {
            var subjectId = $(this).val();
            var gradeId = $('#lp-grade').val();
            var semesterId = $('input[name="semester_id"]').val();
            $('#lp-unit').html('<option value=""><?php echo esc_js($t('-- Select Unit --')); ?></option>');
            $('#lp-lesson').html('<option value=""><?php echo esc_js($t('-- Select Lesson --')); ?></option>');
            clearAutoFill();
            if (!subjectId || !gradeId || !semesterId) return;
            $.post(ajaxurl, { action: 'olama_lp_get_units', nonce: adminNonce, subject_id: subjectId, grade_id: gradeId, semester_id: semesterId }, function (res) {
                if (res.success && res.data) {
                    var opts = '<option value=""><?php echo esc_js($t('-- Select Unit --')); ?></option>';
                    $.each(res.data, function (i, u) { opts += '<option value="' + u.id + '">' + u.unit_name + '</option>'; });
                    $('#lp-unit').html(opts);
                }
            });
        });

        // Unit → load Lessons
        $('#lp-unit').on('change', function () {
            var unitId = $(this).val();
            $('#lp-lesson').html('<option value=""><?php echo esc_js($t('-- Select Lesson --')); ?></option>');
            clearAutoFill();
            if (!unitId) return;
            $.post(ajaxurl, { action: 'olama_lp_get_timeline_lessons', nonce: adminNonce, unit_id: unitId }, function (res) {
                if (res.success && res.data) {
                    var opts = '<option value=""><?php echo esc_js($t('-- Select Lesson --')); ?></option>';
                    $.each(res.data, function (i, l) {
                        opts += '<option value="' + l.id + '" data-title="' + $('<span>').text(l.lesson_title).html() + '" data-periods="' + l.periods + '" data-start="' + (l.start_date || '') + '" data-end="' + (l.end_date || '') + '">' + l.lesson_number + '. ' + l.lesson_title + '</option>';
                    });
                    $('#lp-lesson').html(opts);
                    if (editLessonId) {
                        $('#lp-lesson').val(editLessonId).trigger('change');
                        editLessonId = 0;
                    }
                }
            });
        });

        // Lesson → auto-fill
        $('#lp-lesson').on('change', function () {
            var $sel = $(this).find(':selected');
            if (!$sel.val()) { clearAutoFill(); return; }
            $('#lp-lesson-title').val($sel.data('title') || '');
            $('#lp-start-date').val($sel.data('start') || '');
            $('#lp-end-date').val($sel.data('end') || '');
            $('#lp-num-classes').val($sel.data('periods') || 1);
            updateTimeIndicator();
            updateCompliance();
        });

        function clearAutoFill() {
            $('#lp-lesson-title').val('');
            $('#lp-start-date').val('');
            $('#lp-end-date').val('');
            $('#lp-num-classes').val(1);
            updateTimeIndicator();
            updateCompliance();
        }

        // Edit mode: trigger cascade chain
        <?php if ($edit_plan && $edit_plan->unit_id): ?>
                (function () {
                    editLessonId = <?php echo intval($edit_plan->lesson_id); ?>;
                    if ($('#lp-unit').val()) {
                        $('#lp-unit').trigger('change');
                    }
                })();
        <?php endif; ?>

        // === Outcomes ===
        var $firstVerb = $('.lp-outcome-verb:first');
        var outcomeVerbHtml = $firstVerb.length ? ($firstVerb.prop('outerHTML') || '').replace(/selected/g, '') : '';

        $('#lp-add-outcome').on('click', function () {
            if (!outcomeVerbHtml) return;
            var count = $('#lp-outcomes-container .lp-outcome-row').length + 1;
            var row = '<div class="lp-outcome-row" style="display:flex;gap:10px;margin-bottom:10px;align-items:center;flex-wrap:wrap;">' +
                '<span class="lp-outcome-num" style="background:#6366f1;color:#fff;min-width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">' + count + '</span>' +
                outcomeVerbHtml.replace('name="outcome_verb[]"', 'name="outcome_verb[]"') +
                '<input type="text" name="outcome_content[]" value="" placeholder="<?php echo esc_js($t('Content / concept')); ?>" style="flex:2;padding:6px 10px;">' +
                '<input type="text" name="outcome_level[]" value="" placeholder="<?php echo esc_js($t('Performance level')); ?>" style="flex:1;padding:6px 10px;">' +
                '<button type="button" class="button lp-remove-outcome" style="color:#dc2626;"><span class="dashicons dashicons-no-alt" style="margin-top:3px;"></span></button></div>';
            $('#lp-outcomes-container').append(row);
            updateCompliance();
        });
        $(document).on('click', '.lp-remove-outcome', function () {
            if ($('#lp-outcomes-container .lp-outcome-row').length > 1) {
                $(this).closest('.lp-outcome-row').remove();
                $('#lp-outcomes-container .lp-outcome-row').each(function (i) { $(this).find('.lp-outcome-num').text(i + 1); });
                updateCompliance();
            }
        });

        // === Strategy-Tool Alignment Hints ===
        $(document).on('change', '.lp-assessment-strategy', function () {
            var stage = $(this).data('stage-key');
            var strategy = $(this).val();
            var $hint = $('.lp-tool-hint[data-stage-key="' + stage + '"]');
            var $toolSelect = $('.lp-assessment-tool[data-stage-key="' + stage + '"]');
            if (strategy && lpConfig.strategy_tool_alignment[strategy]) {
                var suggested = lpConfig.strategy_tool_alignment[strategy];
                var labels = suggested.map(function (k) { return lpConfig.assessment_tools[k] || k; });
                $hint.text('💡 <?php echo esc_js($t('Suggested')); ?>: ' + labels.join(', ')).show();
                // Highlight suggested options
                $toolSelect.find('option').css('font-weight', 'normal');
                suggested.forEach(function (k) { $toolSelect.find('option[value="' + k + '"]').css('font-weight', 'bold'); });
            } else { $hint.hide(); }
            updateCompliance();
        });

        // === Time Indicator ===
        function updateTimeIndicator() {
            var total = 0;
            $('.lp-time-input').each(function () { total += parseInt($(this).val()) || 0; });
            var periodDuration = parseInt($('#lp-period-duration').val()) || 45;
            var expected = (parseInt($('#lp-num-classes').val()) || 1) * periodDuration;
            var color = total === expected ? '#10b981' : (total > expected ? '#ef4444' : '#f59e0b');
            $('#lp-time-indicator').html('<span style="color:' + color + '">⏱ ' + total + ' / ' + expected + ' <?php echo esc_js($t('min')); ?></span>');
        }
        $(document).on('input change', '.lp-time-input, #lp-num-classes, #lp-period-duration', function () { updateTimeIndicator(); updateCompliance(); });

        // === Compliance Scoring (Client-side mirror) ===
        function updateCompliance() {
            var w = lpConfig.compliance_weights;
            var score = 0;
            var details = [];

            // 1. Outcomes
            var validOutcomes = 0;
            $('.lp-outcome-row').each(function () {
                if ($(this).find('.lp-outcome-verb').val() && $(this).find('input[name="outcome_content[]"]').val()) validOutcomes++;
            });
            if (validOutcomes > 0) { score += w.outcomes_with_verb; details.push('✅ <?php echo esc_js($t('Learning Outcomes')); ?>'); }
            else details.push('❌ <?php echo esc_js($t('Learning Outcomes')); ?>');

            // 2-7. Stages
            var teacherCount = 0, learnerCount = 0, strategyCount = 0, assessCount = 0, toolCount = 0;
            stageKeys.forEach(function (sk) {
                var panel = $('.lp-stage-panel[data-stage="' + sk + '"]');
                if (panel.find('textarea[name="stage[' + sk + '][teacher_action]"]').val()) teacherCount++;
                if (panel.find('textarea[name="stage[' + sk + '][learner_action]"]').val()) learnerCount++;
                if (panel.find('select[name="stage[' + sk + '][teaching_strategy]"]').val()) strategyCount++;
                if (panel.find('select[name="stage[' + sk + '][assessment_strategy]"]').val()) assessCount++;
                if (panel.find('select[name="stage[' + sk + '][assessment_tool]"]').val()) toolCount++;
            });
            score += Math.round(w.stages_teacher_action * (teacherCount / stageKeys.length));
            score += Math.round(w.stages_learner_action * (learnerCount / stageKeys.length));
            var totalTime = 0; $('.lp-time-input').each(function () { totalTime += parseInt($(this).val()) || 0; });
            var periodDuration2 = parseInt($('#lp-period-duration').val()) || 45;
            var expectedTime = (parseInt($('#lp-num-classes').val()) || 1) * periodDuration2;
            if (totalTime === expectedTime && expectedTime > 0) score += w.time_distribution;
            else if (totalTime > 0 && expectedTime > 0) score += Math.round(w.time_distribution * Math.min(totalTime, expectedTime) / Math.max(totalTime, expectedTime));
            score += Math.round(w.teaching_strategy * (strategyCount / stageKeys.length));
            score += Math.round(w.assessment_strategy * (assessCount / stageKeys.length));
            score += Math.round(w.assessment_tool * (toolCount / stageKeys.length));
            details.push((teacherCount === stageKeys.length ? '✅' : '⚠️') + ' <?php echo esc_js($t('Teacher Actions')); ?> (' + teacherCount + '/' + stageKeys.length + ')');
            details.push((learnerCount === stageKeys.length ? '✅' : '⚠️') + ' <?php echo esc_js($t('Learner Actions')); ?> (' + learnerCount + '/' + stageKeys.length + ')');
            details.push((totalTime === expectedTime ? '✅' : '⚠️') + ' <?php echo esc_js($t('Time')); ?> (' + totalTime + '/' + expectedTime + ')');

            // 8-10. Text fields
            if ($('textarea[name="resources"]').val()) { score += w.resources; details.push('✅ <?php echo esc_js($t('Teaching Resources')); ?>'); }
            else details.push('❌ <?php echo esc_js($t('Teaching Resources')); ?>');
            if ($('textarea[name="self_reflection"]').val()) { score += w.self_reflection; details.push('✅ <?php echo esc_js($t('Self-Reflection')); ?>'); }
            else details.push('❌ <?php echo esc_js($t('Self-Reflection')); ?>');
            if ($('textarea[name="homework"]').val()) { score += w.homework; details.push('✅ <?php echo esc_js($t('Homework')); ?>'); }
            else details.push('❌ <?php echo esc_js($t('Homework')); ?>');

            score = Math.min(100, score);
            var color = score >= 80 ? '#10b981' : (score >= 50 ? '#f59e0b' : '#ef4444');
            $('#lp-compliance-score').text(score + '%').css('color', color);
            $('#lp-compliance-bar').css({ width: score + '%', background: color });
            $('#lp-compliance-badge').css('border-color', color);
            $('#lp-compliance-details').html(details.join('<br>'));
        }

        // Trigger compliance on any input change
        $(document).on('input change', '.lp-stage-field, .lp-outcome-verb, .lp-compliance-field, input[name="outcome_content[]"], input[name="outcome_level[]"]', updateCompliance);

        // Initial
        updateTimeIndicator();
        updateCompliance();
        // Trigger tool hints for existing data
        $('.lp-assessment-strategy').each(function () { if ($(this).val()) $(this).trigger('change'); });
    });
</script>