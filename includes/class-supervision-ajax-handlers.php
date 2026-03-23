<?php
/**
 * Academic Supervision AJAX Handlers
 */
if (!defined('ABSPATH'))
    exit;

class Olama_School_Supervision_Ajax_Handlers
{
    public function __construct()
    {
        add_action('wp_ajax_olama_save_supervisor_visit', array($this, 'save_supervisor_visit'));
        add_action('wp_ajax_olama_delete_supervisor_visit', array($this, 'delete_supervisor_visit'));
        add_action('wp_ajax_olama_get_supervisor_evaluation_modal', array($this, 'get_supervisor_evaluation_modal'));
        add_action('wp_ajax_olama_save_supervisor_evaluation_modal', array($this, 'save_supervisor_evaluation_modal'));
        add_action('wp_ajax_olama_save_supervisor_assignment', array($this, 'save_supervisor_assignment'));
        add_action('wp_ajax_olama_delete_supervisor_assignment', array($this, 'delete_supervisor_assignment'));
    }

    /**
     * AJAX: Save Supervisor Visit
     */
    public function save_supervisor_visit()
    {
        try {
            parse_str($_POST['data'] ?? '', $data);

            if (empty($data) || !wp_verify_nonce($data['_wpnonce'] ?? '', 'olama_plan_visit_nonce')) {
                wp_send_json_error(__('Security check failed.', 'olama-school'));
            }

            if (!Olama_School_Permissions::can('olama_manage_supervision_plan')) {
                wp_send_json_error(__('Unauthorized.', 'olama-school'));
            }

            $schedule_id = intval($data['schedule_id']);
            $raw_visit_date = sanitize_text_field($data['visit_date']);
            $visit_date = date('Y-m-d', strtotime($raw_visit_date));
            $template_id = intval($data['template_id']);

            // 1. Validate date matches day
            global $wpdb;
            $schedule = $wpdb->get_row($wpdb->prepare(
                "SELECT s.day_name, sem.academic_year_id, s.semester_id 
                 FROM {$wpdb->prefix}olama_schedule s
                 JOIN {$wpdb->prefix}olama_semesters sem ON s.semester_id = sem.id
                 WHERE s.id = %d",
                $schedule_id
            ));

            if (!$schedule) {
                wp_send_json_error(__('Schedule not found. Schedule ID: ' . $schedule_id, 'olama-school'));
            }

            if (!\Olama\Services\ScheduleValidatorService::validate_date_matches_day($visit_date, $schedule->day_name)) {
                wp_send_json_error(__('Visit date does not match the scheduled day. Sent: ' . $visit_date . ' Expected: ' . $schedule->day_name, 'olama-school'));
            }

            // 2. Check if it's a past date
            if (\Olama\Services\ScheduleValidatorService::is_past_date($visit_date)) {
                wp_send_json_error(__('Cannot plan visits in the past.', 'olama-school'));
            }

            // 3. Create Visit Record
            $visit_data = [
                'schedule_id' => $schedule_id,
                'supervisor_id' => get_current_user_id(),
                'visit_date' => $visit_date,
                'notes' => $data['notes'] ?? ''
            ];
            $visit_id = \Olama\Services\SupervisorVisitService::create_visit($visit_data);

            if (!$visit_id) {
                wp_send_json_error('Failed to create visit record. DB Error: ' . $wpdb->last_error . ' Data: ' . json_encode($visit_data));
            }

            // 4. Pre-create Evaluation Record for this visit
            Olama_School_EV_Record::save_evaluation([
                'template_id' => $template_id,
                'academic_year_id' => $schedule->academic_year_id,
                'semester_id' => $schedule->semester_id,
                'context_type' => 'supervisor',
                'related_entity_type' => 'supervisor_visit',
                'related_entity_id' => $visit_id,
                'status' => 'draft'
            ]);

            wp_send_json_success();
        } catch (\Throwable $e) {
            error_log('Supervision Save Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            wp_send_json_error('Server Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Delete Supervisor Visit
     */
    public function delete_supervisor_visit()
    {
        check_ajax_referer('olama_delete_visit_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_access_supervision')) {
            wp_send_json_error(__('Unauthorized.', 'olama-school'));
        }

        $id = intval($_POST['id']);
        global $wpdb;

        // Delete the visit
        $result = $wpdb->delete("{$wpdb->prefix}olama_supervisor_visits", array('id' => $id));

        if ($result) {
            // Also delete associated evaluation record and scores
            $eval = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_ev_records WHERE related_entity_id = %d AND related_entity_type = 'supervisor_visit'",
                $id
            ));

            if ($eval) {
                $wpdb->delete("{$wpdb->prefix}olama_ev_scores", array('evaluation_id' => $eval->id));
                $wpdb->delete("{$wpdb->prefix}olama_ev_records", array('id' => $eval->id));
            }

            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete visit.', 'olama-school'));
        }
    }

