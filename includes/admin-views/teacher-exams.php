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
        <div class="olama-modal-content" style="max-width: 800px;">
            <div class="olama-modal-header">
                <h2>
                    <?php echo Olama_School_Helpers::translate('Exam Details'); ?> - <span
                        id="subject-name-title"></span>
                </h2>
                <span class="olama-modal-close" onclick="closeModal('teacher-exam-modal')">&times;</span>
            </div>
            <form id="teacher-exam-form" method="post">
                <?php wp_nonce_field('olama_save_exam', 'olama_exam_nonce_field'); ?>
                <input type="hidden" name="id" id="t_exam_id">
                <input type="hidden" name="academic_year_id" id="t_year_id">
                <input type="hidden" name="semester_id" id="t_semester_id">
                <input type="hidden" name="semester_exam_id" id="t_se_exam_id">
                <input type="hidden" name="grade_id" id="t_grade_id">
                <input type="hidden" name="subject_id" id="t_subject_id">
                <input type="hidden" name="exam_material_json" id="exam_material_json">
                <input type="hidden" name="status" value="completed">
                <input type="hidden" name="action" value="olama_save_exam">

                <div style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                    <!-- Description Section (Added back as requested by visual consistency) -->
                    <div class="material-section">
                        <h3
                            style="margin: 0 0 10px; font-size: 14px; color: #475569; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            <?php echo Olama_School_Helpers::translate('Exam Description'); ?>
                        </h3>
                        <textarea name="description" id="t_description" rows="2" style="width: 100%;"
                            placeholder="<?php echo Olama_School_Helpers::translate('Enter general description or topic...'); ?>"></textarea>
                    </div>

                    <!-- Curriculum Material Section -->
                    <div class="material-section" style="margin-top: 20px;">
                        <h3
                            style="margin: 0 0 15px; font-size: 14px; color: #475569; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            <?php echo Olama_School_Helpers::translate('Curriculum Material'); ?>
                        </h3>
                        <table id="curriculum-material-table" class="widefat" style="margin-bottom: 10px;">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php echo Olama_School_Helpers::translate('Unit'); ?></th>
                                    <th style="width: 30%;"><?php echo Olama_School_Helpers::translate('Lesson'); ?>
                                    </th>
                                    <th style="width: 35%;">
                                        <?php echo Olama_School_Helpers::translate('Required Material'); ?></th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="curriculum-rows">
                                <!-- Dynamic rows will be added here -->
                            </tbody>
                        </table>
                        <button type="button" id="add-curriculum-row" class="button button-secondary">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                            <?php echo Olama_School_Helpers::translate('Add Row'); ?>
                        </button>
                    </div>

                    <!-- Booklets & Notebooks Section -->
                    <div class="material-section" style="margin-top: 20px;">
                        <h3
                            style="margin: 0 0 10px; font-size: 14px; color: #475569; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            <?php echo Olama_School_Helpers::translate('Booklets & Notebooks'); ?>
                        </h3>
                        <textarea id="booklets_notebooks" rows="3" style="width: 100%;"
                            placeholder="<?php echo Olama_School_Helpers::translate('e.g., Worksheet #3, Notebook entries from week 2'); ?>"></textarea>
                    </div>

                    <!-- Teacher Notes Section -->
                    <div class="material-section" style="margin-top: 20px;">
                        <h3
                            style="margin: 0 0 10px; font-size: 14px; color: #475569; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                            <?php echo Olama_School_Helpers::translate('Teacher Notes'); ?>
                        </h3>
                        <textarea name="teacher_notes" id="material_teacher_notes" rows="2"
                            style="width: 100%;"></textarea>
                    </div>
                </div>

                <div class="olama-modal-footer">
                    <button type="submit"
                        class="button button-primary"><?php echo Olama_School_Helpers::translate('Save Details'); ?></button>
                    <button type="button" class="button cancel-modal"
                        onclick="closeModal('teacher-exam-modal')"><?php echo Olama_School_Helpers::translate('Cancel'); ?></button>
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
        width: 95%;
        max-width: 800px;
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

    .material-section {
        margin-bottom: 20px;
    }

    .olama-modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-teacher-exams-wrap {
            direction: rtl;
        }

        .olama-modal-footer {
            justify-content: flex-start;
        }

    <?php endif; ?>
</style>

