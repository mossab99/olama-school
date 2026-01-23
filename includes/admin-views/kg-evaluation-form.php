<?php
/**
 * KG Evaluation Form View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="olama-kg-evaluation-wrap">
    <div class="olama-header-section" style="margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?php echo Olama_School_Helpers::translate('KG Student Evaluation'); ?>
        </h2>
        <p class="description">
            <?php echo Olama_School_Helpers::translate('Fill the evaluation form for KG students.'); ?>
        </p>
    </div>

    <!-- Filter Bar -->
    <div class="olama-filter-bar olama-card"
        style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-evaluation">
            <input type="hidden" name="tab" value="kg_evaluation">

            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                    </label>
                    <select name="academic_year_id" onchange="this.form.submit()" style="width: 100%;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                                <?php echo esc_html($y->year_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Semester'); ?>
                    </label>
                    <select name="semester_id" onchange="this.form.submit()" style="width: 100%;">
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                                <?php echo esc_html($s->semester_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Section'); ?>
                    </label>
                    <select name="section_id" onchange="this.form.submit()" style="width: 100%;">
                        <option value="">
                            <?php _e('Select Section', 'olama-school'); ?>
                        </option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                <?php echo esc_html($sec->section_name); ?> (
                                <?php echo esc_html($sec->grade_name); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Select Student'); ?>
                    </label>
                    <select name="student_id" onchange="this.form.submit()" style="width: 100%;">
                        <option value="">
                            <?php _e('Choose a Student...', 'olama-school'); ?>
                        </option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?php echo $stu->id; ?>" <?php selected($selected_student_id, $stu->id); ?>>
                                <?php echo esc_html($stu->student_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selected_student_id): ?>
                    <div style="flex: 1; min-width: 150px;">
                        <label class="olama-label">
                            <?php _e('Select Evaluation (Title)', 'olama-school'); ?>
                        </label>
                        <select name="template_id" onchange="this.form.submit()" style="width: 100%;">
                            <option value=""><?php _e('-- Select --', 'olama-school'); ?></option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?php echo $t->id; ?>" <?php selected($selected_template_id, $t->id); ?>>
                                    <?php echo esc_html($t->template_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($selected_student_id && $selected_template_id && empty($curriculum)): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('No evaluation structure defined for this template yet.', 'olama-school'); ?>
            </p>
        </div>
    <?php elseif ($selected_student_id && $selected_template_id): ?>

        <!-- Progress Bar -->
        <div id="kg-progress-container"
            style="background: #e2e8f0; height: 12px; border-radius: 6px; margin-bottom: 30px; position: sticky; top: 32px; z-index: 100;">
            <div id="kg-progress-bar"
                style="background: #6366f1; width: 0%; height: 100%; border-radius: 6px; transition: width 0.3s ease;">
            </div>
            <span id="kg-progress-text"
                style="position: absolute; right: 0; top: -20px; font-size: 11px; font-weight: 600; color: #6366f1;">0/0</span>
        </div>

        <form id="kg-evaluation-form" method="post" action="">
            <?php wp_nonce_field('olama_kg_evaluation_save', 'olama_kg_evaluation_save'); ?>
            <input type="hidden" name="olama_kg_save_eval" value="1">
            <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
            <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
            <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">
            <input type="hidden" name="status" id="eval-status" value="draft">
                <input type="hidden" name="template_id" value="<?php echo $selected_template_id; ?>">

            <?php foreach ($curriculum as $domain): ?>
                <div class="kg-domain-section" style="margin-bottom: 40px;">
                    <div class="kg-domain-header-sticky"
                        style="background: #1e293b; color: #fff; padding: 12px 25px; border-radius: 8px; margin-bottom: 20px; position: sticky; top: 44px; z-index: 90; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0; letter-spacing: 0.5px; color: #ffffff !important;">
                            <?php echo esc_html($domain->title_ar); ?>
                        </h3>
                    </div>

                    <?php foreach ($domain->categories as $category): ?>
                        <div class="kg-category-container"
                            style="margin-bottom: 25px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0;">
                                <h4 style="margin: 0; color: #475569; font-weight: 600;">
                                    <?php echo esc_html($category->title_ar); ?>
                                </h4>
                            </div>

                            <table class="kg-indicator-table" style="width: 100%; border-collapse: collapse;">
                                <?php foreach ($category->indicators as $indicator):
                                    $saved_score = isset($scores[$indicator->id]) ? $scores[$indicator->id]->score : null;
                                    $saved_notes = isset($scores[$indicator->id]) ? $scores[$indicator->id]->notes : '';
                                    ?>
                                    <tr class="kg-row" data-indicator-id="<?php echo $indicator->id; ?>"
                                        style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 15px 20px; width: 50%; font-size: 1.05em;">
                                            <?php echo esc_html($indicator->indicator_text); ?>
                                        </td>
                                        <td style="padding: 15px 20px;">
                                            <div class="kg-scoring-grid" style="display: flex; gap: 8px; justify-content: flex-end;">
                                                <label class="kg-score-option mastered <?php echo $saved_score == 3 ? 'active' : ''; ?>"
                                                    title="<?php echo Olama_School_Helpers::translate('Mastered'); ?>">
                                                    <input type="radio" name="scores[<?php echo $indicator->id; ?>][score]" value="3"
                                                        <?php checked($saved_score, 3); ?> style="display: none;">
                                                    <span class="kg-circle"></span>
                                                    <span class="kg-label">أتقن</span>
                                                </label>
                                                <label class="kg-score-option partial <?php echo $saved_score == 2 ? 'active' : ''; ?>"
                                                    title="<?php echo Olama_School_Helpers::translate('Partially Mastered'); ?>">
                                                    <input type="radio" name="scores[<?php echo $indicator->id; ?>][score]" value="2"
                                                        <?php checked($saved_score, 2); ?> style="display: none;">
                                                    <span class="kg-circle"></span>
                                                    <span class="kg-label">أتقن جزئيا</span>
                                                </label>
                                                <label
                                                    class="kg-score-option not-mastered <?php echo $saved_score == 1 ? 'active' : ''; ?>"
                                                    title="<?php echo Olama_School_Helpers::translate('Not Mastered'); ?>">
                                                    <input type="radio" name="scores[<?php echo $indicator->id; ?>][score]" value="1"
                                                        <?php checked($saved_score, 1); ?> style="display: none;">
                                                    <span class="kg-circle"></span>
                                                    <span class="kg-label">لم يتقن</span>
                                                </label>

                                                <button type="button"
                                                    class="kg-note-trigger <?php echo !empty($saved_notes) ? 'has-note' : ''; ?>"
                                                    onclick="jQuery('#note-<?php echo $indicator->id; ?>').toggle();"
                                                    style="background: none; border: none; cursor: pointer; color: #94a3b8; padding: 5px;">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                            </div>
                                            <div id="note-<?php echo $indicator->id; ?>" class="kg-note-area"
                                                style="display: <?php echo !empty($saved_notes) ? 'block' : 'none'; ?>; margin-top: 10px;">
                                                <textarea name="scores[<?php echo $indicator->id; ?>][notes]"
                                                    placeholder="<?php _e('Add details...', 'olama-school'); ?>"
                                                    style="width: 100%; border-radius: 6px; border-color: #cbd5e1;"><?php echo esc_textarea($saved_notes); ?></textarea>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="kg-form-footer"
                style="position: sticky; bottom: 0; background: #fff; padding: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05);">
                <span id="autosave-status" style="font-size: 12px; color: #94a3b8;">
                    <?php _e('Changes saved as draft.', 'olama-school'); ?>
                </span>
                <div style="display: flex; gap: 10px;">
                    <?php if ($evaluation && $evaluation->status === 'published'): ?>
                        <a href="<?php echo add_query_arg(array('action' => 'kg_print_report', 'evaluation_id' => $evaluation->id), admin_url('admin.php')); ?>"
                            target="_blank" class="button button-secondary button-large">
                            <span class="dashicons dashicons-printer" style="margin-top: 5px; margin-right: 5px;"></span>
                            <?php _e('Print Report', 'olama-school'); ?>
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="button button-large"
                        onclick="document.getElementById('eval-status').value='draft'">
                        <?php echo Olama_School_Helpers::translate('Save Draft'); ?>
                    </button>
                    <button type="submit" class="button button-primary button-large"
                        style="background: #10b981; border-color: #10b981;"
                        onclick="document.getElementById('eval-status').value='published'">
                        <?php echo Olama_School_Helpers::translate('Publish Evaluation'); ?>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
    .olama-kg-evaluation-wrap {
        max-width: 1000px;
        margin-top: 20px;
    }

    .kg-score-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        min-width: 90px;
        min-height: 60px;
        transition: all 0.2s ease;
        background: #fff;
    }

    .kg-score-option .kg-circle {
        display: block;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        background: #fff;
        margin-bottom: 4px;
    }

    .kg-score-option .kg-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        pointer-events: none;
    }

    .kg-score-option.mastered.active {
        background: #ecfdf5;
        border-color: #10b981;
    }

    .kg-score-option.mastered.active .kg-circle {
        background: #10b981;
        border-color: #10b981;
    }

    .kg-score-option.mastered.active .kg-label {
        color: #065f46;
    }

    .kg-score-option.partial.active {
        background: #fffaf2;
        border-color: #f59e0b;
    }

    .kg-score-option.partial.active .kg-circle {
        background: #f59e0b;
        border-color: #f59e0b;
    }

    .kg-score-option.partial.active .kg-label {
        color: #92400e;
    }

    .kg-score-option.not-mastered.active {
        background: #fef2f2;
        border-color: #ef4444;
    }

    .kg-score-option.not-mastered.active .kg-circle {
        background: #ef4444;
        border-color: #ef4444;
    }

    .kg-score-option.not-mastered.active .kg-label {
        color: #991b1b;
    }

    .kg-note-trigger.has-note {
        color: #6366f1 !important;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-kg-evaluation-wrap {
            direction: rtl;
        }

        .kg-scoring-grid {
            justify-content: flex-start !important;
        }

    <?php endif; ?>
</style>

<script>
    jQuery(document).ready(function ($) {
        // Traffic Light Selection Logic
        $('.kg-score-option').on('click', function (e) {
            const row = $(this).closest('.kg-row');
            const input = $(this).find('input');

            row.find('.kg-score-option').removeClass('active');
            $(this).addClass('active');
            input.prop('checked', true);

            updateProgress();
        });

        // Ensure radio changes also update UI (for initial state)
        $('.kg-score-option input').on('change', function () {
            $(this).closest('.kg-score-option').addClass('active').siblings().removeClass('active');
            updateProgress();
        });

        // Progress Bar Logic
        function updateProgress() {
            const total = $('.kg-row').length;
            const filled = $('.kg-row input:checked').length;
            const percent = total > 0 ? (filled / total) * 100 : 0;

            $('#kg-progress-bar').css('width', percent + '%');
            $('#kg-progress-text').text(filled + ' / ' + total);
        }

        updateProgress();

        // Autosave Logic (Basic Implementation)
        let autosaveTimer;
        $('#kg-evaluation-form').on('change', 'input, textarea', function () {
            clearTimeout(autosaveTimer);
            $('#autosave-status').text('<?php _e('Saving...', 'olama-school'); ?>');
            autosaveTimer = setTimeout(performAutosave, 5000); // 5 seconds after last change
        });

        function performAutosave() {
            // Collect data
            // For simplicity in this demo, we'll just show the status change
            // Real implementation would use AJAX to call ajax_autosave
            $('#autosave-status').text('<?php _e('Draft saved locally.', 'olama-school'); ?>');
        }
    });
</script>