    /**
     * AJAX: Get Supervisor Evaluation Form parts (Modal)
     */
    public function get_supervisor_evaluation_modal()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_access_evaluation')) {
            wp_send_json_error(__('Unauthorized.', 'olama-school'));
        }

        $visit_id = isset($_POST['visit_id']) ? intval($_POST['visit_id']) : 0;
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if (!$visit_id || !$template_id) {
            wp_send_json_error(__('Invalid parameters', 'olama-school'));
        }

        global $wpdb;

        // 1. Fetch Visit & Context Details
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, s.day_name, s.period_number, s.section_id, s.semester_id, s.subject_id,
                    sec.section_name, sec.academic_year_id, g.grade_name, g.id as grade_id, sub.subject_name
             FROM {$wpdb->prefix}olama_supervisor_visits v
             JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
             JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
             JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
             JOIN {$wpdb->prefix}olama_subjects sub ON s.subject_id = sub.id
             WHERE v.id = %d",
            $visit_id
        ));

        if (!$visit) {
            wp_send_json_error(__('Visit not found.', 'olama-school'));
        }

        $active_year = Olama_School_Academic::get_active_year();
        $semesters = Olama_School_Academic::get_semesters($visit->academic_year_id);
        $semester_name = '';
        foreach ($semesters as $sem) {
            if ($sem->id == $visit->semester_id)
                $semester_name = $sem->semester_name;
        }

        // Fetch Assigned Teacher directly from DB, ignoring orphaned records
        $teacher = $wpdb->get_row($wpdb->prepare(
            "SELECT u.ID, u.display_name 
             FROM {$wpdb->prefix}olama_teacher_assignments ta
             JOIN {$wpdb->users} u ON ta.teacher_id = u.ID
             WHERE ta.section_id = %d AND ta.subject_id = %d AND ta.academic_year_id = %d 
             LIMIT 1",
            $visit->section_id,
            $visit->subject_id,
            $visit->academic_year_id
        ));

        $teacher_name = $teacher ? $teacher->display_name : '<span style="color:#ef4444;">' . __('Not Assigned', 'olama-school') . '</span>';

        // Fetch Units
        $units = Olama_School_Unit::get_units($visit->subject_id, $visit->grade_id, $visit->semester_id);

        $unit_options = '<option value="">' . __('-- Select Unit --', 'olama-school') . '</option>';
        foreach ($units as $u) {
            $selected = ($visit->unit_id == $u->id) ? 'selected' : '';
            $unit_options .= '<option value="' . $u->id . '" ' . $selected . '>' . esc_html($u->unit_name) . '</option>';
        }

        // Fetch Lessons (if unit is pre-selected)
        $lesson_options = '<option value="">' . __('-- Select Lesson --', 'olama-school') . '</option>';
        if ($visit->unit_id) {
            $lessons = Olama_School_Lesson::get_lessons($visit->unit_id);
            foreach ($lessons as $l) {
                $selected = ($visit->lesson_id == $l->id) ? 'selected' : '';
                $lesson_options .= '<option value="' . $l->id . '" ' . $selected . '>' . esc_html($l->lesson_title) . '</option>';
            }
        }

        // Fetch Evaluation Record and Scores
        $record = Olama_School_EV_Record::get_evaluation($visit->supervisor_id, $visit->academic_year_id, $visit->semester_id, $template_id, 'supervisor', $visit_id);
        $scores = $record ? Olama_School_EV_Record::get_scores($record->id) : [];

        // Fetch Template Data
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($template_id);
        $score_config = Olama_School_EV_Template::get_score_config($template_id);

        ob_start();
        ?>
        <form id="olama-supervisor-eval-form">
            <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
            <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">

            <!-- Part 1: Plan Details -->
            <div class="eval-part1-grid">
                <div class="eval-info-box">
                    <label><?php _e('Academic Year', 'olama-school'); ?></label>
                    <div><?php echo esc_html($active_year->year_name ?? ''); ?></div>
                </div>
                <div class="eval-info-box">
                    <label><?php _e('Semester', 'olama-school'); ?></label>
                    <div><?php echo esc_html($semester_name); ?></div>
                </div>
                <div class="eval-info-box">
                    <label><?php _e('Grade & Section', 'olama-school'); ?></label>
                    <div><?php echo esc_html($visit->grade_name . ' - ' . $visit->section_name); ?></div>
                </div>
                <div class="eval-info-box">
                    <label><?php _e('Subject', 'olama-school'); ?></label>
                    <div><?php echo esc_html($visit->subject_name); ?></div>
                </div>
                <div class="eval-info-box">
                    <label><?php _e('Visit Date', 'olama-school'); ?></label>
                    <div style="font-weight: 500;">
                        <?php
                        $day_translated = Olama_School_Helpers::translate($visit->day_name);
                        $date_formatted = date_i18n(get_option('date_format'), strtotime($visit->visit_date));

                        $period_ordinals = [
                            1 => 'الأولى',
                            2 => 'الثانية',
                            3 => 'الثالثة',
                            4 => 'الرابعة',
                            5 => 'الخامسة',
                            6 => 'السادسة',
                            7 => 'السابعة',
                            8 => 'الثامنة'
                        ];

                        $period_ordinal = isset($period_ordinals[$visit->period_number]) ? $period_ordinals[$visit->period_number] : $visit->period_number;
                        $period_text = Olama_School_Helpers::translate('Period') . ' ' . $period_ordinal;

                        echo esc_html(sprintf('%s %s - %s', $day_translated, $date_formatted, $period_text));
                        ?>
                    </div>
                </div>
                <div class="eval-info-box" style="background: #eff6ff; border-color: #bfdbfe;">
                    <label style="color: #3b82f6;"><?php _e('Assigned Teacher', 'olama-school'); ?></label>
                    <div style="color: #1d4ed8; font-size: 1.1em;"><?php echo $teacher_name; ?></div>
                </div>
            </div>

            <!-- Dynamic Dropdowns for Unit and Lesson -->
            <div style="display: flex; gap: 20px; margin-bottom: 30px;">
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:8px;"><?php _e('Unit', 'olama-school'); ?> <span
                            style="color:#ef4444;">*</span></label>
                    <select name="unit_id" id="eval-unit-select" required style="width:100%; max-width:100%;">
                        <?php echo $unit_options; ?>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-weight:600; margin-bottom:8px;"><?php _e('Lesson', 'olama-school'); ?>
                        <span style="color:#ef4444;">*</span></label>
                    <select name="lesson_id" id="eval-lesson-select" required style="width:100%; max-width:100%;">
                        <?php echo $lesson_options; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <label
                    style="display:block; font-weight:600; margin-bottom:8px;"><?php echo Olama_School_Helpers::translate('Supervisor Comments'); ?></label>
                <textarea name="supervisor_comments"
                    style="width:100%; max-width:100%; min-height: 80px; border-radius: 6px; border-color: #cbd5e1;"
                    placeholder="<?php echo esc_attr(Olama_School_Helpers::translate('Enter supervisor comments for this visit...')); ?>"><?php echo esc_textarea($record ? $record->supervisor_comments : ''); ?></textarea>
            </div>

            <hr style="margin-bottom: 30px; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Part 2: Evaluation Form Content -->
            <h3 style="margin-top: 0; margin-bottom: 20px;"><?php echo esc_html($template->template_name); ?></h3>
            <div class="ev-review-wrapper" style="direction: <?php echo Olama_School_Helpers::is_arabic() ? 'rtl' : 'ltr'; ?>;">
                <?php foreach ($curriculum as $domain): ?>
                    <div class="ev-domain-section">
                        <div class="ev-domain-header"><?php echo esc_html($domain->title_ar); ?></div>

                        <?php foreach ($domain->categories as $category): ?>
                            <div
                                style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                                <div class="ev-category-header"><?php echo esc_html($category->title_ar); ?></div>

                                <div>
                                    <?php foreach ($category->indicators as $indicator):
                                        $score_row = $scores[$indicator->id] ?? null;
                                        $score_val = $score_row ? $score_row->score : null;
                                        ?>
                                        <div class="ev-indicator-row">
                                            <div style="width: 50%; padding-right: 20px;">
                                                <?php echo esc_html($indicator->indicator_text); ?>
                                                <?php if ($indicator->is_critical): ?>
                                                    <span style="color: #ef4444; font-size: 11px; font-weight: bold; margin-left: 5px;">*
                                                        Critical</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ev-scoring-options">
                                                <?php
                                                $total_levels = count($score_config);
                                                $i = 0;
                                                foreach ($score_config as $val => $label) {
                                                    $i++;
                                                    $is_active = ($score_val == $val);
                                                    $color_class = 'not-mastered';
                                                    if ($i === 1)
                                                        $color_class = 'mastered';
                                                    elseif ($i === $total_levels)
                                                        $color_class = 'not-mastered';
                                                    elseif ($i === 2 && $total_levels > 2)
                                                        $color_class = 'partial';
                                                    ?>
                                                    <label
                                                        class="ev-score-option <?php echo $color_class; ?> <?php echo $is_active ? 'active' : ''; ?>">
                                                        <input type="radio" name="scores[<?php echo $indicator->id; ?>]"
                                                            value="<?php echo $val; ?>" <?php checked($score_val, $val); ?> required>
                                                        <span><?php echo esc_html(Olama_School_Helpers::translate($label)); ?></span>
                                                    </label>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php foreach ($category->indicators as $indicator):
                                $score_row = $scores[$indicator->id] ?? null;
                                $score_val = $score_row ? $score_row->score : null;
                                ?>
                                <div class="ev-indicator-row">
                                    <div style="width: 50%; padding-right: 20px;">
                                        <?php echo esc_html($indicator->indicator_text); ?>
                                        <?php if ($indicator->is_critical): ?>
                                            <span style="color: #ef4444; font-size: 11px; font-weight: bold; margin-left: 5px;">*
                                                Critical</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ev-scoring-options">
                                        <?php
                                        $total_levels = count($score_config);
                                        $i = 0;
                                        foreach ($score_config as $val => $label) {
                                            $i++;
                                            $is_active = ($score_val == $val);
                                            $color_class = 'not-mastered';
                                            if ($i === 1)
                                                $color_class = 'mastered';
                                            elseif ($i === $total_levels)
                                                $color_class = 'not-mastered';
                                            elseif ($i === 2 && $total_levels > 2)
                                                $color_class = 'partial';
                                            ?>
                                            <label
                                                class="ev-score-option <?php echo $color_class; ?> <?php echo $is_active ? 'active' : ''; ?>">
                                                <input type="radio" name="scores[<?php echo $indicator->id; ?>]" value="<?php echo $val; ?>"
                                                    <?php checked($score_val, $val); ?> required>
                                                <span><?php echo esc_html(Olama_School_Helpers::translate($label)); ?></span>
                                            </label>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Save Evaluation', 'olama-school'); ?>
                </button>
            </div>
        </form>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Save Supervisor Evaluation from Modal
     */
    public function save_supervisor_evaluation_modal()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_access_evaluation')) {
            wp_send_json_error(__('Unauthorized.', 'olama-school'));
        }

        parse_str($_POST['data'] ?? '', $data);

        $visit_id = isset($data['visit_id']) ? intval($data['visit_id']) : 0;
        $template_id = isset($data['template_id']) ? intval($data['template_id']) : 0;
        $unit_id = isset($data['unit_id']) ? intval($data['unit_id']) : 0;
        $lesson_id = isset($data['lesson_id']) ? intval($data['lesson_id']) : 0;
        $scores = isset($data['scores']) ? array_map('intval', $data['scores']) : array();
        $supervisor_comments = isset($data['supervisor_comments']) ? sanitize_textarea_field($data['supervisor_comments']) : '';

        if (!$visit_id || !$template_id || empty($scores)) {
            wp_send_json_error(__('Missing required data.', 'olama-school'));
        }

        global $wpdb;

        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, s.semester_id, sec.academic_year_id, s.section_id, s.subject_id
             FROM {$wpdb->prefix}olama_supervisor_visits v
             JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
             JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
             WHERE v.id = %d",
            $visit_id
        ));

        if (!$visit) {
            wp_send_json_error(__('Visit not found.', 'olama-school'));
        }

        $teacher_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ta.teacher_id 
             FROM {$wpdb->prefix}olama_teacher_assignments ta
             JOIN {$wpdb->users} u ON ta.teacher_id = u.ID
             WHERE ta.section_id = %d AND ta.subject_id = %d AND ta.academic_year_id = %d 
             LIMIT 1",
            $visit->section_id,
            $visit->subject_id,
            $visit->academic_year_id
        ));
        if (!$teacher_id)
            $teacher_id = 0;

        // Ensure ev_record exists or create it
        $record = Olama_School_EV_Record::get_evaluation($visit->supervisor_id, $visit->academic_year_id, $visit->semester_id, $template_id, 'supervisor', $visit_id);

        $record_id = 0;
        if (!$record) {
            $record_id = Olama_School_EV_Record::save_evaluation([
                'template_id' => $template_id,
                'teacher_id' => $teacher_id,
                'academic_year_id' => $visit->academic_year_id,
                'semester_id' => $visit->semester_id,
                'context_type' => 'supervisor',
                'related_entity_type' => 'supervisor_visit',
                'related_entity_id' => $visit_id,
                'status' => 'published',
                'supervisor_comments' => $supervisor_comments
            ]);
        } else {
            $record_id = $record->id;
            $wpdb->update("{$wpdb->prefix}olama_ev_records", [
                'status' => 'published',
                'teacher_id' => $teacher_id,
                'supervisor_comments' => $supervisor_comments
            ], ['id' => $record_id]);
        }

        // Calculate and save scores
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
        $score_config = Olama_School_EV_Template::get_score_config($template_id);
        $max_points_in_config = max(array_keys($score_config));

        $total_possible = 0;
        $total_earned = 0;

        // Clear existing scores
        $wpdb->delete("{$wpdb->prefix}olama_ev_scores", ['evaluation_id' => $record_id]);

        foreach ($scores as $indicator_id => $score_val) {
            $indicator = $wpdb->get_row($wpdb->prepare("SELECT weight, max_score FROM {$wpdb->prefix}olama_ev_indicators WHERE id = %d", $indicator_id));
            if ($indicator) {
                // Determine percentage achieved based on raw score vs max_points_in_config
                $percentage = $score_val / $max_points_in_config;
                // Earned = Max Possible for Indicator * Percentage Built * Weight
                $calculated = ($indicator->max_score * $percentage) * $indicator->weight;

                $total_possible += ($indicator->max_score * $indicator->weight);
                $total_earned += $calculated;

                $wpdb->insert("{$wpdb->prefix}olama_ev_scores", [
                    'evaluation_id' => $record_id,
                    'indicator_id' => $indicator_id,
                    'score' => $score_val,
                    'calculated_score' => $calculated
                ]);
            }
        }

        $final_score = ($total_possible > 0) ? ($total_earned / $total_possible) * 100 : 0;

        // Update Visit with new Status, Unit, Lesson, and Final Score
        $wpdb->update("{$wpdb->prefix}olama_supervisor_visits", [
            'unit_id' => $unit_id,
            'lesson_id' => $lesson_id,
            'status' => 'completed',
            'final_score' => $final_score
        ], ['id' => $visit_id]);

        wp_send_json_success();
    }

    /**
     * AJAX: Save Supervisor Assignment
     */
    public function save_supervisor_assignment() {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_supervision_plan')) {
            wp_send_json_error(__('Unauthorized.', 'olama-school'));
        }

        $supervisor_id = intval($_POST['supervisor_id']);
        $grade_id = intval($_POST['grade_id']);
        $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
        $academic_year_id = intval($_POST['academic_year_id']);
        $semester_id = intval($_POST['semester_id']);

        if (!$supervisor_id || !$grade_id || !$academic_year_id || !$semester_id) {
            wp_send_json_error(__('Missing required fields.', 'olama-school'));
        }

        global $wpdb;
        $table = "{$wpdb->prefix}olama_supervisor_assignments";

        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE academic_year_id = %d AND semester_id = %d AND supervisor_id = %d AND grade_id = %d AND (subject_id = %d OR (subject_id IS NULL AND %d IS NULL))",
            $academic_year_id, $semester_id, $supervisor_id, $grade_id, $subject_id, $subject_id
        ));

        if ($exists) {
            wp_send_json_error(__('Assignment already exists.', 'olama-school'));
        }

        $result = $wpdb->insert($table, [
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
            'supervisor_id' => $supervisor_id,
            'grade_id' => $grade_id,
            'subject_id' => $subject_id
        ]);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to save assignment.', 'olama-school'));
        }
    }

    /**
     * AJAX: Delete Supervisor Assignment
     */
    public function delete_supervisor_assignment() {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_manage_supervision_plan')) {
            wp_send_json_error(__('Unauthorized.', 'olama-school'));
        }

        $id = intval($_POST['id']);
        global $wpdb;

        $result = $wpdb->delete("{$wpdb->prefix}olama_supervisor_assignments", ['id' => $id]);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete assignment.', 'olama-school'));
        }
    }
}
