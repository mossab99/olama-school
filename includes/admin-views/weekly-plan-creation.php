<?php
/**
 * Weekly Plan Creation View
 */
if (!defined('ABSPATH')) {
    exit;
}

// All variables like $grades, $sections, $months_weeks, $selected_month, 
// $week_start, $selected_date, and $today_plans are provided by class-admin.php
?>

<div class="olama-plan-creation-container">
    <!-- Section 1: Top Navigation & Filters -->
    <div class="olama-card"
        style="margin-bottom: 25px; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <form method="get" id="olama-plan-filters" class="olama-filter-row">
            <input type="hidden" name="page" value="olama-school-plans" />
            <input type="hidden" name="tab" value="creation" />
            <input type="hidden" name="active_day" id="active_day_input" value="<?php echo esc_attr($active_day); ?>" />

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
                <label><?php _e('Grade', 'olama-school'); ?></label>
                <select name="grade_id" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Section', 'olama-school'); ?></label>
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

            <div class="olama-filter-item">
                <label><?php _e('Month', 'olama-school'); ?></label>
                <select name="plan_month" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Week', 'olama-school'); ?></label>
                <select name="week_start" class="olama-select" onchange="this.form.submit()">
                    <?php
                    $w_count = 1;
                    foreach ($current_month_weeks as $w): ?>
                        <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                            <?php echo sprintf(__('%s %d', 'olama-school'), __('Week', 'olama-school'), $w_count) . " " . esc_html($w['label']); ?>
                        </option>
                        <?php $w_count++; endforeach; ?>
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
                        <?php echo __(esc_html($day_name), 'olama-school'); ?>
                    </strong><br>
                    <small>
                        <?php echo Olama_School_Helpers::format_date($date, false, 'M d'); ?>
                    </small>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php
    $needs_edit_count = count(array_filter($today_plans, function ($p) {
        return $p->status === 'needs_edit';
    }));
    if ($needs_edit_count > 0): ?>
        <div
            style="background: #fef2f2; border: 1px solid #fee2e2; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; border-right: 5px solid #ef4444;">
            <span class="dashicons dashicons-warning"
                style="color: #ef4444; font-size: 24px; width: 24px; height: 24px;"></span>
            <div>
                <strong
                    style="color: #991b1b; display: block; font-size: 1.1em;"><?php echo Olama_School_Helpers::translate('Action Required'); ?></strong>
                <span
                    style="color: #b91c1c;"><?php printf(Olama_School_Helpers::translate('You have %d plans that need urgent revision from the supervisor.'), $needs_edit_count); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="olama-two-column" style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
        <!-- Left Column: Form -->
        <div class="olama-form-col"
            style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h2 style="margin-top: 0; color: #1d2327;">
                <?php printf(__('%s\'s Plan', 'olama-school'), __($active_day, 'olama-school')); ?> -
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

                <input type="hidden" name="plan_type" id="olama-plan-type" value="homework" />

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

                <!-- Plan Type Toggle -->
                <div class="olama-plan-type-toggle" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 10px;">
                        <?php echo Olama_School_Helpers::translate('Plan Type'); ?>
                    </label>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="olama-plan-type-btn active" data-type="homework"
                            style="flex: 1; padding: 12px 20px; border: 2px solid #3b82f6; border-radius: 8px; background: #3b82f6; color: #fff; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span style="font-size: 1.2em;">📝</span>
                            <?php echo Olama_School_Helpers::translate('Homework Plan'); ?>
                        </button>
                        <button type="button" class="olama-plan-type-btn" data-type="review"
                            style="flex: 1; padding: 12px 20px; border: 2px solid #8b5cf6; border-radius: 8px; background: #fff; color: #8b5cf6; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span style="font-size: 1.2em;">🔄</span>
                            <?php echo Olama_School_Helpers::translate('Review Plan'); ?>
                        </button>
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
                            // semester_id is already calculated in class-admin.php
                            $scheduled_subjects = Olama_School_Schedule::get_unique_subjects_for_day($selected_section_id, $active_day, $semester_id, $selected_date);

                            $filled_subject_ids = array_map(function ($p) {
                                return $p->subject_id;
                            }, $today_plans);

                            foreach ($scheduled_subjects as $subj):
                                $is_filled = in_array($subj->id, $filled_subject_ids);
                                ?>
                                <option value="<?php echo $subj->id; ?>" <?php echo $is_filled ? 'data-filled="true" class="olama-filled-subject"' : ''; ?>>
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

                <!-- Supervisor Feedback & Teacher Response Loop -->
                <div id="olama-revision-wrapper"
                    style="display: none; margin-bottom: 20px; padding: 20px; background: #fffaf0; border: 2px solid #f59e0b; border-radius: 12px;">
                    <div
                        style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: #975a16; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; border-bottom: 1px solid #fbd38d; padding-bottom: 12px;">
                        <span style="font-size: 20px;">💬</span>
                        <?php echo Olama_School_Helpers::translate('Feedback History'); ?>
                    </div>

                    <div id="olama-supervisor-feedback-display"
                        style="background: #fff; padding: 15px; border-radius: 8px; border-right: 4px solid #ef4444; color: #7f1d1d; font-size: 0.9rem; line-height: 1.6; margin-bottom: 15px;">
                        <strong><?php echo Olama_School_Helpers::translate('Supervisor Feedback'); ?>:</strong>
                        <div class="content" style="white-space: pre-line; margin-top: 5px;"></div>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 700; color: #2d3748; margin-bottom: 8px; font-size: 0.85rem;">
                            <?php echo Olama_School_Helpers::translate('Your Response'); ?>
                        </label>
                        <textarea name="teacher_response" id="olama-teacher-response"
                            placeholder="<?php echo Olama_School_Helpers::translate('Explain the changes you made...'); ?>"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; min-height: 80px;"></textarea>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

                <!-- Homework Fields (hidden for review plans) -->
                <div class="olama-homework-grid">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                                <?php _e('Homework (Student Book)', 'olama-school'); ?>
                            </label>
                            <textarea name="homework_sb" rows="3" style="width: 100%;"
                                placeholder="<?php _e('Page numbers or details...', 'olama-school'); ?>"></textarea>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                                <?php _e('Homework (Exercise Book)', 'olama-school'); ?>
                            </label>
                            <textarea name="homework_eb" rows="3" style="width: 100%;"
                                placeholder="<?php _e('Page numbers or details...', 'olama-school'); ?>"></textarea>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                                <?php _e('Homework (Notebook)', 'olama-school'); ?>
                            </label>
                            <textarea name="homework_nb" rows="3" style="width: 100%;"
                                placeholder="<?php _e('Notebook instructions...', 'olama-school'); ?>"></textarea>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                                <?php _e('Homework (Worksheet)', 'olama-school'); ?>
                            </label>
                            <textarea name="homework_ws" rows="3" style="width: 100%;"
                                placeholder="<?php _e('Worksheet details...', 'olama-school'); ?>"></textarea>
                        </div>
                    </div>
                </div> <!-- End olama-homework-grid -->

                <div class="olama-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <?php _e('Teacher\'s Notes', 'olama-school'); ?>
                    </label>
                    <textarea name="teacher_notes" rows="3" style="width: 100%;"
                        placeholder="<?php _e('Additional notes...', 'olama-school'); ?>"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 30px;">
                    <button type="button" id="olama-cancel-edit-btn" class="button button-large"
                        style="margin-right: 15px; display: none; height: 46px; font-weight: 600;">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                    <input type="submit" name="save_plan" id="olama-save-plan-btn" class="button button-large"
                        style="height: 46px; padding: 0 25px; font-weight: 600; margin-right: 10px;"
                        value="<?php _e('Save as Draft', 'olama-school'); ?>" />
                    <button type="button" id="olama-submit-plan-btn" class="button button-primary button-large"
                        style="height: 46px; padding: 0 30px; font-weight: 600;">
                        <?php _e('Submit for Review', 'olama-school'); ?>
                    </button>
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
                        'supervisor_feedback' => $plan->supervisor_feedback ?? '',
                        'teacher_response' => $plan->teacher_response ?? '',
                        'question_ids' => $q_ids,
                        'status' => $plan->status,
                        'teacher_name' => $plan->teacher_name ?? '',
                        'plan_type' => $plan->plan_type ?? 'homework'
                    ]);
                    ?>
                    <div class="olama-plan-item" data-plan="<?php echo esc_attr($plan_json); ?>"
                        style="border-left: 4px solid <?php echo esc_attr($plan->color_code); ?>; padding: 15px; margin-bottom: 15px; background: #fcfcfc; border-radius: 0 8px 8px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
                        <?php if ($plan->status === 'needs_edit'): ?>
                            <span class="olama-feedback-warning" title="<?php echo esc_attr($plan->supervisor_feedback); ?>"
                                style="position: absolute; top: 8px; right: 8px; font-size: 18px; cursor: help; z-index: 5;">
                                ⚠️
                            </span>
                        <?php endif; ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="font-size: 1.1em; color: <?php echo esc_attr($plan->color_code); ?>;">
                                <?php echo esc_html($plan->subject_name); ?>
                            </strong>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if (!empty($plan->teacher_name)): ?>
                                    <span class="teacher-badge"
                                        style="font-size: 0.75em; padding: 2px 8px; border-radius: 12px; background: #e2e8f0; color: #475569; font-weight: 600;">
                                        <i class="dashicons dashicons-admin-users"
                                            style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></i>
                                        <?php echo esc_html($plan->teacher_name); ?>
                                    </span>
                                <?php endif; ?>
                                <?php
                                $status_color = '#64748b';
                                $status_bg = '#f1f5f9';
                                $status_label = ucfirst($plan->status);

                                if ($plan->status === 'submitted') {
                                    $status_color = '#d97706';
                                    $status_bg = '#fef3c7';
                                } elseif ($plan->status === 'approved' || $plan->status === 'published') {
                                    $status_color = '#10b981';
                                    $status_bg = '#dcfce7';
                                    $status_label = Olama_School_Helpers::translate('Approved');
                                } elseif ($plan->status === 'needs_edit') {
                                    $status_color = '#ef4444';
                                    $status_bg = '#fef2f2';
                                    $status_label = Olama_School_Helpers::translate('Needs Revision');
                                } elseif ($plan->status === 'edited') {
                                    $status_color = '#6366f1';
                                    $status_bg = '#eef2ff';
                                    $status_label = Olama_School_Helpers::translate('Edited');
                                }
                                ?>
                                <span class="status-badge"
                                    style="font-size: 0.8em; padding: 2px 8px; border-radius: 12px; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; font-weight: 700;">
                                    <?php echo $status_label; ?>
                                </span>
                                <?php if (isset($plan->plan_type) && $plan->plan_type === 'review'): ?>
                                    <span class="plan-type-badge review"
                                        style="font-size: 0.75em; padding: 2px 8px; border-radius: 12px; background: #f3e8ff; color: #7c3aed; font-weight: 600;">
                                        🔄 <?php echo Olama_School_Helpers::translate('Review'); ?>
                                    </span>
                                <?php endif; ?>
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
                                <?php echo $plan->homework_sb ? __('SB:', 'olama-school') . ' ' . esc_html($plan->homework_sb) : ''; ?>
                                <?php echo $plan->homework_eb ? ' ' . __('EB:', 'olama-school') . ' ' . esc_html($plan->homework_eb) : ''; ?>
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

<script>
    jQuery(document).ready(function ($) {
        // Auto-open plan if plan_id is in URL
        const urlParams = new URLSearchParams(window.location.search);
        const planId = urlParams.get('plan_id');

        if (planId) {
            // Wait slightly for plan.js and other components to be ready
            setTimeout(function () {
                let planOpened = false;
                $('.olama-plan-item').each(function () {
                    try {
                        const planData = $(this).data('plan');
                        if (planData && parseInt(planData.id) === parseInt(planId)) {
                            $(this).find('.olama-edit-plan').trigger('click');
                            planOpened = true;

                            // Scroll to the item for better visibility
                            $(this)[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return false; // Break loop
                        }
                    } catch (e) {
                        console.error('Olama Error: Failed to parse plan data for auto-open', e);
                    }
                });

                if (!planOpened) {
                    console.log('Olama: Plan ' + planId + ' not found on this day view.');
                }
            }, 800);
        }
    });
</script>