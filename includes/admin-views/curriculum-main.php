<?php
/**
 * Curriculum Management - Units, Lessons, Questions
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
?>

<div class="wrap olama-school-wrap">
    <h1>
        <?php _e('Curriculum Management', 'olama-school'); ?>
    </h1>

    <!-- Section 1: Filters -->
    <div class="olama-card" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Semester', 'olama-school'); ?>
                </label>
                <select id="curriculum-semester" class="olama-select">
                    <option value="">
                        <?php _e('-- Select Semester --', 'olama-school'); ?>
                    </option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem->id; ?>">
                            <?php echo esc_html($sem->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Grade', 'olama-school'); ?>
                </label>
                <select id="curriculum-grade" class="olama-select">
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
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php _e('Subject', 'olama-school'); ?>
                </label>
                <select id="curriculum-subject" class="olama-select">
                    <!-- Populated via JS -->
                </select>
            </div>
        </div>

        <!-- Export / Import Section -->
        <div
            style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <form method="post" id="olama-export-curriculum-form" style="margin: 0;">
                <?php wp_nonce_field('olama_export_curriculum'); ?>
                <input type="hidden" name="olama_export_curriculum" value="true">
                <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
                <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
                <input type="hidden" name="subject_id" class="curriculum-hidden-subject">
                <button type="submit" class="button button-secondary" id="olama-export-curriculum-btn" disabled>
                    <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                    <?php _e('Export Curriculum CSV', 'olama-school'); ?>
                </button>
            </form>

            <div style="height: 24px; width: 1px; background: #cbd5e1; display: inline-block;"></div>

            <form method="post" enctype="multipart/form-data" id="olama-import-curriculum-form"
                style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <?php wp_nonce_field('olama_import_curriculum'); ?>
                <input type="hidden" name="olama_import_type" value="curriculum">
                <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
                <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
                <input type="hidden" name="subject_id" class="curriculum-hidden-subject">

                <input type="file" name="olama_import_file" accept=".csv" required style="max-width: 200px;"
                    id="olama-import-curriculum-file" disabled>

                <button type="submit" class="button button-primary" id="olama-import-curriculum-btn" disabled>
                    <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                    <?php _e('Import Curriculum CSV', 'olama-school'); ?>
                </button>
            </form>

            <p class="description" style="margin: 0; font-size: 11px; color: #64748b;">
                <?php _e('Select Semester, Grade, and Subject to enable Export/Import.', 'olama-school'); ?>
            </p>
        </div>
    </div>

    <div class="curriculum-grid"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <!-- Section 2: Units -->
        <div class="olama-card section-container" id="unit-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 1.2em;">
                    <?php _e('1. Units', 'olama-school'); ?>
                </h2>
                <button type="button" id="add-unit-btn" class="button button-small add-unit-btn">
                    <?php _e('+ Add Unit', 'olama-school'); ?>
                </button>
            </div>

            <div id="unit-form-container"
                style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <input type="hidden" id="unit-id" value="">
                <div style="margin-bottom: 10px;">
                    <input type="number" id="unit-number" placeholder="Unit #" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <input type="text" id="unit-name" placeholder="Unit Name" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <textarea id="unit-objectives" placeholder="Learning Objectives"
                        style="width: 100%; height: 60px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary save-unit-btn">
                        <?php _e('Save Unit', 'olama-school'); ?>
                    </button>
                    <button type="button" class="button cancel-unit-btn">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                </div>
            </div>

            <div id="units-list" class="item-list">
                <p style="color: #999; text-align: center; padding: 20px;">
                    <?php _e('Select Subject to see units.', 'olama-school'); ?>
                </p>
            </div>
        </div>

        <!-- Section 3: Lessons -->
        <div class="olama-card section-container" id="lesson-section" style="opacity: 0.5; pointer-events: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 1.2em;">
                    <?php _e('2. Lessons', 'olama-school'); ?>
                </h2>
                <button type="button" id="add-lesson-btn" class="button button-small add-lesson-btn">
                    <?php _e('+ Add Lesson', 'olama-school'); ?>
                </button>
            </div>

            <div id="lesson-form-container"
                style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <input type="hidden" id="lesson-id" value="">
                <div style="margin-bottom: 10px;">
                    <input type="number" id="lesson-number" placeholder="Lesson #" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <input type="text" id="lesson-title" placeholder="Lesson Title" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <input type="url" id="lesson-url" placeholder="Video URL" style="width: 100%;">
                </div>
                <div style="margin-bottom: 10px;">
                    <input type="number" id="lesson-periods" placeholder="Number of Periods" style="width: 100%;"
                        min="1" value="1" required>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary save-lesson-btn">
                        <?php _e('Save Lesson', 'olama-school'); ?>
                    </button>
                    <button type="button" class="button cancel-lesson-btn">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                </div>
            </div>

            <div id="lessons-list" class="item-list">
                <p style="color: #999; text-align: center; padding: 20px;">
                    <?php _e('Select Unit to see lessons.', 'olama-school'); ?>
                </p>
            </div>
        </div>

        <!-- Section 4: Question Bank -->
        <div class="olama-card section-container" id="question-section" style="opacity: 0.5; pointer-events: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 1.2em;">
                    <?php _e('3. Question Bank', 'olama-school'); ?>
                </h2>
                <button type="button" id="add-question-btn" class="button button-small add-question-btn">
                    <?php _e('+ Add Question', 'olama-school'); ?>
                </button>
            </div>

            <div id="question-form-container"
                style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <input type="hidden" id="question-id" value="">
                <div style="margin-bottom: 10px;">
                    <input type="number" id="question-number" placeholder="Question #" style="width: 100%;" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <textarea id="question-text" placeholder="Question" style="width: 100%; height: 60px;"
                        required></textarea>
                </div>
                <div style="margin-bottom: 10px;">
                    <textarea id="question-answer" placeholder="Suggested Answer"
                        style="width: 100%; height: 60px;"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="button button-primary save-question-btn">
                        <?php _e('Save Question', 'olama-school'); ?>
                    </button>
                    <button type="button" class="button cancel-question-btn">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                </div>
            </div>

            <div id="questions-list" class="item-list">
                <p style="color: #999; text-align: center; padding: 20px;">
                    <?php _e('Select Lesson to see questions.', 'olama-school'); ?>
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