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
            <?php 
            if ($context_type === 'supervisor') {
                _e('Supervisor Visit Evaluation', 'olama-school');
            } else {
                echo Olama_School_Helpers::translate('Student Evaluation'); 
            }
            ?>
        </h2>
        <p class="description">
            <?php 
            if ($context_type === 'supervisor') {
                _e('Evaluate the teacher performance during the scheduled visit.', 'olama-school');
            } else {
                echo Olama_School_Helpers::translate('Fill the evaluation form for the selected student.'); 
            }
            ?>
        </p>
    </div>

    <?php if ($context_type === 'supervisor' && $visit): ?>
        <div class="olama-visit-info-card olama-card" style="background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px; border-right: 4px solid #6366f1;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        <?php _e('Teacher', 'olama-school'); ?>
                    </label>
                    <div style="font-size: 16px; font-weight: 700; color: #1e293b;">
                        <?php echo esc_html(get_the_author_meta('display_name', $visit->teacher_id)); ?>
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        <?php _e('Visit Date', 'olama-school'); ?>
                    </label>
                    <div style="font-size: 16px; font-weight: 700; color: #1e293b;">
                        <?php echo date_i18n(get_option('date_format'), strtotime($visit->visit_date)); ?>
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        <?php _e('Template', 'olama-school'); ?>
                    </label>
                    <div style="font-size: 16px; font-weight: 700; color: #1e293b;">
                        <?php echo esc_html($visit->template_name); ?>
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        <?php _e('Status', 'olama-school'); ?>
                    </label>
                    <div style="font-size: 16px; font-weight: 700; color: <?php echo $visit->status === 'completed' ? '#10b981' : '#f59e0b'; ?>;">
                        <?php echo ucfirst($visit->status); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($context_type === 'student'): ?>
        <!-- Action Required Summary Notice -->
        <?php 
        $students_with_comments = array();
        if ($selected_section_id && $selected_template_id) {
            foreach ($students as $stu) {
                $ev_data = $evaluation_statuses[$stu->id] ?? null;
                if ($ev_data && $ev_data['status'] === 'draft' && $ev_data['has_comments']) {
                    $students_with_comments[] = $stu;
                }
            }
        }
        ?>

        <?php if (!empty($students_with_comments)): ?>
            <div class="ev-action-required-summary" style="background: #fef2f2; border: 1px solid #fee2e2; border-right: 4px solid #ef4444; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                <h3 style="margin: 0 0 15px 0; color: #991b1b; font-size: 1.1em; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-warning" style="color: #ef4444;"></span>
                    <?php echo Olama_School_Helpers::translate('Action Required: Supervisor Feedback'); ?>
                </h3>
                
                <table class="wp-list-table widefat fixed striped" style="border: none; background: transparent; box-shadow: none;">
                    <thead>
                        <tr>
                            <th style="background: transparent; border-bottom: 2px solid #fee2e2; font-weight: 700; color: #1e293b; width: 25%;"><?php echo Olama_School_Helpers::translate('Student Name'); ?></th>
                            <th style="background: transparent; border-bottom: 2px solid #fee2e2; font-weight: 700; color: #1e293b;"><?php echo Olama_School_Helpers::translate('Supervisor Comments'); ?></th>
                            <th style="background: transparent; border-bottom: 2px solid #fee2e2; font-weight: 700; color: #1e293b; width: 120px; text-align: center;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_with_comments as $stu): 
                            $stu_ev = Olama_School_EV_Record::get_evaluation($stu->id, $selected_year_id, $selected_semester_id, $selected_template_id);
                        ?>
                            <tr>
                                <td style="background: transparent; font-weight: 600; color: #1e293b;"><?php echo esc_html($stu->student_name); ?></td>
                                <td style="background: transparent;">
                                    <div style="color: #991b1b; font-size: 13px; line-height: 1.4; font-style: italic;">
                                        "<?php echo nl2br(esc_html($stu_ev->supervisor_comments)); ?>"
                                    </div>
                                </td>
                                <td style="background: transparent; text-align: center;">
                                    <a href="<?php echo add_query_arg('student_id', $stu->id, $_SERVER['REQUEST_URI']); ?>" 
                                    class="button button-small" 
                                    style="background: #ef4444; border-color: #ef4444; color: #fff;">
                                        <?php echo Olama_School_Helpers::translate('Open Form'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?> <!-- Close student context if -->

    <!-- Filter Bar -->
        <div class="olama-filter-bar olama-card"
            style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'olama-school-evaluation'); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr($_GET['tab'] ?? 'student_evaluation'); ?>">
                <input type="hidden" name="context" value="<?php echo esc_attr($context_type); ?>">
                <?php if ($visit_id): ?>
                    <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
                <?php endif; ?>

                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <?php 
                    echo Olama_School_Helpers::locked_filter_render(
                        Olama_School_Helpers::translate('Academic Year'), 
                        Olama_School_Helpers::translate($selected_year_name), 
                        'academic_year_id', 
                        $selected_year_id
                    ); 

                    echo Olama_School_Helpers::locked_filter_render(
                        Olama_School_Helpers::translate('Semester'), 
                        Olama_School_Helpers::translate($selected_semester_name), 
                        'semester_id', 
                        $selected_semester_id
                    ); 
                    ?>

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

                    <div style="flex: 1; min-width: 150px; <?php echo $context_type === 'supervisor' ? 'display: none;' : ''; ?>">
                        <label class="olama-label">
                            <?php echo Olama_School_Helpers::translate('Select Student'); ?>
                        </label>
                        <select name="student_id" onchange="this.form.submit()" style="width: 100%;">
                            <option value="">
                                <?php echo Olama_School_Helpers::translate('Choose a Student...'); ?>
                            </option>
                            <?php foreach ($students as $stu):
                                $ev_data = isset($evaluation_statuses[$stu->id]) ? $evaluation_statuses[$stu->id] : array('status' => '', 'has_comments' => false);
                                $status = $ev_data['status'];
                                $has_comments = $ev_data['has_comments'];
                                $tag = '';
                                if ($status === 'published') {
                                    $tag = ' - ' . Olama_School_Helpers::translate('Evaluated');
                                } elseif ($status === 'draft') {
                                    if ($has_comments) {
                                        $tag = ' - ' . Olama_School_Helpers::translate('Action required');
                                    } else {
                                        $tag = ' - ' . Olama_School_Helpers::translate('In Progress');
                                    }
                                }
                                ?>
                                <option value="<?php echo $stu->id; ?>" <?php selected($selected_student_id, $stu->id); ?> <?php echo $has_comments && $status === 'draft' ? 'style="color: #ef4444; font-weight: bold;"' : ''; ?>>
                                    <?php echo esc_html($stu->student_name . $tag); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

    <?php if (($selected_student_id && $selected_template_id && empty($curriculum)) || ($context_type === 'supervisor' && $visit_id && empty($curriculum))): ?>
        <div class="notice notice-warning">
            <p><?php echo Olama_School_Helpers::translate('No evaluation structure defined for this template yet.'); ?></p>
        </div>
    <?php elseif (($selected_student_id && $selected_template_id) || ($context_type === 'supervisor' && $visit_id)): ?>

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
            <input type="hidden" name="subject_id" value="<?php echo $template_subject_id ?? ''; ?>">
            <input type="hidden" name="context_type" value="<?php echo esc_attr($context_type); ?>">
            <?php if ($context_type === 'supervisor' && $visit_id): ?>
                <input type="hidden" name="related_entity_type" value="supervisor_visit">
                <input type="hidden" name="related_entity_id" value="<?php echo intval($visit_id); ?>">
            <?php endif; ?>

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
                    <button type="submit" class="button button-large button-primary"
                        onclick="document.getElementById('eval-status').value='draft'">
                        <?php echo Olama_School_Helpers::translate('Save Draft'); ?>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Supervisor Comments logic -->
