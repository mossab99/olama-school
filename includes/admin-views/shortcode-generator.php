<?php
/**
 * Shortcode Generator View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id'])
    ? absint($_GET['academic_year_id'])
    : ($active_year ? (int) $active_year->id : 0);
$selected_year = $selected_year_id ? Olama_School_Academic::get_year($selected_year_id) : null;

if (!$selected_year && $active_year) {
    $selected_year_id = (int) $active_year->id;
    $selected_year = $active_year;
}

$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$active_semester = ($active_year && (int) $active_year->id === $selected_year_id)
    ? Olama_School_Academic::get_active_semester($selected_year_id)
    : null;
$default_semester_id = $active_semester
    ? (int) $active_semester->id
    : (!empty($semesters) ? (int) $semesters[0]->id : 0);
$weeks_by_semester = array();
foreach ($semesters as $semester) {
    $weeks_by_semester[(int) $semester->id] = Olama_School_Academic::get_academic_weeks(
        $selected_year_id,
        (int) $semester->id
    );
}

$field_style = 'width:100%;border:1px solid #e2e8f0;border-radius:6px;padding:10px;';
$label_style = 'display:block;font-weight:600;color:#475569;margin-bottom:8px;font-size:.9rem;';
?>
<div class="olama-card" style="max-width:800px;margin:0 auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);">
    <h2 style="margin-top:0;color:#1e293b;font-size:1.5rem;font-weight:700;">
        <span class="dashicons dashicons-shortcode" style="font-size:24px;width:24px;height:24px;margin-right:10px;color:#2563eb;"></span>
        <?php esc_html_e('Shortcode Generator', 'olama-school'); ?>
    </h2>
    <p style="color:#64748b;margin-bottom:24px;font-size:1rem;line-height:1.5;">
        <?php esc_html_e('Configure the options below to generate a custom shortcode for displaying weekly plans. You can paste this shortcode into any post, page, or widget.', 'olama-school'); ?>
    </p>

    <form method="get" id="olama-shortcode-gen-filters" style="margin-bottom:25px;">
        <input type="hidden" name="page" value="olama-school-settings" />
        <input type="hidden" name="tab" value="shortcode" />
        <?php echo Olama_School_Helpers::academic_year_selector($selected_year_id); ?>
    </form>

    <div id="olama-shortcode-fields" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:25px;margin-bottom:40px;">
        <div>
            <label for="gen-type" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Content Type', 'olama-school'); ?></label>
            <select id="gen-type" style="<?php echo esc_attr($field_style); ?>">
                <option value="weekly_plan"><?php esc_html_e('Weekly Plan', 'olama-school'); ?></option>
                <option value="weekly_schedule"><?php esc_html_e('Weekly Schedule', 'olama-school'); ?></option>
                <option value="teachers_office_hours"><?php esc_html_e('Teachers Office Hours', 'olama-school'); ?></option>
                <option value="stationary"><?php esc_html_e('Stationary', 'olama-school'); ?></option>
                <option value="logged_teacher_schedule"><?php esc_html_e('Today\'s Teaching Schedule', 'olama-school'); ?></option>
            </select>
        </div>

        <div id="gen-semester-wrapper">
            <label for="gen-semester" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Semester', 'olama-school'); ?></label>
            <select id="gen-semester" style="<?php echo esc_attr($field_style); ?>">
                <?php if (empty($semesters)): ?>
                    <option value=""><?php esc_html_e('No semesters found', 'olama-school'); ?></option>
                <?php else: ?>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo esc_attr($semester->id); ?>" <?php selected($default_semester_id, (int) $semester->id); ?>>
                            <?php echo esc_html($semester->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div id="gen-grade-wrapper">
            <label for="gen-grade" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Target Grade', 'olama-school'); ?></label>
            <select id="gen-grade" style="<?php echo esc_attr($field_style); ?>">
                <option value=""><?php esc_html_e('-- Select Grade --', 'olama-school'); ?></option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo esc_attr($grade->id); ?>"><?php echo esc_html($grade->grade_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="gen-section-wrapper">
            <label for="gen-section" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Target Section', 'olama-school'); ?></label>
            <select id="gen-section" style="<?php echo esc_attr($field_style); ?>" disabled>
                <option value=""><?php esc_html_e('-- Select Grade First --', 'olama-school'); ?></option>
            </select>
        </div>

        <div id="gen-week-wrapper">
            <label for="gen-week" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Specific Week', 'olama-school'); ?></label>
            <select id="gen-week" style="<?php echo esc_attr($field_style); ?>"></select>
        </div>

        <div id="gen-schedule-type-wrapper" style="display:none;">
            <label for="gen-schedule-type" style="<?php echo esc_attr($label_style); ?>"><?php esc_html_e('Schedule Type', 'olama-school'); ?></label>
            <select id="gen-schedule-type" style="<?php echo esc_attr($field_style); ?>">
                <option value="normal"><?php esc_html_e('Normal Schedule', 'olama-school'); ?></option>
                <option value="ramadan"><?php esc_html_e('Ramadan Schedule', 'olama-school'); ?></option>
            </select>
        </div>
    </div>

    <div style="background:#f8fafc;padding:25px;border-radius:12px;border:2px dashed #e2e8f0;text-align:center;">
        <label style="display:block;font-weight:700;color:#1e293b;margin-bottom:15px;font-size:1.1rem;">
            <?php esc_html_e('Copy & Paste This Code:', 'olama-school'); ?>
        </label>
        <code id="generated-shortcode" style="display:block;font-family:'JetBrains Mono','Courier New',monospace;font-size:1.2rem;background:#fff;padding:20px 15px;border:1px solid #cbd5e1;border-radius:8px;color:#2563eb;overflow-x:auto;white-space:nowrap;">[olama_weekly_plan]</code>
        <button type="button" class="button button-primary button-large" id="copy-shortcode" style="height:46px;padding:0 30px;margin-top:20px;font-size:1rem;font-weight:600;border-radius:8px;background:#2563eb;">
            <span class="dashicons dashicons-admin-page" style="margin-top:10px;margin-right:5px;"></span>
            <?php esc_html_e('Copy to Clipboard', 'olama-school'); ?>
        </button>
    </div>
</div>

<script>
jQuery(function ($) {
    const selectedYearId = <?php echo wp_json_encode($selected_year_id); ?>;
    const activeSemesterId = <?php echo wp_json_encode($active_semester ? (int) $active_semester->id : 0); ?>;
    const weeksBySemester = <?php echo wp_json_encode($weeks_by_semester); ?>;
    const strings = {
        currentWeek: <?php echo wp_json_encode(__('Current Week', 'olama-school')); ?>,
        previousWeek: <?php echo wp_json_encode(__('Previous Week', 'olama-school')); ?>,
        selectGradeFirst: <?php echo wp_json_encode(__('-- Select Grade First --', 'olama-school')); ?>,
        selectSection: <?php echo wp_json_encode(__('-- Select Section --', 'olama-school')); ?>,
        noSections: <?php echo wp_json_encode(__('No sections found', 'olama-school')); ?>,
        copied: <?php echo wp_json_encode(__('Copied!', 'olama-school')); ?>
    };

    function addOption($select, value, label) {
        $('<option>').val(value).text(label).appendTo($select);
    }

    function refreshWeeks() {
        const semesterId = $('#gen-semester').val();
        const weeks = weeksBySemester[semesterId] || {};
        const $week = $('#gen-week').empty();
        if (activeSemesterId && Number(semesterId) === Number(activeSemesterId)) {
            addOption($week, '', '-- ' + strings.currentWeek + ' --');
            addOption($week, 'previous', '-- ' + strings.previousWeek + ' --');
        }
        $.each(weeks, function (date, label) {
            addOption($week, date, label);
        });
    }

    function updateShortcode() {
        const type = $('#gen-type').val();
        const usesAcademicContext = type !== 'logged_teacher_schedule';
        const usesSemester = type === 'weekly_plan' || type === 'weekly_schedule' || type === 'teachers_office_hours';
        const usesClass = usesSemester;

        $('#olama-shortcode-gen-filters').toggle(usesAcademicContext);
        $('#gen-semester-wrapper').toggle(usesSemester);
        $('#gen-grade-wrapper, #gen-section-wrapper').toggle(usesClass);
        $('#gen-week-wrapper').toggle(type === 'weekly_plan');
        $('#gen-schedule-type-wrapper').toggle(type === 'weekly_schedule');

        let shortcode = '[olama_' + type;
        if (usesAcademicContext && selectedYearId) shortcode += ' year="' + selectedYearId + '"';
        if (usesSemester && $('#gen-semester').val()) shortcode += ' semester="' + $('#gen-semester').val() + '"';
        if (usesClass && $('#gen-grade').val()) shortcode += ' grade="' + $('#gen-grade').val() + '"';
        if (usesClass && $('#gen-section').val()) shortcode += ' section="' + $('#gen-section').val() + '"';
        if (type === 'weekly_plan' && $('#gen-week').val()) shortcode += ' week="' + $('#gen-week').val() + '"';
        if (type === 'weekly_schedule' && $('#gen-schedule-type').val() !== 'normal') {
            shortcode += ' schedule_type="' + $('#gen-schedule-type').val() + '"';
        }
        $('#generated-shortcode').text(shortcode + ']');
    }

    $('#gen-type').on('change', updateShortcode);
    $('#gen-semester').on('change', function () {
        refreshWeeks();
        updateShortcode();
    });
    $('#gen-grade').on('change', function () {
        const gradeId = $(this).val();
        const $section = $('#gen-section').empty().prop('disabled', true);
        if (!gradeId) {
            addOption($section, '', strings.selectGradeFirst);
            updateShortcode();
            return;
        }

        addOption($section, '', strings.selectSection);
        $.post(ajaxurl, {
            action: 'olama_get_sections_by_grade',
            grade_id: gradeId,
            academic_year_id: selectedYearId,
            nonce: <?php echo wp_json_encode(wp_create_nonce('olama_curriculum_nonce')); ?>
        }).done(function (response) {
            $section.empty();
            if (response.success && response.data && response.data.length) {
                addOption($section, '', strings.selectSection);
                $.each(response.data, function (_, section) {
                    addOption($section, section.id, section.section_name);
                });
                $section.prop('disabled', false);
            } else {
                addOption($section, '', strings.noSections);
            }
            updateShortcode();
        }).fail(function () {
            $section.empty();
            addOption($section, '', strings.noSections);
            updateShortcode();
        });
    });
    $('#gen-section, #gen-week, #gen-schedule-type').on('change', updateShortcode);

    $('#copy-shortcode').on('click', function () {
        const text = $('#generated-shortcode').text().trim();
        const copy = navigator.clipboard && navigator.clipboard.writeText
            ? navigator.clipboard.writeText(text)
            : Promise.reject();

        copy.catch(function () {
            const $temp = $('<input>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
        }).finally(function () {
            const $button = $('#copy-shortcode');
            const original = $button.html();
            $button.html('<span class="dashicons dashicons-yes" style="margin-top:10px;margin-right:5px;"></span> ' + strings.copied).css('background', '#10b981');
            setTimeout(function () {
                $button.html(original).css('background', '#2563eb');
            }, 2000);
        });
    });

    refreshWeeks();
    updateShortcode();
});
</script>
