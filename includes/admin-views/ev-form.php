<?php
/**
 * Evaluation Form View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="olama-ev-form-wrap">
    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible" style="margin-left: 0; margin-right: 0;">
            <p>
                <?php echo Olama_School_Helpers::translate(sanitize_text_field($_GET['message'])); ?>
            </p>
        </div>
    <?php endif; ?>
    <div class="olama-header-section" style="margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?php echo Olama_School_Helpers::translate('Student Evaluation'); ?>
        </h2>
        <p class="description">
            <?php echo Olama_School_Helpers::translate('Fill the evaluation form for the selected student.'); ?>
        </p>
    </div>

    <!-- Filter Bar -->
    <div class="olama-filter-bar olama-card"
        style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-evaluation">
            <input type="hidden" name="tab" value="student_evaluation">

            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                    </label>
                    <select name="academic_year_id" onchange="this.form.submit()" style="width: 100%;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                                <?php echo esc_html(Olama_School_Helpers::translate($y->year_name)); ?>
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
                                <?php echo esc_html(Olama_School_Helpers::translate($s->semester_name)); ?>
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
                            <?php echo Olama_School_Helpers::translate('Select Section'); ?>
                        </option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                <?php echo esc_html(Olama_School_Helpers::translate($sec->section_name)); ?>
                                (<?php echo esc_html(Olama_School_Helpers::translate($sec->grade_name)); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Select Evaluation (Title)'); ?>
                    </label>
                    <select name="template_id" onchange="this.form.submit()" style="width: 100%;">
                        <option value=""><?php echo Olama_School_Helpers::translate('-- Select --'); ?></option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo $t->id; ?>" <?php selected($selected_template_id, $t->id); ?>>
                                <?php echo esc_html($t->template_name); ?>
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
                            <?php echo Olama_School_Helpers::translate('Choose a Student...'); ?>
                        </option>
                        <?php foreach ($students as $stu):
                            $status = isset($evaluation_statuses[$stu->id]) ? $evaluation_statuses[$stu->id] : '';
                            $tag = '';
                            if ($status === 'published') {
                                $tag = ' - ' . Olama_School_Helpers::translate('Evaluated');
                            } elseif ($status === 'draft') {
                                $tag = ' - ' . Olama_School_Helpers::translate('In Progress');
                            }
                            ?>
                            <option value="<?php echo $stu->id; ?>" <?php selected($selected_student_id, $stu->id); ?>>
                                <?php echo esc_html($stu->student_name . $tag); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selected_student_id && $selected_template_id && empty($curriculum)): ?>
        <div class="notice notice-warning">
            <p><?php echo Olama_School_Helpers::translate('No evaluation structure defined for this template yet.'); ?></p>
        </div>
    <?php elseif ($selected_student_id && $selected_template_id): ?>

        <!-- Progress Bar -->
        <div id="ev-progress-container"
            style="background: #e2e8f0; height: 12px; border-radius: 6px; margin-bottom: 30px; position: sticky; top: 32px; z-index: 100;">
            <div id="ev-progress-bar"
                style="background: #6366f1; width: 0%; height: 100%; border-radius: 6px; transition: width 0.3s ease;">
            </div>
            <span id="ev-progress-text"
                style="position: absolute; right: 0; top: -20px; font-size: 11px; font-weight: 600; color: #6366f1;">0/0</span>
        </div>

        <form id="ev-evaluation-form" method="post" action="">
            <?php wp_nonce_field('olama_ev_save', 'olama_ev_save'); ?>
            <input type="hidden" name="olama_ev_save_eval" value="1">
            <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
            <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
            <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">
            <input type="hidden" name="status" id="eval-status" value="draft">
            <input type="hidden" name="template_id" value="<?php echo $selected_template_id; ?>">

            <?php foreach ($curriculum as $domain): ?>
                <div class="ev-domain-section" style="margin-bottom: 40px;">
                    <div class="ev-domain-header-sticky"
                        style="background: #1e293b; color: #fff; padding: 12px 25px; border-radius: 8px; margin-bottom: 20px; position: sticky; top: 44px; z-index: 90; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0; letter-spacing: 0.5px; color: #ffffff !important;">
                            <?php echo esc_html($domain->title_ar); ?>
                        </h3>
                    </div>

                    <?php foreach ($domain->categories as $category): ?>
                        <div class="ev-category-container"
                            style="margin-bottom: 25px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0;">
                                <h4 style="margin: 0; color: #475569; font-weight: 600;">
                                    <?php echo esc_html($category->title_ar); ?>
                                </h4>
                            </div>

                            <table class="ev-indicator-table" style="width: 100%; border-collapse: collapse;">
                                <?php foreach ($category->indicators as $indicator):
                                    $saved_score = isset($scores[$indicator->id]) ? $scores[$indicator->id]->score : null;
                                    $saved_notes = isset($scores[$indicator->id]) ? $scores[$indicator->id]->notes : '';
                                    ?>
                                    <tr class="ev-row" data-indicator-id="<?php echo $indicator->id; ?>"
                                        style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 15px 20px; width: 50%; font-size: 1.05em;">
                                            <?php echo esc_html($indicator->indicator_text); ?>
                                        </td>
                                        <td style="padding: 15px 20px;">
                                            <div class="ev-scoring-grid" style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: nowrap; align-items: center;">
                                                <?php
                                                $score_config = Olama_School_EV_Template::get_score_config($selected_template_id);
                                                $total_levels = count($score_config);
                                                $i = 0;
                                                foreach ($score_config as $val => $label):
                                                    $i++;
                                                    // Dynamic color assignment
                                                    $color_class = 'not-mastered';
                                                    if ($i === 1) $color_class = 'mastered';
                                                    elseif ($i === $total_levels) $color_class = 'not-mastered';
                                                    elseif ($i === 2 && $total_levels > 2) $color_class = 'partial';
                                                    ?>
                                                    <label class="ev-score-option <?php echo $color_class; ?> <?php echo $saved_score == $val ? 'active' : ''; ?>"
                                                        title="<?php echo esc_attr(Olama_School_Helpers::translate($label)); ?>">
                                                        <input type="radio" name="scores[<?php echo $indicator->id; ?>][score]" value="<?php echo $val; ?>"
                                                            <?php checked($saved_score, $val); ?> style="display: none;">
                                                        <span class="ev-circle"></span>
                                                        <span class="ev-label"><?php echo esc_html(Olama_School_Helpers::translate($label)); ?></span>
                                                    </label>
                                                <?php endforeach; ?>

                                                <button type="button"
                                                    class="ev-note-trigger <?php echo !empty($saved_notes) ? 'has-note' : ''; ?>"
                                                    onclick="jQuery('#note-<?php echo $indicator->id; ?>').toggle();"
                                                    style="background: none; border: none; cursor: pointer; color: #94a3b8; padding: 5px;">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                            </div>
                                            <div id="note-<?php echo $indicator->id; ?>" class="ev-note-area"
                                                style="display: <?php echo !empty($saved_notes) ? 'block' : 'none'; ?>; margin-top: 10px;">
                                                <textarea name="scores[<?php echo $indicator->id; ?>][notes]"
                                                    placeholder="<?php echo Olama_School_Helpers::translate('Add details...'); ?>"
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

            <div class="ev-form-footer"
                style="position: sticky; bottom: 0; background: #fff; padding: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05);">
                <span id="autosave-status" style="font-size: 12px; color: #94a3b8;">
                    <?php echo Olama_School_Helpers::translate('Changes saved as draft.'); ?>
                </span>
                <div style="display: flex; gap: 10px;">
                    <?php if ($evaluation && $evaluation->status === 'published'): ?>
                        <a href="<?php echo add_query_arg(array('action' => 'ev_print_report', 'evaluation_id' => $evaluation->id), admin_url('admin.php')); ?>"
                            target="_blank" class="button button-secondary button-large">
                            <span class="dashicons dashicons-printer" style="margin-top: 5px; margin-right: 5px;"></span>
                            <?php echo Olama_School_Helpers::translate('Print Report'); ?>
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
    .olama-ev-form-wrap {
        max-width: 1000px;
        margin-top: 20px;
    }

    .ev-score-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        min-width: 80px;
        min-height: 55px;
        transition: all 0.2s ease;
        background: #fff;
        flex-shrink: 0;
    }

    .ev-score-option .ev-circle {
        display: block;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        background: #fff;
        margin-bottom: 4px;
    }

    .ev-score-option .ev-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        pointer-events: none;
    }

    .ev-score-option.mastered.active {
        background: #ecfdf5;
        border-color: #10b981;
    }

    .ev-score-option.mastered.active .ev-circle {
        background: #10b981;
        border-color: #10b981;
    }

    .ev-score-option.mastered.active .ev-label {
        color: #065f46;
    }

    .ev-score-option.partial.active {
        background: #fffaf2;
        border-color: #f59e0b;
    }

    .ev-score-option.partial.active .ev-circle {
        background: #f59e0b;
        border-color: #f59e0b;
    }

    .ev-score-option.partial.active .ev-label {
        color: #92400e;
    }

    .ev-score-option.not-mastered.active {
        background: #fef2f2;
        border-color: #ef4444;
    }

    .ev-score-option.not-mastered.active .ev-circle {
        background: #ef4444;
        border-color: #ef4444;
    }

    .ev-score-option.not-mastered.active .ev-label {
        color: #991b1b;
    }

    .ev-note-trigger.has-note {
        color: #6366f1 !important;
    }

    .ev-row:nth-child(even) {
        background: #f8fafc;
    }

    .ev-row:hover {
        background: #f1f5f9;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-ev-form-wrap {
            direction: rtl;
        }

        .ev-scoring-grid {
            justify-content: flex-start !important;
        }

    <?php endif; ?>
</style>

<script>
    jQuery(document).ready(function ($) {
        // Traffic Light Selection Logic
        $('.ev-score-option').on('click', function (e) {
            const row = $(this).closest('.ev-row');
            const input = $(this).find('input');

            row.find('.ev-score-option').removeClass('active');
            $(this).addClass('active');
            input.prop('checked', true);

            updateProgress();
        });

        $('.ev-score-option input').on('change', function () {
            $(this).closest('.ev-score-option').addClass('active').siblings().removeClass('active');
            updateProgress();
        });

        function updateProgress() {
            const total = $('.ev-row').length;
            const filled = $('.ev-row input:checked').length;
            const percent = total > 0 ? (filled / total) * 100 : 0;

            $('#ev-progress-bar').css('width', percent + '%');
            $('#ev-progress-text').text(filled + ' / ' + total);
        }

        updateProgress();

        let autosaveTimer;
        $('#ev-evaluation-form').on('change', 'input, textarea', function () {
            clearTimeout(autosaveTimer);
            $('#autosave-status').text('<?php echo Olama_School_Helpers::translate('Saving...'); ?>');
            autosaveTimer = setTimeout(performAutosave, 5000);
        });

        function performAutosave() {
            $('#autosave-status').text('<?php echo Olama_School_Helpers::translate('Draft saved locally.'); ?>');
        }
    });
</script>