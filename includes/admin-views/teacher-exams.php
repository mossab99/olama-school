<?php
/**
 * Teacher Exams View
 */
if (!defined('ABSPATH')) {
    exit;
}

$teacher_id = get_current_user_id();
$exams = Olama_School_Exam::get_teacher_exams($teacher_id, $selected_year_id, $selected_exam_id);

// Get semester exams for the dropdown
$semester_exams = Olama_School_Academic::get_semester_exams($selected_semester_id);
?>

<div class="wrap olama-teacher-exams-wrap">
    <div class="olama-header-section" style="margin-bottom: 20px;">
        <h1>
            <?php echo Olama_School_Helpers::translate('Teacher Exams'); ?>
        </h1>
    </div>

    <!-- Filter Bar -->
    <div class="olama-filter-bar"
        style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; border-radius: 4px;">
        <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <input type="hidden" name="page" value="olama-school-exams">
            <input type="hidden" name="tab" value="teacher_exams">

            <div class="filter-group">
                <label style="display:block; font-size:11px; color:#666;">
                    <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                </label>
                <select name="academic_year_id" onchange="this.form.submit();">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                            <?php echo esc_html($y->year_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label style="display:block; font-size:11px; color:#666;">
                    <?php echo Olama_School_Helpers::translate('Semester'); ?>
                </label>
                <select name="semester_id" onchange="this.form.submit();">
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                            <?php echo esc_html($s->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label style="display:block; font-size:11px; color:#666;">
                    <?php echo Olama_School_Helpers::translate('Active Exam'); ?>
                </label>
                <select name="semester_exam_id" onchange="this.form.submit();">
                    <option value="0">
                        <?php echo Olama_School_Helpers::translate('Choose Exam'); ?>
                    </option>
                    <?php foreach ($semester_exams as $se): ?>
                        <option value="<?php echo $se->id; ?>" <?php selected($selected_exam_id, $se->id); ?>>
                            <?php echo esc_html($se->exam_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button button-secondary" style="margin-top: 15px;">
                <?php echo Olama_School_Helpers::translate('Search'); ?>
            </button>
        </form>
    </div>

    <div class="olama-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Grade'); ?>
                    </th>
                    <th style="font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Subject'); ?>
                    </th>
                    <th style="font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Date'); ?>
                    </th>
                    <th style="font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Room'); ?>
                    </th>
                    <th style="font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Status'); ?>
                    </th>
                    <th style="width: 150px; text-align: center; font-weight: 600;">
                        <?php echo Olama_School_Helpers::translate('Actions'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <?php echo Olama_School_Helpers::translate('No subjects assigned for the selected exam.'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam):
                        $exam->formatted_date = Olama_School_Helpers::format_date($exam->exam_date);
                        $status_label = ($exam->status === 'completed') ? Olama_School_Helpers::translate('Completed') : Olama_School_Helpers::translate('Not Completed');
                        $status_color = ($exam->status === 'completed') ? '#10b981' : '#f59e0b';
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($exam->grade_name); ?>
                            </td>
                            <td>
                                <?php echo esc_html($exam->subject_name); ?>
                            </td>
                            <td>
                                <?php echo $exam->formatted_date; ?>
                            </td>
                            <td>
                                <?php echo esc_html($exam->room_number); ?>
                            </td>
                            <td>
                                <span
                                    style="display:inline-block; padding: 2px 8px; border-radius: 12px; background: <?php echo $status_color; ?>22; color: <?php echo $status_color; ?>; font-size: 11px; font-weight: 600;">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button button-primary fill-details"
                                    data-exam='<?php echo json_encode($exam); ?>'>
                                    <?php echo Olama_School_Helpers::translate('Enter Details'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Teacher Modal -->
    <div id="teacher-exam-modal" class="olama-modal" style="display: none;">
        <div class="olama-modal-content">
            <div class="olama-modal-header">
                <h2>
                    <?php echo Olama_School_Helpers::translate('Exam Details'); ?> - <span
                        id="subject-name-title"></span>
                </h2>
                <span class="olama-modal-close">&times;</span>
            </div>

            <form id="teacher-exam-form" method="post">
                <?php wp_nonce_field('olama_save_exam', 'olama_exam_nonce_field'); ?>
                <input type="hidden" name="id" id="t_exam_id" value="">
                <input type="hidden" name="academic_year_id" id="t_year_id" value="">
                <input type="hidden" name="semester_id" id="t_semester_id" value="">
                <input type="hidden" name="semester_exam_id" id="t_se_exam_id" value="">
                <input type="hidden" name="grade_id" id="t_grade_id" value="">
                <input type="hidden" name="subject_id" id="t_subject_id" value="">
                <input type="hidden" name="status" value="completed">

                <div class="olama-form-grid">
                    <div class="form-field full-width">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Exam Description'); ?> *
                        </label>
                        <textarea name="description" required rows="3"></textarea>
                    </div>

                    <div class="form-field full-width">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Student Book'); ?> *
                        </label>
                        <textarea name="student_book_material" required rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Workbook'); ?>
                        </label>
                        <textarea name="workbook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Exercise Notebook'); ?>
                        </label>
                        <textarea name="exercise_book_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Notebook'); ?>
                        </label>
                        <textarea name="notebook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label>
                            <?php echo Olama_School_Helpers::translate('Teacher Notes'); ?> *
                        </label>
                        <textarea name="teacher_notes" required rows="2"></textarea>
                    </div>
                </div>

                <div class="olama-modal-footer">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo Olama_School_Helpers::translate('Save Details'); ?>
                    </button>
                    <button type="button" class="button cancel-modal">
                        <?php echo Olama_School_Helpers::translate('Cancel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .olama-teacher-exams-wrap {
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
        max-width: 750px;
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
    }

    .olama-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #94a3b8;
        cursor: pointer;
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

    <?php if (Olama_School_Helpers::is_arabic()): ?>.olama-teacher-exams-wrap {
            direction: rtl;
        }

        .olama-modal-footer {
            justify-content: flex-start;
        }

    <?php endif; ?>
</style>

<script>
    jQuery(document).ready(function ($) {
        var modal = $('#teacher-exam-modal');
        var form = $('#teacher-exam-form');

        $('.fill-details').on('click', function () {
            var data = $(this).data('exam');
            $('#t_exam_id').val(data.id);
            $('#t_year_id').val(data.academic_year_id);
            $('#t_semester_id').val(data.semester_id);
            $('#t_se_exam_id').val(data.semester_exam_id);
            $('#t_grade_id').val(data.grade_id);
            $('#t_subject_id').val(data.subject_id);

            $('#subject-name-title').text(data.subject_name);

            form.find('[name="description"]').val(data.description);
            form.find('[name="student_book_material"]').val(data.student_book_material);
            form.find('[name="workbook_material"]').val(data.workbook_material);
            form.find('[name="exercise_book_material"]').val(data.exercise_book_material);
            form.find('[name="notebook_material"]').val(data.notebook_material);
            form.find('[name="teacher_notes"]').val(data.teacher_notes);

            modal.fadeIn(200);
        });

        $('.olama-modal-close, .cancel-modal').on('click', function () {
            modal.fadeOut(200);
        });

        form.on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize() + '&action=olama_save_exam&nonce=' + $('#olama_exam_nonce_field').val();

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