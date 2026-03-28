<?php
/**
 * Shortcode Generator View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$weeks = Olama_School_Academic::get_academic_weeks($selected_year_id);
?>
<div class="olama-card"
    style="max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

    <form method="get" id="olama-shortcode-gen-filters" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="olama-school-plans" />
        <input type="hidden" name="tab" value="shortcode" />
        <?php echo Olama_School_Helpers::academic_year_selector($selected_year_id); ?>
    </form>

    <h2 style="margin-top: 0; color: #1e293b; font-size: 1.5rem; font-weight: 700;">
        <span class="dashicons dashicons-shortcode"
            style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2563eb;"></span>
        <?php _e('Shortcode Generator', 'olama-school'); ?>
    </h2>
    <p style="color: #64748b; margin-bottom: 30px; font-size: 1rem; line-height: 1.5;">
        <?php _e('Configure the options below to generate a custom shortcode for displaying weekly plans. You can paste this shortcode into any post, page, or widget.', 'olama-school'); ?>
    </p>

    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 40px;">
        <div>
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Content Type', 'olama-school'); ?>
            </label>
            <select id="gen-type" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="weekly_plan">
                    <?php _e('Weekly Plan', 'olama-school'); ?>
                </option>
                <option value="weekly_schedule">
                    <?php _e('Weekly Schedule', 'olama-school'); ?>
                </option>
                <option value="teachers_office_hours">
                    <?php _e('Teachers Office Hours', 'olama-school'); ?>
                </option>
                <option value="stationary">
                    <?php _e('Stationary', 'olama-school'); ?>
                </option>
                <option value="exam_report">
                    <?php _e('Exam Report', 'olama-school'); ?>
                </option>
                <option value="attendance">
                    <?php _e('Daily Attendance', 'olama-school'); ?>
                </option>
                <option value="family_performance">
                    <?php _e('Family Performance', 'olama-school'); ?>
                </option>
                <option value="online_exams">
                    <?php echo Olama_School_Helpers::translate('Online Exams'); ?>
                </option>
                <option value="logged_teacher_schedule">
                    <?php _e('Today\'s Teaching Schedule', 'olama-school'); ?>
                </option>
                <option value="logged_user_shifts">
                    <?php _e('My Weekly Shifts', 'olama-school'); ?>
                </option>
                <option value="supervisor_visits">
                    <?php _e('Supervisor Visits', 'olama-school'); ?>
                </option>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Active Semester', 'olama-school'); ?>
            </label>
            <select id="gen-semester"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="active"><?php _e('Active Semester', 'olama-school'); ?></option>
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?php echo $sem->id; ?>">
                        <?php echo esc_html($sem->semester_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Target Grade', 'olama-school'); ?>
            </label>
            <select id="gen-grade" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="">
                    <?php _e('-- Select Grade --', 'olama-school'); ?>
                </option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo $grade->id; ?>">
                        <?php echo esc_html($grade->grade_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Target Section', 'olama-school'); ?>
            </label>
            <select id="gen-section" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;"
                disabled>
                <option value="">
                    <?php _e('-- Select Grade First --', 'olama-school'); ?>
                </option>
            </select>
        </div>
        <div id="gen-week-wrapper">
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Specific Week', 'olama-school'); ?>
            </label>
            <select id="gen-week" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="">
                    <?php _e('-- Current Week --', 'olama-school'); ?>
                </option>
                <option value="previous">
                    <?php _e('-- Previous Week --', 'olama-school'); ?>
                </option>
                <?php foreach ($weeks as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="gen-exam-wrapper" style="display: none;">
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Specific Exam', 'olama-school'); ?>
            </label>
            <select id="gen-exam" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="active"><?php _e('Active Exam', 'olama-school'); ?></option>
                <option value="">
                    <?php _e('-- All Exams --', 'olama-school'); ?>
                </option>
            </select>
        </div>
        <div id="gen-schedule-type-wrapper" style="display: none;">
            <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                <?php _e('Schedule Type', 'olama-school'); ?>
            </label>
            <select id="gen-schedule-type"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                <option value="normal">
                    <?php _e('Normal Schedule', 'olama-school'); ?>
                </option>
                <option value="ramadan">
                    <?php _e('Ramadan Schedule', 'olama-school'); ?>
                </option>
            </select>
        </div>
    </div>

    <div
        style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 2px dashed #e2e8f0; text-align: center;">
        <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 15px; font-size: 1.1rem;">
            <?php _e('Copy & Paste This Code:', 'olama-school'); ?>
        </label>
        <div id="shortcode-display-wrapper" style="position: relative; margin-bottom: 20px;">
            <code id="generated-shortcode"
                style="display: block; font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 1.2rem; background: #fff; padding: 20px 15px; border: 1px solid #cbd5e1; border-radius: 8px; color: #2563eb; overflow-x: auto; white-space: nowrap;">
                [olama_weekly_plan]
            </code>
        </div>
        <button type="button" class="button button-primary button-large" id="copy-shortcode"
            style="height: 46px; padding: 0 30px; font-size: 1rem; font-weight: 600; border-radius: 8px; background: #2563eb;">
            <span class="dashicons dashicons-admin-page" style="margin-top: 10px; margin-right: 5px;"></span>
            <?php _e('Copy to Clipboard', 'olama-school'); ?>
        </button>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        function updateShortcode() {
            var type = $('#gen-type').val();
            var semester = $('#gen-semester').val();
            var grade = $('#gen-grade').val();
            var section = $('#gen-section').val();
            var week = $('#gen-week').val();
            var exam = $('#gen-exam').val();
            var scheduleType = $('#gen-schedule-type').val();
            var activeYearId = '<?php echo $active_year ? $active_year->id : 0; ?>';
            var selectedYearId = $('#academic_year_id').val();

            // Hide/show fields based on type
            if (type === 'stationary') {
                // For stationary, hide all except year (which is in the form above)
                $('#gen-semester').closest('div').hide();
                $('#gen-grade').closest('div').hide();
                $('#gen-section').closest('div').hide();
                $('#gen-week-wrapper').hide();
                $('#gen-exam-wrapper').hide();
            } else if (type === 'exam_report') {
                $('#gen-semester').closest('div').show();
                $('#gen-grade').closest('div').show();
                $('#gen-section').closest('div').hide();
                $('#gen-week-wrapper').hide();
                $('#gen-exam-wrapper').show();
            } else if (type === 'weekly_schedule') {
                $('#gen-semester').closest('div').show();
                $('#gen-grade').closest('div').show();
                $('#gen-section').closest('div').show();
                $('#gen-week-wrapper').hide();
                $('#gen-exam-wrapper').hide();
                $('#gen-schedule-type-wrapper').show();
            } else if (type === 'teachers_office_hours' || type === 'attendance') {
                $('#gen-semester').closest('div').show();
                $('#gen-grade').closest('div').show();
                $('#gen-section').closest('div').show();
                $('#gen-week-wrapper').hide();
                $('#gen-exam-wrapper').hide();
                $('#gen-schedule-type-wrapper').hide();
            } else if (type === 'family_performance' || type === 'online_exams' || type === 'logged_teacher_schedule' || type === 'logged_user_shifts' || type === 'supervisor_visits') {
                $('#gen-semester').closest('div').show();
                $('#gen-grade').closest('div').hide();
                $('#gen-section').closest('div').hide();
                $('#gen-week-wrapper').hide();
                $('#gen-exam-wrapper').hide();
                $('#gen-schedule-type-wrapper').hide();
            } else {
                $('#gen-semester').closest('div').show();
                $('#gen-grade').closest('div').show();
                $('#gen-section').closest('div').show();
                $('#gen-week-wrapper').show();
                $('#gen-exam-wrapper').hide();
                $('#gen-schedule-type-wrapper').hide();
            }

            // Generate shortcode based on type
            var shortcode = '[olama_' + type;

            if (selectedYearId == activeYearId) {
                shortcode += ' year="active"';
            } else if (selectedYearId) {
                shortcode += ' year="' + selectedYearId + '"';
            }

            if (type !== 'stationary') {
                if (type === 'family_performance' || type === 'online_exams' || type === 'logged_teacher_schedule' || type === 'logged_user_shifts') {
                    if (semester && (type === 'family_performance' || type === 'online_exams')) shortcode += ' semester="' + semester + '"';
                } else {
                    if (semester) shortcode += ' semester="' + semester + '"';
                    if (grade) shortcode += ' grade="' + grade + '"';

                    if (type === 'exam_report') {
                        if (exam) shortcode += ' exam="' + exam + '"';
                    } else {
                        if (section) shortcode += ' section="' + section + '"';
                        if (type === 'weekly_plan' && week) shortcode += ' week="' + week + '"';
                        if (type === 'weekly_schedule' && scheduleType && scheduleType !== 'normal') {
                            shortcode += ' schedule_type="' + scheduleType + '"';
                        }
                    }
                }
            }
            shortcode += ']';

            $('#generated-shortcode').text(shortcode);
        }

        $('#gen-type').on('change', updateShortcode);

        $('#gen-grade').on('change', function () {
            var gradeId = $(this).val();
            var $sectionSelect = $('#gen-section');

            if (!gradeId) {
                $sectionSelect.html('<option value=""><?php _e('-- Select Grade First --', 'olama-school'); ?></option>').prop('disabled', true);
                updateShortcode();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'olama_get_sections_by_grade',
                    grade_id: gradeId,
                    nonce: '<?php echo wp_create_nonce("olama_curriculum_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var options = '<option value=""><?php _e('-- All Sections --', 'olama-school'); ?></option>';
                        $.each(response.data, function (i, section) {
                            options += '<option value="' + section.id + '">' + section.section_name + '</option>';
                        });
                        $sectionSelect.html(options).prop('disabled', false);
                    } else {
                        $sectionSelect.html('<option value=""><?php _e('No sections found', 'olama-school'); ?></option>').prop('disabled', true);
                    }
                    updateShortcode();
                }
            });
        });

        $('#gen-semester').on('change', function () {
            var semesterId = $(this).val();
            var $examSelect = $('#gen-exam');
            var type = $('#gen-type').val();

            if (type !== 'exam_report' || !semesterId || semesterId === 'active') {
                $examSelect.html('<option value="active"><?php _e('Active Exam', 'olama-school'); ?></option><option value=""><?php _e('-- All Exams --', 'olama-school'); ?></option>');
                updateShortcode();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'olama_get_semester_exams',
                    semester_id: semesterId,
                    nonce: '<?php echo wp_create_nonce("olama_curriculum_nonce"); ?>'
                },
                success: function (response) {
                    var options = '<option value="active"><?php _e('Active Exam', 'olama-school'); ?></option>';
                    options += '<option value=""><?php _e('-- All Exams --', 'olama-school'); ?></option>';
                    if (response.success && response.data) {
                        $.each(response.data, function (i, exam) {
                            options += '<option value="' + exam.id + '">' + exam.exam_name + '</option>';
                        });
                    }
                    $examSelect.html(options);
                    updateShortcode();
                }
            });
        });

        $('#gen-section, #gen-week, #gen-exam, #gen-schedule-type').on('change', updateShortcode);

        $('#copy-shortcode').on('click', function () {
            var text = $('#generated-shortcode').text().trim();
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();

            var $btn = $(this);
            var originalContent = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 10px; margin-right: 5px;"></span> <?php _e('Copied!', 'olama-school'); ?>');
            $btn.css('background', '#10b981');

            setTimeout(function () {
                $btn.html(originalContent);
                $btn.css('background', '#2563eb');
            }, 2000);
        });

        updateShortcode();
    });
</script>