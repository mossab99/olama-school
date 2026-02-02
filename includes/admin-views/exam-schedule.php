<?php
/**
 * Exam Schedule View - Modal Layout
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get semester exams for the dropdown
$semester_exams = Olama_School_Academic::get_semester_exams($selected_semester_id);
$active_exam = Olama_School_Academic::get_active_exam($selected_semester_id);
$selected_semester_exam_id = isset($_GET['semester_exam_id']) ? intval($_GET['semester_exam_id']) : ($active_exam ? $active_exam->id : (!empty($semester_exams) ? $semester_exams[0]->id : 0));

$exams = Olama_School_Exam::get_exams($selected_year_id, $selected_semester_id, $selected_grade_id, $selected_subject_id, $selected_semester_exam_id);
?>

<div class="wrap olama-exam-wrap">
    <div class="olama-header-section"
        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="margin: 0;"><?php echo Olama_School_Helpers::translate('Exam Schedule'); ?> DEBUG_IDENTIFIER_123</h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" id="bulk-add-subjects" class="button button-secondary" 
                <?php echo (!$selected_semester_exam_id || !$selected_grade_id) ? 'disabled' : ''; ?>>
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
                <label style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Year'); ?></label>
                <select name="academic_year_id" onchange="this.form.submit();">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                            <?php echo esc_html($y->year_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Semester'); ?></label>
                <select name="semester_id" onchange="this.form.submit();">
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                            <?php echo esc_html($s->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Active Exam'); ?></label>
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
                <label style="display:block; font-size:11px; color:#666;"><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
                <select name="grade_id" onchange="this.form.submit();">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                            <?php echo esc_html($g->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button button-secondary" style="margin-top: 15px;"><?php echo Olama_School_Helpers::translate('Search'); ?></button>
        </form>
    </div>

    <!-- Full Width Table -->
    <div class="olama-table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Subject'); ?></th>
                    <th style="font-weight: 600; width: 20%;"><?php echo Olama_School_Helpers::translate('Material'); ?></th>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Date'); ?></th>
                    <th style="font-weight: 600; width: 10%;"><?php echo Olama_School_Helpers::translate('Room'); ?></th>
                    <th style="font-weight: 600; width: 15%;"><?php echo Olama_School_Helpers::translate('Status'); ?></th>
                    <th style="width: 120px; text-align: center; font-weight: 600;"><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
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
                                $se_info = array_filter($semester_exams, function($e) use ($exam) { return $e->id == $exam->semester_exam_id; });
                                $se_info = reset($se_info);
                                echo $se_info ? esc_html($se_info->exam_name) : esc_html($exam->evaluation_type); 
                            ?></td>
                            <td><?php echo $exam->formatted_date; ?></td>
                            <td><?php echo esc_html($exam->room_number); ?></td>
                            <td>
                                <span style="display:inline-block; padding: 2px 8px; border-radius: 12px; background: <?php echo $status_color; ?>22; color: <?php echo $status_color; ?>; font-size: 11px; font-weight: 600;">
                                    <?php echo $status_label; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="button button-small edit-exam"
                                    data-exam='<?php echo json_encode($exam); ?>' title="<?php echo Olama_School_Helpers::translate('Edit'); ?>">
                                    <span class="dashicons dashicons-edit"></span>
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
                        <label><?php echo Olama_School_Helpers::translate('Active Exam'); ?> *</label>
                        <select name="semester_exam_id" required>
                            <option value=""><?php echo Olama_School_Helpers::translate('Choose'); ?></option>
                            <?php foreach ($semester_exams as $se): ?>
                                <option value="<?php echo $se->id; ?>"><?php echo esc_html($se->exam_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Material'); ?> *</label>
                        <select name="subject_id" required>
                            <option value=""><?php echo Olama_School_Helpers::translate('Choose'); ?></option>
                            <?php foreach ($subjects as $sb): ?>
                                <option value="<?php echo $sb->id; ?>"><?php echo esc_html($sb->subject_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Exam Date'); ?> *</label>
                        <input type="text" name="exam_date" required class="olama-datepicker" autocomplete="off">
                    </div>

                    <div class="form-field">
                        <label><?php echo Olama_School_Helpers::translate('Room'); ?></label>
                        <input type="text" name="room_number" placeholder="<?php echo Olama_School_Helpers::translate('Room Name'); ?>">
                    </div>

                    <div class="form-field full-width">
                        <label><?php echo Olama_School_Helpers::translate('Exam Description'); ?></label>
                        <textarea name="description" rows="3" placeholder="<?php echo Olama_School_Helpers::translate('Detailed exam description...'); ?>"></textarea>
                    </div>
                    
                    <input type="hidden" name="status" value="draft">
                </div>

                <div class="olama-modal-footer">
                    <button type="submit" id="submit-exam-btn"
                        class="button button-primary button-large"><?php echo Olama_School_Helpers::translate('Add Exam'); ?></button>
                    <button type="button" id="cancel-modal-btn"
                        class="button"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .olama-exam-wrap { max-width: 1200px; margin: 20px auto; }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .olama-modal { position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; }
    .olama-modal-content { background-color: #fff; padding: 0; border-radius: 8px; width: 100%; max-width: 700px; position: relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    .olama-modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .olama-modal-header h2 { margin: 0; font-size: 18px; color: #1e293b; }
    .olama-modal-close { font-size: 28px; font-weight: bold; color: #94a3b8; cursor: pointer; line-height: 1; }
    .olama-form-grid { padding: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-height: 70vh; overflow-y: auto; }
    .form-field { display: flex; flex-direction: column; gap: 8px; }
    .form-field.full-width { grid-column: span 2; }
    .form-field label { font-weight: 600; font-size: 13px; color: #475569; }
    .form-field select, .form-field input, .form-field textarea { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; }
    .olama-modal-footer { padding: 20px 25px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-exam-wrap { direction: rtl; }
        .olama-modal-footer { justify-content: flex-start; }
    <?php endif; ?>
</style>

<script>
    jQuery(document).ready(function ($) {
        var modal = $('#exam-modal');
        var form = $('#exam-form');

        $('#open-add-exam-modal').on('click', function () {
            form[0].reset();
            $('#exam_id').val('');
            $('#form-title').text('<?php echo Olama_School_Helpers::translate('Add Exam Subject'); ?>');
            $('#submit-exam-btn').text('<?php echo Olama_School_Helpers::translate('Add Exam'); ?>');
            modal.fadeIn(200);
        });

        $('#bulk-add-subjects').on('click', function() {
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

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Init All Subjects'); ?>');
                }
            }).fail(function(xhr, status, error) {
                alert('Request failed. Status: ' + xhr.status + ', Error: ' + error + '\nResponse: ' + xhr.responseText);
                btn.prop('disabled', false).text('<?php echo Olama_School_Helpers::translate('Init All Subjects'); ?>');
            });
        });

        $('.olama-modal-close, #cancel-modal-btn').on('click', function () {
            modal.fadeOut(200);
        });

        $('.edit-exam').on('click', function () {
            var data = $(this).data('exam');
            $('#exam_id').val(data.id);
            form.find('[name="semester_exam_id"]').val(data.semester_exam_id);
            form.find('[name="subject_id"]').val(data.subject_id);
            form.find('[name="exam_date"]').val(data.formatted_date);
            form.find('[name="room_number"]').val(data.room_number);
            form.find('[name="description"]').val(data.description);
            form.find('[name="status"]').val(data.status);

            $('#form-title').text('<?php echo Olama_School_Helpers::translate('Update'); ?>');
            $('#submit-exam-btn').text('<?php echo Olama_School_Helpers::translate('Update Exam'); ?>');
            modal.fadeIn(200);
        });

        form.on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize() + '&action=olama_save_exam&nonce=' + $('#olama_exam_nonce_field').val();
            
            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
    });
</script>
