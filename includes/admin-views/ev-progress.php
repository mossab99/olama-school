<?php
/**
 * Evaluation Progress View
 */

if (!defined('ABSPATH')) {
    exit;
}

$years = Olama_School_Academic::get_years();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
$selected_year = Olama_School_Academic::get_year($selected_year_id);
$selected_year_name = $selected_year ? $selected_year->year_name : '';

$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$selected_semester_id = $active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0);
$selected_semester_name = $active_semester ? $active_semester->semester_name : '';

$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;

$sections = $selected_grade_id ? Olama_School_Section::get_by_grade($selected_grade_id, $selected_year_id) : array();
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

$ev_manager = new Olama_School_EV_Manager();
$progress_data = array();

if ($selected_grade_id && $selected_section_id && $selected_semester_id) {
    $progress_data = $ev_manager->get_progress_data($selected_grade_id, $selected_section_id, $selected_year_id, $selected_semester_id);
}
?>

<div class="olama-ev-form-wrap">
    <div class="olama-header-section" style="margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?php echo Olama_School_Helpers::translate('Evaluation Progress'); ?>
        </h2>
        <p class="description">
            <?php echo Olama_School_Helpers::translate('Track evaluation completion by grade and section.'); ?>
        </p>
    </div>

    <!-- Filter Bar -->
    <div class="olama-filter-bar olama-card"
        style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-evaluation">
            <input type="hidden" name="tab" value="evaluation_progress">

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
                        <?php echo Olama_School_Helpers::translate('Grade'); ?>
                    </label>
                    <select name="grade_id" onchange="this.form.submit()" style="width: 100%;">
                        <option value=""><?php echo Olama_School_Helpers::translate('Select Grade'); ?></option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                <?php echo esc_html($g->grade_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Section'); ?>
                    </label>
                    <select name="section_id" onchange="this.form.submit()" style="width: 100%;" <?php echo empty($sections) ? 'disabled' : ''; ?>>
                        <option value=""><?php echo Olama_School_Helpers::translate('Select Section'); ?></option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($selected_section_id, $s->id); ?>>
                                <?php echo esc_html($s->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selected_grade_id && $selected_section_id): ?>
        <div class="olama-card"
            style="background: #fff; padding: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php echo Olama_School_Helpers::translate('Evaluation Title'); ?></th>
                        <th style="width: 15%;"><?php echo Olama_School_Helpers::translate('Creation Date'); ?></th>
                        <th style="width: 25%;"><?php echo Olama_School_Helpers::translate('Completion Ratio'); ?></th>
                        <th style="width: 20%; text-align: center;">
                            <?php echo Olama_School_Helpers::translate('Actions'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($progress_data)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
                                <?php echo Olama_School_Helpers::translate('No evaluation templates found for this selection.'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($progress_data as $row): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="progress-ratio-container">
                                        <div class="progress-text">
                                            <?php echo sprintf('%d / %d (%d%%)', $row['filled_count'], $row['total_students'], round($row['ratio'] * 100)); ?>
                                        </div>
                                        <div class="progress-bar-mini">
                                            <div class="progress-fill"
                                                style="width: <?php echo $row['ratio'] * 100; ?>%; background: <?php echo $row['ratio'] >= 1 ? '#10b981' : ($row['ratio'] > 0.5 ? '#f59e0b' : '#ef4444'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="ev-progress-actions" style="justify-content: center;">
                                        <button type="button" class="button button-secondary list-students-btn"
                                            data-template-id="<?php echo $row['template_id']; ?>"
                                            data-section-id="<?php echo $selected_section_id; ?>"
                                            data-title="<?php echo esc_attr($row['title']); ?>">
                                            <?php echo Olama_School_Helpers::translate('List Student'); ?>
                                        </button>
                                        <button type="button" class="button button-primary bulk-approve-btn"
                                            style="background: #10b981; border-color: #10b981;"
                                            data-template-id="<?php echo $row['template_id']; ?>"
                                            data-section-id="<?php echo $selected_section_id; ?>">
                                            <?php echo Olama_School_Helpers::translate('Approve All Drafts'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- New Details Section -->
        <div id="ev-details-section" class="olama-card"
            style="display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 25px; overflow: hidden;">
            <div class="ev-details-grid" style="display: grid; grid-template-columns: 1fr 3fr; min-height: 500px;">
                <!-- Student Selection Column -->
                <div class="ev-students-col"
                    style="border-right: 1px solid #e2e8f0; background: #f8fafc; overflow-y: auto; max-height: 800px;">
                    <div
                        style="padding: 15px; border-bottom: 1px solid #e2e8f0; background: #fff; position: sticky; top: 0; z-index: 5;">
                        <h3 style="margin: 0; font-size: 1.1em;"><?php echo Olama_School_Helpers::translate('Students'); ?>
                        </h3>
                    </div>
                    <div id="ev-students-list">
                        <!-- AJAX populated students -->
                    </div>
                </div>
                <!-- Evaluation Display Column -->
                <div class="ev-evaluation-col" style="padding: 25px; overflow-y: auto; max-height: 800px;">
                    <div id="ev-evaluation-content">
                        <div style="text-align: center; color: #94a3b8; padding-top: 100px;">
                            <span class="dashicons dashicons-id"
                                style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 15px;"></span>
                            <p><?php echo Olama_School_Helpers::translate('Select a student to view evaluation details.'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="notice notice-info" style="margin: 0;">
            <p><?php echo Olama_School_Helpers::translate('Please select a grade and section to view progress.'); ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
    jQuery(document).ready(function ($) {
        const nonce = '<?php echo wp_create_nonce("olama_admin_nonce"); ?>';

        $('.list-students-btn').on('click', function () {
            const btn = $(this);
            const templateId = btn.data('template-id');
            const sectionId = btn.data('section-id');

            $('#ev-details-section').show();
            $('#ev-students-list').html('<p style="text-align:center; padding:20px;"><span class="dashicons dashicons-update spin"></span></p>');

            $.get(ajaxurl, {
                action: 'olama_get_ev_progress_students',
                template_id: templateId,
                section_id: sectionId,
                nonce: nonce
            }, function (response) {
                $('#ev-students-list').html(response);
            });
        });

        $(document).on('click', '.ev-student-item', function () {
            $('.ev-student-item').removeClass('active');
            $(this).addClass('active');

            const studentId = $(this).data('student-id');
            const templateId = $(this).data('template-id');

            $('#ev-evaluation-content').html('<p style="text-align:center; padding:50px;"><span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px;"></span></p>');

            $.get(ajaxurl, {
                action: 'olama_get_student_evaluation',
                student_id: studentId,
                template_id: templateId,
                nonce: nonce
            }, function (response) {
                $('#ev-evaluation-content').html(response);
            });
        });

        $(document).on('click', '.ev-approve-btn', function (e) {
            e.stopPropagation();
            const btn = $(this);
            const studentId = btn.data('student-id');
            const templateId = btn.data('template-id');
            const comments = $('#ev-supervisor-comments-text').val() || '';

            if (!confirm('<?php echo Olama_School_Helpers::translate('Are you sure you want to approve this evaluation? It will be published immediately.'); ?>')) {
                return;
            }

            btn.prop('disabled', true).text('...');

            $.post(ajaxurl, {
                action: 'olama_approve_evaluation',
                student_id: studentId,
                template_id: templateId,
                supervisor_comments: comments,
                nonce: nonce
            }, function (response) {
                if (response.success) {
                    // Refresh student list entry or evaluation content
                    $('.ev-student-item.active').click();
                    // Optionally refresh the student list to update badges
                    $('.list-students-btn[data-template-id="' + templateId + '"]').click();
                } else {
                    alert(response.data);
                    btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Approve'); ?>');
                }
            });
        });

        $(document).on('click', '.ev-save-comments-btn', function (e) {
            const btn = $(this);
            const studentId = btn.data('student-id');
            const templateId = btn.data('template-id');
            const comments = $('#ev-supervisor-comments-text').val();

            btn.prop('disabled', true).text('<?php echo Olama_School_Helpers::translate('Saving...'); ?>');

            $.post(ajaxurl, {
                action: 'olama_save_supervisor_comments',
                student_id: studentId,
                template_id: templateId,
                supervisor_comments: comments,
                nonce: nonce
            }, function (response) {
                btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Save Comments'); ?>');
                if (response.success) {
                    alert('<?php echo Olama_School_Helpers::translate('Comments saved successfully.'); ?>');
                } else {
                    alert(response.data);
                }
            });
        });

        $('.bulk-approve-btn').on('click', function () {
            const btn = $(this);
            const templateId = btn.data('template-id');
            const sectionId = btn.data('section-id');

            if (!confirm('<?php echo Olama_School_Helpers::translate('Are you sure you want to approve ALL draft evaluations for this template and section?'); ?>')) {
                return;
            }

            btn.prop('disabled', true).text('...');

            $.post(ajaxurl, {
                action: 'olama_bulk_approve_evaluations',
                template_id: templateId,
                section_id: sectionId,
                nonce: nonce
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Approve All Drafts'); ?>');
                }
            });
        });
    });
</script>

<style>
    .progress-ratio-container {
        width: 100%;
        max-width: 200px;
    }

    .progress-text {
        font-size: 12px;
        margin-bottom: 4px;
        font-weight: 600;
        color: #475569;
    }

    .progress-bar-mini {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .ev-student-item {
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }

    .ev-student-item:hover {
        background: #f1f5f9;
    }

    .ev-student-item.active {
        background: #fff;
        border-left: 4px solid #3b82f6;
    }

    .ev-student-info {
        flex: 1;
    }

    .ev-student-name {
        font-weight: 600;
        color: #1e293b;
        display: block;
    }

    .ev-student-uid {
        font-size: 11px;
        color: #64748b;
    }

    .ev-approve-btn {
        margin-left: 10px !important;
    }

    .ev-status-badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        font-weight: 700;
    }

    .status-published {
        background: #dcfce7;
        color: #166534;
    }

    .status-draft {
        background: #fef9c3;
        color: #854d0e;
    }

    .status-none {
        background: #f1f5f9;
        color: #475569;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
</style>