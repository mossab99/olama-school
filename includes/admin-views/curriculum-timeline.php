<?php
/**
 * Curriculum Timeline View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = $active_year ? intval($active_year->id) : 0;
$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$default_semester_id = $active_semester ? intval($active_semester->id) : 0;
?>

<div class="olama-timeline-container">
    <div class="olama-card" style="margin-bottom: 20px; padding: 20px;">
        <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <?php
            $active_year_name = '—';
            $years = Olama_School_Academic::get_years();
            foreach ($years as $year) {
                if ($year->id == $selected_year_id) {
                    $active_year_name = $year->year_name;
                    break;
                }
            }
            $active_semester_name = '—';
            foreach ($semesters as $sem) {
                if ($sem->id == $default_semester_id) {
                    $active_semester_name = $sem->semester_name;
                    break;
                }
            }

            echo Olama_School_Helpers::locked_filter_render(Olama_School_Helpers::translate('Academic Year'), $active_year_name, 'academic_year_id', $selected_year_id);
            echo Olama_School_Helpers::locked_filter_render(Olama_School_Helpers::translate('Semester'), $active_semester_name, 'semester_id', $default_semester_id);
            ?>
            <div style="flex: 1; min-width: 150px; display: none;">
                <select id="timeline-semester" class="olama-select" disabled>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo esc_attr($semester->id); ?>"
                            data-start="<?php echo esc_attr($semester->start_date); ?>"
                            data-end="<?php echo esc_attr($semester->end_date); ?>" <?php selected($default_semester_id, $semester->id); ?>>
                            <?php echo esc_html($semester->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php echo Olama_School_Helpers::translate('Grade'); ?>
                </label>
                <select id="timeline-grade" class="olama-select">
                    <option value="">
                        <?php echo Olama_School_Helpers::translate('Choose Grade...'); ?>
                    </option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo esc_attr($grade->id); ?>">
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php echo Olama_School_Helpers::translate('Subject'); ?>
                </label>
                <select id="timeline-subject" class="olama-select" disabled>
                    <option value="">
                        <?php echo Olama_School_Helpers::translate('Select Grade first...'); ?>
                    </option>
                </select>
            </div>
            <div style="width: auto;">
                <button type="button" id="load-timeline-btn" class="button button-primary button-large" disabled>
                    <?php echo Olama_School_Helpers::translate('Load Timeline'); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="timeline-content" style="display: none;">
        <div class="olama-card" style="padding: 20px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                <h2 id="timeline-title" style="margin: 0;"></h2>
                <div style="display: flex; gap: 10px;">
                    <?php if (Olama_School_Permissions::can('olama_manage_curriculum_timeline')): ?>
                        <button type="button" id="clear-timeline-btn" class="button button-secondary button-large">
                            <?php echo Olama_School_Helpers::translate('Clear All Dates'); ?>
                        </button>
                        <button type="button" id="save-timeline-btn" class="button button-primary button-large">
                            <?php echo Olama_School_Helpers::translate('Save All Dates'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="timeline-grid-container">
                <!-- Timeline items will be rendered here by JS -->
            </div>
        </div>
    </div>
</div>