<?php if ($evaluation && !empty($evaluation->supervisor_comments)): ?>
    <script>
        jQuery(document).ready(function($) {
            const commentsHtml = `
                <div class="ev-supervisor-comments-notice" style="background: #fff7ed; border: 1px solid #ffedd5; border-right: 4px solid #f97316; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
                    <h4 style="margin: 0 0 8px 0; color: #9a3412; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <?php echo Olama_School_Helpers::translate('Supervisor Comments'); ?>
                    </h4>
                    <div style="color: #c2410c; font-size: 14px; line-height: 1.5;">
                        <?php echo nl2br(esc_html($evaluation->supervisor_comments)); ?>
                    </div>
                </div>
            `;
            $('.olama-header-section').after(commentsHtml);
        });
    </script>
<?php endif; ?>

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
            const formData = $('#ev-evaluation-form').serializeArray();
            const evaluation_data = {};
            const scores = {};

            formData.forEach(item => {
                if (item.name.startsWith('scores[')) {
                    const match = item.name.match(/scores\[(\d+)\]\[(score|notes)\]/);
                    if (match) {
                        const id = match[1];
                        const key = match[2];
                        if (!scores[id]) scores[id] = { indicator_id: id };
                        scores[id][key] = item.value;
                    }
                } else {
                    evaluation_data[item.name] = item.value;
                }
            });

            evaluation_data.scores = Object.values(scores);

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'olama_kg_evaluation_autosave',
                    nonce: '<?php echo wp_create_nonce("olama_kg_evaluation_nonce"); ?>',
                    evaluation_data: evaluation_data
                },
                success: function(response) {
                    if (response.success) {
                        $('#autosave-status').text('<?php echo Olama_School_Helpers::translate('Draft saved automatically.'); ?>');
                    } else {
                        $('#autosave-status').text('<?php echo Olama_School_Helpers::translate('Error saving draft.'); ?>');
                    }
                },
                error: function() {
                    $('#autosave-status').text('<?php echo Olama_School_Helpers::translate('Connection error.'); ?>');
                }
            });
        }
    });
</script>