<script>
    function closeModal(id) { jQuery('#' + id).fadeOut(200); }

    jQuery(document).ready(function ($) {
        var currentUnits = [];
        var unitLessonsCache = {};

        function createCurriculumRow(unitId, lessonId, material) {
            var gradeId = $('#t_grade_id').val();
            var subjectId = $('#t_subject_id').val();
            var semesterId = $('#t_semester_id').val();

            var row = $('<tr class="curriculum-row">' +
                '<td><select class="unit-select" style="width: 100%;"><option value="">-- <?php echo Olama_School_Helpers::translate('Select Unit'); ?> --</option></select></td>' +
                '<td><select class="lesson-select" style="width: 100%;"><option value="">-- <?php echo Olama_School_Helpers::translate('Select Lesson'); ?> --</option></select></td>' +
                '<td><input type="text" class="material-input" style="width: 100%;" placeholder="<?php echo Olama_School_Helpers::translate('e.g., Pages 10-15'); ?>"></td>' +
                '<td><button type="button" class="button button-small remove-row" style="color: #dc2626;"><span class="dashicons dashicons-no"></span></button></td>' +
                '</tr>');

            if (currentUnits.length > 0) {
                var unitSelect = row.find('.unit-select');
                currentUnits.forEach(function (unit) { unitSelect.append('<option value="' + unit.id + '">' + unit.unit_name + '</option>'); });
                if (unitId) { unitSelect.val(unitId); loadLessonsForRow(row, unitId, lessonId); }
            } else {
                loadUnits(gradeId, subjectId, semesterId, function (units) {
                    currentUnits = units;
                    var unitSelect = row.find('.unit-select');
                    units.forEach(function (unit) { unitSelect.append('<option value="' + unit.id + '">' + unit.unit_name + '</option>'); });
                    if (unitId) { unitSelect.val(unitId); loadLessonsForRow(row, unitId, lessonId); }
                });
            }
            if (material) row.find('.material-input').val(material);
            return row;
        }

        function loadUnits(gradeId, subjectId, semesterId, callback) {
            $.post(ajaxurl, { action: 'olama_get_units', grade_id: gradeId, subject_id: subjectId, semester_id: semesterId }, function (response) {
                if (response.success) { callback(response.data); } else { callback([]); }
            });
        }

        function loadLessonsForRow(row, unitId, selectedLessonId) {
            if (unitLessonsCache[unitId]) { populateLessons(row, unitLessonsCache[unitId], selectedLessonId); } else {
                $.post(ajaxurl, { action: 'olama_get_lessons', unit_id: unitId }, function (response) {
                    if (response.success) { unitLessonsCache[unitId] = response.data; populateLessons(row, response.data, selectedLessonId); }
                });
            }
        }

        function populateLessons(row, lessons, selectedLessonId) {
            var lessonSelect = row.find('.lesson-select');
            lessonSelect.empty().append('<option value="">-- <?php echo Olama_School_Helpers::translate('Select Lesson'); ?> --</option>');
            lessons.forEach(function (lesson) { lessonSelect.append('<option value="' + lesson.id + '">' + lesson.lesson_title + '</option>'); });
            if (selectedLessonId) lessonSelect.val(selectedLessonId);
        }

        function serializeCurriculumData() {
            var items = [];
            $('#curriculum-rows tr.curriculum-row').each(function () {
                var unitId = $(this).find('.unit-select').val();
                var lessonId = $(this).find('.lesson-select').val();
                var material = $(this).find('.material-input').val();
                if (unitId || lessonId || material) {
                    items.push({ unit_id: unitId ? parseInt(unitId) : null, lesson_id: lessonId ? parseInt(lessonId) : null, material: material });
                }
            });
            return { curriculum_items: items, booklets_notebooks: $('#booklets_notebooks').val(), teacher_notes: $('#material_teacher_notes').val() };
        }

        $('.fill-details').on('click', function () {
            var data = $(this).data('exam');
            $('#t_exam_id').val(data.id);
            $('#t_year_id').val(data.academic_year_id);
            $('#t_semester_id').val(data.semester_id);
            $('#t_se_exam_id').val(data.semester_exam_id);
            $('#t_grade_id').val(data.grade_id);
            $('#t_subject_id').val(data.subject_id);
            $('#subject-name-title').text(data.subject_name);
            $('#t_description').val(data.description || '');

            $('#curriculum-rows').empty();
            currentUnits = [];
            unitLessonsCache = {};

            var materialJson = null;
            if (data.exam_material_json) {
                try { materialJson = JSON.parse(data.exam_material_json); } catch (e) { console.error('Invalid JSON:', e); }
            }

            if (materialJson && materialJson.curriculum_items && materialJson.curriculum_items.length > 0) {
                materialJson.curriculum_items.forEach(function (item) {
                    $('#curriculum-rows').append(createCurriculumRow(item.unit_id, item.lesson_id, item.material));
                });
                $('#booklets_notebooks').val(materialJson.booklets_notebooks || '');
                $('#material_teacher_notes').val(materialJson.teacher_notes || '');
            } else {
                $('#curriculum-rows').append(createCurriculumRow());
                $('#booklets_notebooks').val(data.notebook_material || '');
                $('#material_teacher_notes').val(data.teacher_notes || '');
            }
            $('#teacher-exam-modal').fadeIn(200);
        });

        $('#add-curriculum-row').on('click', function () { $('#curriculum-rows').append(createCurriculumRow()); });
        $(document).on('change', '.unit-select', function () {
            var row = $(this).closest('tr');
            var unitId = $(this).val();
            if (unitId) { loadLessonsForRow(row, unitId); } else {
                row.find('.lesson-select').empty().append('<option value="">-- <?php echo Olama_School_Helpers::translate('Select Lesson'); ?> --</option>');
            }
        });
        $(document).on('click', '.remove-row', function () { $(this).closest('tr').remove(); });
        $('.olama-modal-close, .cancel-modal').on('click', function () { closeModal('teacher-exam-modal'); });

        $('#teacher-exam-form').on('submit', function (e) {
            e.preventDefault();
            var jsonData = serializeCurriculumData();
            $('#exam_material_json').val(JSON.stringify(jsonData));
            var formData = $(this).serialize() + '&action=olama_save_exam&nonce=' + $('#olama_exam_nonce_field').val();
            $.post(ajaxurl, formData, function (response) {
                if (response.success) { window.location.reload(); } else { alert('Error: ' + response.data); }
            });
        });
    });
</script>