<?php
/**
 * Exam Schedule View - Modal Layout
 */
if (!defined('ABSPATH')) {
    exit;
}

$exams = Olama_School_Exam::get_exams($selected_year_id, $selected_semester_id, $selected_grade_id, $selected_subject_id);

$evaluation_types = array(
    'التقويم الاول' => Olama_School_Helpers::translate('First Exam'),
    'التقويم الثاني' => Olama_School_Helpers::translate('Second Exam'),
    'الامتحان النهائي' => Olama_School_Helpers::translate('Final Exam'),
);
?>

<div class="wrap olama-exam-wrap">
    <div class="olama-header-section" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;"><?php echo Olama_School_Helpers::translate('Exam Schedule'); ?></h1>
        <button type="button" id="open-add-exam-modal" class="button button-primary button-large" style="display: flex; align-items: center; gap: 5px;">
            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
            <?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?>
        </button>
    </div>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'exam_saved'): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo Olama_School_Helpers::translate('Exam saved successfully.'); ?></p></div>
    <?php elseif (isset($_GET['message']) && $_GET['message'] === 'exam_deleted'): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo Olama_School_Helpers::translate('Exam deleted successfully.'); ?></p></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="olama-filter-bar" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; border-radius: 4px;">
        <form method="get" action="" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <input type="hidden" name="page" value="olama-school-academic">
            <input type="hidden" name="tab" value="exam_schedule">
            <select name="academic_year_id" style="min-width: 140px;">
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>><?php echo esc_html($y->year_name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="semester_id" style="min-width: 140px;">
                <?php foreach ($semesters as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>><?php echo esc_html($s->semester_name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="grade_id" onchange="this.form.subject_id.value=0; this.form.submit();" style="min-width: 140px;">
                <?php foreach ($grades as $g): ?>
                    <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>><?php echo esc_html($g->grade_name); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject_id" style="min-width: 140px;">
                <option value="0"><?php _e('All Subjects', 'olama-school'); ?></option>
                <?php foreach ($subjects as $sb): ?>
                    <option value="<?php echo $sb->id; ?>" <?php selected($selected_subject_id, $sb->id); ?>><?php echo esc_html($sb->subject_name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-secondary"><?php _e('Search', 'olama-school'); ?></button>
        </form>
    </div>

    <!-- Full Width Table -->
    <div class="olama-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="font-weight: 600;"><?php echo Olama_School_Helpers::translate('Evaluation'); ?></th>
                    <th style="font-weight: 600;"><?php echo Olama_School_Helpers::translate('Material'); ?></th>
                    <th style="font-weight: 600;"><?php echo Olama_School_Helpers::translate('Date'); ?></th>
                    <th style="font-weight: 600;"><?php echo Olama_School_Helpers::translate('Description'); ?></th>
                    <th style="width: 100px; text-align: center; font-weight: 600;"><?php _e('Actions', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($exams)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                            <?php echo Olama_School_Helpers::translate('No exams found for the selected criteria.'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td><strong><?php echo esc_html($evaluation_types[$exam->evaluation_type] ?? $exam->evaluation_type); ?></strong></td>
                            <td><?php 
                                $sb_info = Olama_School_Subject::get_subject($exam->subject_id);
                                echo $sb_info ? esc_html($sb_info->subject_name) : __('Unknown', 'olama-school'); 
                            ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($exam->exam_date)); ?></td>
                            <td><?php echo wp_trim_words(esc_html($exam->description), 10); ?></td>
                            <td style="text-align: center;">
                                <button type="button" class="button button-small edit-exam" data-exam='<?php echo json_encode($exam); ?>' title="<?php _e('Edit', 'olama-school'); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=exam_schedule&action=delete_exam&exam_id=' . $exam->id), 'olama_delete_exam_' . $exam->id); ?>" 
                                   class="button button-small" onclick="return confirm('<?php _e('Are you sure?', 'olama-school'); ?>')" title="<?php _e('Delete', 'olama-school'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Form -->
    <div id="exam-modal" class="olama-modal" style="display: none;">
        <div class="olama-modal-content">
            <div class="olama-modal-header">
                <h2 id="form-title"><?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?></h2>
                <span class="olama-modal-close">&times;</span>
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
                        <label><?php echo Olama_School_Helpers::translate('Evaluation'); ?> *</label>
                        <select name="evaluation_type" required>
                            <option value=""><?php echo Olama_School_Helpers::translate('Choose'); ?></option>
                            <?php foreach ($evaluation_types as $val => $label): ?>
                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Material'); ?> *</label>
                        <select name="subject_id" required>
                            <option value=""><?php _e('Choose the subject', 'olama-school'); ?></option>
                            <?php foreach ($subjects as $sb): ?>
                                <option value="<?php echo $sb->id; ?>" <?php selected($selected_subject_id, $sb->id); ?>><?php echo esc_html($sb->subject_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Exam Date'); ?> *</label>
                        <input type="date" name="exam_date" required>
                    </div>

                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Exam Description'); ?> *</label>
                        <textarea name="description" required rows="3" placeholder="<?php echo Olama_School_Helpers::translate('Detailed exam description...'); ?>"></textarea>
                    </div>

                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Student Book Material'); ?> *</label>
                        <textarea name="student_book_material" required rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Workbook'); ?></label>
                        <textarea name="workbook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Exercise'); ?></label>
                        <textarea name="exercise_book_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Notebook'); ?></label>
                        <textarea name="notebook_material" rows="2"></textarea>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Teacher Notes'); ?> *</label>
                        <textarea name="teacher_notes" required rows="2"></textarea>
                    </div>
                </div>

                <div class="olama-modal-footer">
                    <button type="submit" id="submit-exam-btn" class="button button-primary button-large"><?php echo Olama_School_Helpers::translate('Add Exam'); ?></button>
                    <button type="submit" name="apply_new" id="apply-new-btn" class="button button-secondary"><?php echo Olama_School_Helpers::translate('Apply and Add New'); ?></button>
                    <button type="button" id="cancel-modal-btn" class="button"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.olama-exam-wrap { max-width: 1200px; margin: 20px auto; }

/* Modal Styles */
.olama-modal {
    position: fixed;
    z-index: 99999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
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
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.olama-modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.olama-modal-header h2 { margin: 0; font-size: 18px; color: #1e293b; }

.olama-modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
}

.olama-modal-close:hover { color: #1e293b; }

.olama-form-grid {
    padding: 25px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    max-height: 70vh;
    overflow-y: auto;
}

.form-field { display: flex; flex-direction: column; gap: 8px; }
.form-field.full-width { grid-column: span 2; }

.form-field label { font-weight: 600; font-size: 13px; color: #475569; }
.form-field select, .form-field input, .form-field textarea {
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

/* RTL Support */
<?php if (Olama_School_Helpers::is_arabic()): ?>
.olama-exam-wrap { direction: rtl; }
.olama-modal-footer { justify-content: flex-start; }
<?php endif; ?>

@media (max-width: 768px) {
    .olama-form-grid { grid-template-columns: 1fr; }
    .form-field.full-width { grid-column: span 1; }
    .olama-modal-content { margin: 20px; }
}
</style>

<script>
jQuery(document).ready(function($) {
    var modal = $('#exam-modal');
    var form = $('#exam-form');
    var applyNew = false;
    
    // Modal controls
    $('#open-add-exam-modal').on('click', function() {
        form[0].reset();
        $('#exam_id').val('');
        $('#form-title').text('<?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?>');
        $('#submit-exam-btn').text('<?php echo Olama_School_Helpers::translate('Add Exam'); ?>');
        $('#apply-new-btn').show();
        modal.fadeIn(200);
    });

    $('.olama-modal-close, #cancel-modal-btn').on('click', function() {
        modal.fadeOut(200);
    });

    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.fadeOut(200);
        }
    });

    var getNonce = function() {
        return $('#olama_exam_nonce_field').val();
    };

    // Dynamic Filters
    $('select[name="academic_year_id"]').on('change', function() {
        var yearId = $(this).val();
        var $semesterSelect = $('select[name="semester_id"]');
        $semesterSelect.prop('disabled', true);
        $.get(ajaxurl, { action: 'olama_get_semesters', year_id: yearId }, function(response) {
            if (response.success) {
                var html = '';
                $.each(response.data, function(i, s) {
                    html += '<option value="' + s.id + '">' + s.semester_name + '</option>';
                });
                $semesterSelect.html(html).prop('disabled', false);
            }
        });
    });

    // Form Submission
    form.on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var isApplyAndAdd = applyNew;
        
        formData += '&action=olama_save_exam&nonce=' + getNonce();

        var $submitBtn = $('#submit-exam-btn');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('<?php _e('Saving...', 'olama-school'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response && response.success) {
                    if (isApplyAndAdd) {
                        var evaluation = form.find('[name="evaluation_type"]').val();
                        var subject = form.find('[name="subject_id"]').val();
                        var date = form.find('[name="exam_date"]').val();
                        
                        form[0].reset();
                        $('#exam_id').val('');
                        
                        form.find('[name="evaluation_type"]').val(evaluation);
                        form.find('[name="subject_id"]').val(subject);
                        form.find('[name="exam_date"]').val(date);

                        $('#form-title').text('<?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?>');
                        alert(response.data.message || '<?php echo Olama_School_Helpers::translate('Exam saved successfully.'); ?>');
                    } else {
                        window.location.reload();
                    }
                } else {
                    var msg = (response && response.data) ? response.data : '<?php _e('Unknown error occurred.', 'olama-school'); ?>';
                    alert('Error: ' + msg);
                }
            },
            error: function(xhr, status, error) {
                alert('<?php _e('An error occurred during save.', 'olama-school'); ?>: ' + error);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
                applyNew = false;
            }
        });
    });

    $('#apply-new-btn').on('click', function() {
        applyNew = true;
    });

    $('.edit-exam').on('click', function() {
        var data = $(this).data('exam');
        $('#exam_id').val(data.id);
        form.find('[name="evaluation_type"]').val(data.evaluation_type);
        form.find('[name="subject_id"]').val(data.subject_id);
        form.find('[name="exam_date"]').val(data.exam_date);
        form.find('[name="description"]').val(data.description);
        form.find('[name="student_book_material"]').val(data.student_book_material);
        form.find('[name="workbook_material"]').val(data.workbook_material);
        form.find('[name="exercise_book_material"]').val(data.exercise_book_material);
        form.find('[name="notebook_material"]').val(data.notebook_material);
        form.find('[name="teacher_notes"]').val(data.teacher_notes);

        $('#form-title').text('<?php echo Olama_School_Helpers::translate('Update'); ?>');
        $('#submit-exam-btn').text('<?php echo Olama_School_Helpers::translate('Update Exam'); ?>');
        $('#apply-new-btn').hide();
        
        modal.fadeIn(200);
    });
});
</script>