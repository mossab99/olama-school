<?php
/**
 * Exam Schedule View - Modal Layout
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get semester exams for the dropdown - filtered by grade if selected
$semester_exams = Olama_School_Academic::get_semester_exams($selected_semester_id, $selected_grade_id);
$active_exam = Olama_School_Academic::get_active_exam($selected_semester_id);
$exams = Olama_School_Exam::get_exams($selected_year_id, $selected_semester_id, $selected_grade_id, $selected_subject_id, $selected_semester_exam_id);

// Get current master exam details if selected
$current_master_exam = null;
if ($selected_semester_exam_id) {
    foreach ($semester_exams as $se) {
        if ($se->id == $selected_semester_exam_id) {
            $current_master_exam = $se;
            break;
        }
    }
}
?>

<div class="wrap olama-exam-wrap">
    <div class="olama-header-section"
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;"><?php echo Olama_School_Helpers::translate('Exam Schedule'); ?></h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" id="bulk-add-subjects" class="button button-secondary" <?php echo (!$selected_semester_exam_id || !$selected_grade_id) ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-database-add" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Init All Subjects'); ?>
            </button>
            <button type="button" id="open-add-exam-modal" class="button button-primary"
                style="display: flex; align-items: center; gap: 5px;">
                <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?>
            </button>
        </div>
    </div>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'exam_saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo Olama_School_Helpers::translate('Exam saved successfully.'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="olama-filter-bar"
        style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; border-radius: 4px;">
        <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <input type="hidden" name="page" value="olama-school-exams">
            <input type="hidden" name="tab" value="exam_schedule">

            <div class="filter-group">
                <label
                    style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Year'); ?></label>
                <select name="academic_year_id" onchange="this.form.submit();">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                            <?php echo esc_html($y->year_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label
                    style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Semester'); ?></label>
                <select name="semester_id" onchange="this.form.submit();">
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                            <?php echo esc_html($s->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label
                    style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Exam'); ?></label>
                <select name="semester_exam_id" onchange="this.form.submit();">
                    <option value="0"><?php echo Olama_School_Helpers::translate('Choose Exam'); ?></option>
                    <?php foreach ($semester_exams as $se): ?>
                        <option value="<?php echo $se->id; ?>" <?php selected($selected_semester_exam_id, $se->id); ?>>
                            <?php echo esc_html($se->exam_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label
                    style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
                <select name="grade_id" onchange="this.form.submit();">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                            <?php echo esc_html($g->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button button-secondary"
                style="margin-top: 15px;"><?php echo Olama_School_Helpers::translate('Search'); ?></button>
        </form>
    </div>

    <?php if ($current_master_exam): ?>
        <div class="olama-exam-context"
            style="background: #fdf2f2; border: 1px solid #fecaca; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 40px; align-items: center;">
            <div>
                <span style="color: #ef4444; font-weight: 700; margin-left: 8px;"><span
                        class="dashicons dashicons-location-alt" style="font-size: 18px; margin-top:2px;"></span>
                    <?php echo Olama_School_Helpers::translate('Room'); ?>:</span>
                <span
                    style="font-weight: 800; color: #1e293b; font-size: 15px;"><?php echo esc_html($current_master_exam->room_number ?: __('Not Assigned', 'olama-school')); ?></span>
            </div>
            <div>
                <span style="color: #ef4444; font-weight: 700; margin-left: 8px;"><span
                        class="dashicons dashicons-calendar-alt" style="font-size: 18px; margin-top:2px;"></span>
                    <?php echo Olama_School_Helpers::translate('Allowed Period'); ?>:</span>
                <span style="font-weight: 800; color: #1e293b; font-size: 15px;">
                    <?php echo Olama_School_Helpers::format_date($current_master_exam->start_date); ?>
                    <span style="margin: 0 5px;">-</span>
                    <?php echo Olama_School_Helpers::format_date($current_master_exam->end_date); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Full Width Table -->
    <div class="olama-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Subject'); ?>
                    </th>
                    <th style="font-weight: 600; width: 20%;"><?php echo Olama_School_Helpers::translate('Exam'); ?>
                    </th>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Date'); ?>
                    </th>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Room'); ?>
                    </th>
                    <th style="font-weight: 600; width: 10%;"><?php echo Olama_School_Helpers::translate('Status'); ?>
                    </th>
                    <th style="width: 140px; text-align: center; font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Actions'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <?php echo Olama_School_Helpers::translate('No exams found for the selected criteria.'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam):
                        $exam->formatted_date = Olama_School_Helpers::format_date($exam->exam_date);
                        $status_label = ($exam->status === 'completed') ? Olama_School_Helpers::translate('Completed') : Olama_School_Helpers::translate('Not Completed');
                        $status_color = ($exam->status === 'completed') ? '#10b981' : '#f59e0b';
                        ?>
                        <tr>
                            <td><strong><?php
                            $sb_info = Olama_School_Subject::get_subject($exam->subject_id);
                            echo $sb_info ? esc_html($sb_info->subject_name) : __('Unknown', 'olama-school');
                            ?></strong></td>
                            <td><?php
                            $se_info = array_filter($semester_exams, function ($e) use ($exam) {
                                return $e->id == $exam->semester_exam_id;
                            });
                            $se_info = reset($se_info);
                            echo $se_info ? esc_html($se_info->exam_name) : esc_html($exam->evaluation_type);
                            ?></td>
                            <td class="date-column">
                                <div class="date-display"><?php echo $exam->formatted_date; ?></div>
                                <div class="date-edit" style="display: none;">
                                    <input type="text" class="olama-datepicker inline-date-input"
                                        data-exam-id="<?php echo $exam->id; ?>" value="<?php echo $exam->formatted_date; ?>"
                                        style="width: 100px; padding: 4px; font-size: 12px;">
                                    <div class="inline-actions" style="margin-top: 5px;">
                                        <button type="button"
                                            class="button button-primary button-small save-inline-date"><?php _e('Save', 'olama-school'); ?></button>
                                        <button type="button"
                                            class="button button-small cancel-inline-date"><?php _e('Cancel', 'olama-school'); ?></button>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo esc_html($exam->master_room ?: $exam->room_number); ?></td>
                            <td>
                                <span
                                    style="display:inline-block; padding: 2px 8px; border-radius: 12px; background: <?php echo $status_color; ?>22; color: <?php echo $status_color; ?>; font-size: 11px; font-weight: 600;">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button button-small edit-exam-date-inline"
                                    title="<?php echo Olama_School_Helpers::translate('Edit Date'); ?>">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </button>
                                <button type="button" class="button button-small edit-exam-material"
                                    data-exam='<?php echo json_encode($exam); ?>'
                                    title="<?php echo Olama_School_Helpers::translate('Exam Material'); ?>">
                                    <span class="dashicons dashicons-media-text"></span>
                                </button>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-exams&tab=exam_schedule&action=delete_exam&exam_id=' . $exam->id), 'olama_delete_exam_' . $exam->id); ?>"
                                    class="button button-small"
                                    onclick="return confirm('<?php echo Olama_School_Helpers::translate('Are you sure?'); ?>')"
                                    title="<?php echo Olama_School_Helpers::translate('Delete'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Material Edit Modal -->
    <div id="material-modal" class="olama-modal" style="display: none;">
        <div class="olama-modal-content">
            <div class="olama-modal-header">
                <h2><?php echo Olama_School_Helpers::translate('Exam Material & Details'); ?></h2>
                <span class="olama-modal-close" onclick="closeModal('material-modal')">&times;</span>
            </div>
            <form id="material-form" method="post">
                <?php wp_nonce_field('olama_save_exam', 'olama_material_nonce_field'); ?>
                <input type="hidden" name="id" id="material_exam_id">
                <input type="hidden" name="academic_year_id" id="material_academic_year_id">
                <input type="hidden" name="semester_id" id="material_semester_id">
                <input type="hidden" name="grade_id" id="material_grade_id">
                <input type="hidden" name="subject_id" id="material_subject_id">
                <input type="hidden" name="semester_exam_id" id="material_semester_exam_id">
                <input type="hidden" name="action" value="olama_save_exam">

                <div class="olama-form-grid">
                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Student Book Material'); ?></label>
                        <textarea name="student_book_material" rows="2"
                            placeholder="<?php echo Olama_School_Helpers::translate('e.g. Pages 10-25, Units 1-2'); ?>"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Workbook Material'); ?></label>
                        <textarea name="workbook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Notebook Material'); ?></label>
                        <textarea name="notebook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Detailed Description'); ?></label>
                        <textarea name="description" rows="3"></textarea>
                    </div>

                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Teacher Notes'); ?></label>
                        <textarea name="teacher_notes" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Status'); ?></label>
                        <select name="status">
                            <option value="draft">
                                <?php echo Olama_School_Helpers::translate('Draft / Not Completed'); ?>
                            </option>
                            <option value="completed"><?php echo Olama_School_Helpers::translate('Completed'); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="olama-modal-footer">
                    <button type="submit"
                        class="button button-primary"><?php echo Olama_School_Helpers::translate('Save Material'); ?></button>
                    <button type="button" class="button"
                        onclick="closeModal('material-modal')"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Subject Modal (Legacy compatibility or new subject) -->
    <div id="exam-modal" class="olama-modal" style="display: none;">
        <div class="olama-modal-content">
            <div class="olama-modal-header">
                <h2 id="form-title"><?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?></h2>
                <span class="olama-modal-close" onclick="closeModal('exam-modal')">&times;</span>
            </div>

            <form id="exam-form" method="post">
                <?php wp_nonce_field('olama_save_exam', 'olama_exam_nonce_field'); ?>
                <input type="hidden" name="olama_save_exam" value="1">
                <input type="hidden" name="id" id="exam_id" value="">
                <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
                <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">
                <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>">

                <div class="olama-form-grid">
                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Active Exam'); ?> *</label>
                        <select name="semester_exam_id" id="add_semester_exam_id" required>
                            <option value=""><?php echo Olama_School_Helpers::translate('Choose'); ?></option>
                            <?php foreach ($semester_exams as $se): ?>
                                <option value="<?php echo $se->id; ?>"><?php echo esc_html($se->exam_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Subject'); ?> *</label>
                        <select name="subject_id" required>
                            <option value=""><?php echo Olama_School_Helpers::translate('Choose'); ?></option>
                            <?php foreach ($subjects as $sb): ?>
                                <option value="<?php echo $sb->id; ?>"><?php echo esc_html($sb->subject_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Exam Date'); ?> *</label>
                        <input type="text" name="exam_date" id="add_exam_date" required class="olama-datepicker"
                            autocomplete="off">
                    </div>

                    <input type="hidden" name="status" value="draft">
                </div>

                <div class="olama-modal-footer">
                    <button type="submit" id="submit-exam-btn"
                        class="button button-primary button-large"><?php echo Olama_School_Helpers::translate('Add Exam'); ?></button>
                    <button type="button" class="button"
                        onclick="closeModal('exam-modal')"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .olama-exam-wrap {
        max-width: 1200px;
        margin: 20px auto;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .olama-modal {
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .olama-modal-content {
        background-color: #fff;
        padding: 0;
        border-radius: 8px;
        width: 100%;
        max-width: 700px;
        position: relative;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .olama-modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .olama-modal-header h2 {
        margin: 0;
        font-size: 18px;
        color: #1e293b;
    }

    .olama-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #94a3b8;
        cursor: pointer;
        line-height: 1;
    }

    .olama-form-grid {
        padding: 25px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }

    .form-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-field.full-width {
        grid-column: span 2;
    }

    .form-field label {
        font-weight: 600;
        font-size: 13px;
        color: #475569;
    }

    .form-field select,
    .form-field input,
    .form-field textarea {
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        width: 100%;
    }

    .olama-modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-exam-wrap {
            direction: rtl;
        }

        .olama-modal-footer {
            justify-content: flex-start;
        }

    <?php endif; ?>
</style>

<script>
    window.closeModal = function (id) {
        jQuery('#' + id).fadeOut(200);
    }

    jQuery(document).ready(function ($) {
        var master_start = <?php echo $current_master_exam ? '"' . $current_master_exam->start_date . '"' : 'null'; ?>;
        var master_end = <?php echo $current_master_exam ? '"' . $current_master_exam->end_date . '"' : 'null'; ?>;

        function validateDate(dateStr) {
            if (!master_start || !master_end || !dateStr) return true;

            var date;
            if (dateStr.indexOf('-') !== -1) {
                var parts = dateStr.split('-');
                if (parts[0].length === 4) {
                    // Y-m-d
                    date = new Date(dateStr);
                } else {
                    // d-m-Y
                    date = new Date(parts[2], parts[1] - 1, parts[0]);
                }
            } else {
                date = new Date(dateStr);
            }

            if (isNaN(date.getTime())) return true;

            var start = new Date(master_start);
            var end = new Date(master_end);

            date.setHours(0, 0, 0, 0);
            start.setHours(0, 0, 0, 0);
            end.setHours(0, 0, 0, 0);

            if (date < start || date > end) {
                alert('<?php echo esc_js(__('Exam date must be within the allowed period: ', 'olama-school')); ?>' + '<?php echo $current_master_exam ? Olama_School_Helpers::format_date($current_master_exam->start_date) : ''; ?>' + ' - ' + '<?php echo $current_master_exam ? Olama_School_Helpers::format_date($current_master_exam->end_date) : ''; ?>');
                return false;
            }
            return true;
        }

        $('#open-add-exam-modal').on('click', function () {
            $('#exam-form')[0].reset();
            $('#exam_id').val('');
            $('#exam-modal').fadeIn(200);
        });

        // Inline Date Edit Toggling
        $('.edit-exam-date-inline').on('click', function() {
            var row = $(this).closest('tr');
            row.find('.date-display').hide();
            row.find('.date-edit').fadeIn(200);
            
            // Re-initialize datepicker for the inline field
            row.find('.olama-datepicker').datepicker({
                dateFormat: '<?php echo (Olama_School_Helpers::get_active_year()) ? "dd-mm-yy" : "yy-mm-dd"; ?>', // Match PHP format
                changeMonth: true,
                changeYear: true
            });
        });

        $('.cancel-inline-date').on('click', function() {
            var row = $(this).closest('tr');
            row.find('.date-edit').hide();
            row.find('.date-display').fadeIn(200);
        });

        $('.save-inline-date').on('click', function() {
            var btn = $(this);
            var row = btn.closest('tr');
            var input = row.find('.inline-date-input');
            var newDate = input.val();
            var examId = input.data('exam-id');

            if (!validateDate(newDate)) return;

            btn.prop('disabled', true).text('...');

            var data = {
                action: 'olama_save_exam',
                nonce: $('#olama_exam_nonce_field').val(),
                id: examId,
                exam_date: newDate
            };

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    row.find('.date-display').text(newDate);
                    row.find('.date-edit').hide();
                    row.find('.date-display').fadeIn(200);
                    btn.prop('disabled', false).text('<?php _e('Save', 'olama-school'); ?>');
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false).text('<?php _e('Save', 'olama-school'); ?>');
                }
            });
        });

        $('.edit-exam-material').on('click', function () {
            var data = $(this).data('exam');
            $('#material_exam_id').val(data.id);
            $('#material_academic_year_id').val(data.academic_year_id);
            $('#material_semester_id').val(data.semester_id);
            $('#material_grade_id').val(data.grade_id);
            $('#material_subject_id').val(data.subject_id);
            $('#material_semester_exam_id').val(data.semester_exam_id);

            var form = $('#material-form');
            form.find('[name="student_book_material"]').val(data.student_book_material);
            form.find('[name="workbook_material"]').val(data.workbook_material);
            form.find('[name="notebook_material"]').val(data.notebook_material);
            form.find('[name="description"]').val(data.description);
            form.find('[name="teacher_notes"]').val(data.teacher_notes);
            form.find('[name="status"]').val(data.status);
            $('#material-modal').fadeIn(200);
        });

        $('#bulk-add-subjects').on('click', function () {
            if (!confirm('<?php echo Olama_School_Helpers::translate('This will initialize all subjects for this grade in the selected exam. Continue?'); ?>')) return;

            var data = {
                action: 'olama_bulk_add_exam_subjects',
                nonce: $('#olama_exam_nonce_field').val(),
                academic_year_id: '<?php echo $selected_year_id; ?>',
                semester_id: '<?php echo $selected_semester_id; ?>',
                semester_exam_id: '<?php echo $selected_semester_exam_id; ?>',
                grade_id: '<?php echo $selected_grade_id; ?>'
            };

            var btn = $(this);
            btn.prop('disabled', true).text('<?php _e('Processing...', 'olama-school'); ?>');

            $.post(ajaxurl, data, function (response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Init All Subjects'); ?>');
                }
            });
        });

        $('.olama-modal-close').on('click', function () {
            $(this).closest('.olama-modal').fadeOut(200);
        });

        $('#date-form, #material-form, #exam-form').on('submit', function (e) {
            e.preventDefault();
            var form = $(this);

            // Date validation for forms that have exam_date
            var dateVal = form.find('[name="exam_date"]').val();
            if (dateVal && !validateDate(dateVal)) {
                return;
            }

            var formData = form.serialize() + '&action=olama_save_exam&nonce=' + $('#olama_exam_nonce_field').val();

            $.post(ajaxurl, formData, function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
    });
</script>