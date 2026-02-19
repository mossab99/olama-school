<?php
/**
 * Curriculum Management - Units, Lessons, Questions
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$academic_years = Olama_School_Academic::get_years();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = $active_year ? intval($active_year->id) : 0;
$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
?>

<?php
if ($import_message = get_transient('olama_import_message')) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($import_message) . '</p></div>';
    delete_transient('olama_import_message');
}
if ($import_error = get_transient('olama_import_error')) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($import_error) . '</p></div>';
    delete_transient('olama_import_error');
}
?>

<!-- Filters Section -->
<div class="olama-card" style="margin-bottom: 20px;">
    <div class="olama-filter-row">
        <?php
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $default_semester_id = $active_semester ? $active_semester->id : 0;

        // Find names for locked display
        $active_year_name = '—';
        foreach ($academic_years as $year) {
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
        <div class="olama-filter-item">
            <label><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
            <select id="curriculum-grade" class="olama-select">
                <option value="">
                    <?php echo Olama_School_Helpers::translate('-- Select Grade --'); ?>
                </option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo $grade->id; ?>">
                        <?php echo esc_html($grade->grade_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="olama-filter-item">
            <label><?php echo Olama_School_Helpers::translate('Subject'); ?></label>
            <select id="curriculum-subject" class="olama-select">
                <!-- Populated via JS -->
            </select>
        </div>
    </div>

    <!-- Export / Import / Delete Section -->
    <div
        style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; align-items: center; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px;">
        <form method="post" id="olama-export-curriculum-form" style="margin: 0; flex-shrink: 0;">
            <?php wp_nonce_field('olama_export_curriculum'); ?>
            <input type="hidden" name="olama_export_curriculum" value="true">
            <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
            <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
            <input type="hidden" name="subject_id" class="curriculum-hidden-subject">
            <button type="submit" class="button button-secondary" id="olama-export-curriculum-btn" disabled
                style="white-space: nowrap;">
                <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Export Curriculum CSV'); ?>
            </button>
        </form>

        <form method="post" enctype="multipart/form-data" id="olama-import-curriculum-form"
            style="margin: 0; display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
            <?php wp_nonce_field('olama_import_curriculum'); ?>
            <input type="hidden" name="olama_import_type" value="curriculum">
            <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
            <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
            <input type="hidden" name="subject_id" class="curriculum-hidden-subject">

            <input type="file" name="olama_import_file" accept=".csv" required style="max-width: 150px;"
                id="olama-import-curriculum-file" disabled>

            <button type="submit" class="button button-primary" id="olama-import-curriculum-btn" disabled
                style="white-space: nowrap;">
                <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Import Curriculum CSV'); ?>
            </button>
        </form>

        <button type="button" class="button button-link-delete" id="olama-clear-curriculum-btn" disabled
            style="color: #dc2626; flex-shrink: 0; white-space: nowrap;">
            <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
            <?php echo Olama_School_Helpers::translate('Clear Curriculum'); ?>
        </button>

        <button type="button" class="button button-link-delete" id="olama-clear-grade-curriculum-btn" disabled
            style="color: #b91c1c; font-weight: 600; flex-shrink: 0; white-space: nowrap;">
            <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
            <?php echo Olama_School_Helpers::translate('Clear Grade Curriculum'); ?>
        </button>

        <button type="button" class="button button-primary" id="olama-force-clear-all-curriculum-btn"
            style="background: #dc2626; border-color: #dc2626; font-weight: 800; color: white; flex-shrink: 0; white-space: nowrap;">
            <span class="dashicons dashicons-warning" style="margin-top: 4px;"></span>
            <?php echo Olama_School_Helpers::translate('FORCE DELETE EVERYTHING'); ?>
        </button>
    </div>
    <p class="description" style="margin-top: 10px; font-size: 11px; color: #64748b;">
        <?php echo Olama_School_Helpers::translate('Select Semester, Grade, and Subject to enable Export/Import.'); ?> |
        <strong><?php echo Olama_School_Helpers::translate('Note: "Force Delete Everything" only deletes curriculum for the selected year.'); ?></strong>
    </p>
</div>

<div class="curriculum-grid"
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    <!-- Section 2: Units -->
    <div class="olama-card section-container" id="unit-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 1.2em;">
                <?php echo Olama_School_Helpers::translate('1. Units'); ?>
            </h2>
            <button type="button" id="add-unit-btn" class="button button-small add-unit-btn">
                <?php echo Olama_School_Helpers::translate('+ Add Unit'); ?>
            </button>
        </div>

        <div id="unit-form-container"
            style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <input type="hidden" id="unit-id" value="">
            <div style="margin-bottom: 10px;">
                <input type="number" id="unit-number"
                    placeholder="<?php echo Olama_School_Helpers::translate('Unit #'); ?>" style="width: 100%;"
                    required>
            </div>
            <div style="margin-bottom: 10px;">
                <input type="text" id="unit-name"
                    placeholder="<?php echo Olama_School_Helpers::translate('Unit Name'); ?>" style="width: 100%;"
                    required>
            </div>
            <div style="margin-bottom: 10px;">
                <textarea id="unit-objectives"
                    placeholder="<?php echo Olama_School_Helpers::translate('Learning Objectives'); ?>"
                    style="width: 100%; height: 60px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary save-unit-btn">
                    <?php echo Olama_School_Helpers::translate('Save Unit'); ?>
                </button>
                <button type="button" class="button cancel-unit-btn">
                    <?php echo Olama_School_Helpers::translate('Cancel'); ?>
                </button>
            </div>
        </div>

        <div id="units-list" class="item-list">
            <p style="color: #999; text-align: center; padding: 20px;">
                <?php echo Olama_School_Helpers::translate('Select Subject to see units.'); ?>
            </p>
        </div>
    </div>

    <!-- Section 3: Lessons -->
    <div class="olama-card section-container" id="lesson-section" style="opacity: 0.5; pointer-events: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 1.2em;">
                <?php echo Olama_School_Helpers::translate('2. Lessons'); ?>
            </h2>
            <button type="button" id="add-lesson-btn" class="button button-small add-lesson-btn">
                <?php echo Olama_School_Helpers::translate('+ Add Lesson'); ?>
            </button>
        </div>

        <div id="lesson-form-container"
            style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <input type="hidden" id="lesson-id" value="">
            <div style="margin-bottom: 10px;">
                <input type="number" id="lesson-number"
                    placeholder="<?php echo Olama_School_Helpers::translate('Lesson #'); ?>" style="width: 100%;"
                    required>
            </div>
            <div style="margin-bottom: 10px;">
                <input type="text" id="lesson-title"
                    placeholder="<?php echo Olama_School_Helpers::translate('Lesson Title'); ?>" style="width: 100%;"
                    required>
            </div>
            <div style="margin-bottom: 10px;">
                <input type="url" id="lesson-url"
                    placeholder="<?php echo Olama_School_Helpers::translate('Video URL'); ?>" style="width: 100%;">
            </div>
            <div style="margin-bottom: 10px;">
                <input type="number" id="lesson-periods"
                    placeholder="<?php echo Olama_School_Helpers::translate('Number of Periods'); ?>"
                    style="width: 100%;" min="1" value="1" required>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary save-lesson-btn">
                    <?php echo Olama_School_Helpers::translate('Save Lesson'); ?>
                </button>
                <button type="button" class="button cancel-lesson-btn">
                    <?php echo Olama_School_Helpers::translate('Cancel'); ?>
                </button>
            </div>
        </div>

        <div id="lessons-list" class="item-list">
            <p style="color: #999; text-align: center; padding: 20px;">
                <?php echo Olama_School_Helpers::translate('Select Unit to see lessons.'); ?>
            </p>
        </div>
    </div>

    <!-- Section 4: Question Bank -->
    <div class="olama-card section-container" id="question-section" style="opacity: 0.5; pointer-events: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 1.2em;">
                <?php echo Olama_School_Helpers::translate('3. Question Bank'); ?>
            </h2>
            <button type="button" id="add-question-btn" class="button button-small add-question-btn">
                <?php echo Olama_School_Helpers::translate('+ Add Question'); ?>
            </button>
        </div>

        <div id="question-form-container"
            style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <input type="hidden" id="question-id" value="">
            <div style="margin-bottom: 10px;">
                <input type="number" id="question-number"
                    placeholder="<?php echo Olama_School_Helpers::translate('Question #'); ?>" style="width: 100%;"
                    required>
            </div>
            <div style="margin-bottom: 10px;">
                <textarea id="question-text" placeholder="<?php echo Olama_School_Helpers::translate('Question'); ?>"
                    style="width: 100%; height: 60px;" required></textarea>
            </div>
            <div style="margin-bottom: 10px;">
                <textarea id="question-answer"
                    placeholder="<?php echo Olama_School_Helpers::translate('Suggested Answer'); ?>"
                    style="width: 100%; height: 60px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary save-question-btn">
                    <?php echo Olama_School_Helpers::translate('Save Question'); ?>
                </button>
                <button type="button" class="button cancel-question-btn">
                    <?php echo Olama_School_Helpers::translate('Cancel'); ?>
                </button>
            </div>
        </div>

        <div id="questions-list" class="item-list">
            <p style="color: #999; text-align: center; padding: 20px;">
                <?php echo Olama_School_Helpers::translate('Select Lesson to see questions.'); ?>
            </p>
        </div>
    </div>
</div>
</div>

<style>
    .section-container {
        height: 600px;
        display: flex;
        flex-direction: column;
    }

    .item-list {
        flex: 1;
        overflow-y: auto;
        border: 1px solid #eee;
        border-radius: 4px;
        padding: 10px;
    }

    .curriculum-item {
        padding: 10px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        position: relative;
        transition: background 0.2s;
    }

    .curriculum-item:hover {
        background: #f5faff;
    }

    .curriculum-item.active {
        background: #e6f3ff;
        border-left: 3px solid #2271b1;
    }

    .item-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: none;
    }

    .curriculum-item:hover .item-actions {
        display: block;
    }

    .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9em;
        color: #555;
    }

    .olama-select {
        width: 100%;
        height: 35px;
    }
</style>