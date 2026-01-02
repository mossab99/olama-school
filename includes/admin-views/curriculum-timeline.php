<?php
/**
 * Curriculum Timeline View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
?>

<div class="olama-timeline-container">
    <div class="olama-card" style="margin-bottom: 20px; padding: 20px;">
        <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Select Semester', 'olama-school'); ?>
                </label>
                <select id="timeline-semester" class="olama-select">
                    <option value="">
                        <?php _e('Select Semester', 'olama-school'); ?>
                    </option>
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?php echo esc_attr($semester->id); ?>"
                            data-start="<?php echo esc_attr($semester->start_date); ?>"
                            data-end="<?php echo esc_attr($semester->end_date); ?>">
                            <?php echo esc_html($semester->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Select Grade', 'olama-school'); ?>
                </label>
                <select id="timeline-grade" class="olama-select">
                    <option value="">
                        <?php _e('Choose Grade...', 'olama-school'); ?>
                    </option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo esc_attr($grade->id); ?>">
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Select Subject', 'olama-school'); ?>
                </label>
                <select id="timeline-subject" class="olama-select" disabled>
                    <option value="">
                        <?php _e('Select Grade first...', 'olama-school'); ?>
                    </option>
                </select>
            </div>
            <div style="width: auto;">
                <button type="button" id="load-timeline-btn" class="button button-primary button-large" disabled>
                    <?php _e('Load Timeline', 'olama-school'); ?>
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
                    <button type="button" id="clear-timeline-btn" class="button button-secondary button-large">
                        <?php _e('Clear All Dates', 'olama-school'); ?>
                    </button>
                    <button type="button" id="save-timeline-btn" class="button button-primary button-large">
                        <?php _e('Save All Dates', 'olama-school'); ?>
                    </button>
                </div>
            </div>

            <div id="timeline-grid-container">
                <!-- Timeline items will be rendered here by JS -->
            </div>
        </div>
    </div>
</div>