jQuery(document).ready(function ($) {
    'use strict';

    let currentGrade = null;
    let currentSubject = null;
    let currentUnit = null;
    let currentLesson = null;
    let currentSemester = null;
    let currentSubjectColor = '#2271b1'; // Default WordPress blue

    // --- Helpers ---
    // Helper function to apply subject color to sections
    function applySubjectColor(color) {
        const root = document.documentElement;
        root.style.setProperty('--subject-color', color);
        root.style.setProperty('--subject-color-light', color + '20'); // 20 = 12.5% opacity
        root.style.setProperty('--subject-color-hover', color + '15'); // 15 = 8% opacity
    }

    function showLoading(container) {
        $(container).html('<div class="olama-loading">Loading...</div>');
    }

    function toggleSection(sectionId, enabled) {
        const $section = $('#' + sectionId);
        if (enabled) {
            $section.removeClass('disabled').css('opacity', '1').css('pointer-events', 'auto');
            $section.find('button, input, select, textarea').prop('disabled', false);
        } else {
            $section.addClass('disabled').css('opacity', '0.5').css('pointer-events', 'none');
            $section.find('button, input, select, textarea').prop('disabled', true);
        }
    }

    function updateImportExportStatus() {
        const semesterId = $('#curriculum-semester').val();
        const gradeId = $('#curriculum-grade').val();
        const subjectId = $('#curriculum-subject').val();

        const isAllSelected = semesterId && gradeId && subjectId;

        // Update hidden fields in both forms
        $('.curriculum-hidden-semester').val(semesterId);
        $('.curriculum-hidden-grade').val(gradeId);
        $('.curriculum-hidden-subject').val(subjectId);

        // Enable/Disable buttons and file input
        $('#olama-export-curriculum-btn').prop('disabled', !isAllSelected);
        $('#olama-import-curriculum-file').prop('disabled', !isAllSelected);
        $('#olama-import-curriculum-btn').prop('disabled', !isAllSelected);
    }

    // --- Filter Interactions ---
    $('#curriculum-grade').on('change', function () {
        currentGrade = $(this).val();
        currentSubject = null;
        currentUnit = null;
        currentLesson = null;

        // Reset sub-sections
        $('#curriculum-subject').html('<option value="">' + olamaCurriculum.i18n.selectSubject + '</option>').prop('disabled', true);
        toggleSection('unit-section', false);
        toggleSection('lesson-section', false);
        toggleSection('question-section', false);

        if (currentGrade) {
            $.ajax({
                url: olamaCurriculum.ajaxUrl,
                data: {
                    action: 'olama_get_subjects_by_grade',
                    grade_id: currentGrade,
                    nonce: olamaCurriculum.nonce
                },
                success: function (response) {
                    if (response.success) {
                        let options = '<option value="">' + olamaCurriculum.i18n.selectSubject + '</option>';
                        response.data.forEach(function (subject) {
                            options += `<option value="${subject.id}" data-color="${subject.color_code || '#2271b1'}">${subject.subject_name}</option>`;
                        });
                        $('#curriculum-subject').html(options).prop('disabled', false);
                    }
                }
            });
        }
        updateImportExportStatus();
    });

    $('#curriculum-subject').on('change', function () {
        currentSubject = $(this).val();
        currentUnit = null;
        currentLesson = null;

        // Get and apply subject color
        const selectedOption = $(this).find('option:selected');
        currentSubjectColor = selectedOption.data('color') || '#2271b1';
        applySubjectColor(currentSubjectColor);

        $('#units-list').empty();
        $('#lessons-list').empty();
        $('#questions-list').empty();
        $('#lesson-form-container').hide();
        $('#add-lesson-btn').show();
        toggleSection('lesson-section', false);
        toggleSection('question-section', false);

        if (currentSubject) {
            currentSemester = $('#curriculum-semester').val();
            toggleSection('unit-section', true);
            loadUnits();
        } else {
            toggleSection('unit-section', false);
            // Reset to default color when no subject selected
            applySubjectColor('#2271b1');
        }
        updateImportExportStatus();
    });

    $('#curriculum-semester').on('change', function () {
        updateImportExportStatus();
        if (currentSubject) {
            loadUnits();
        }
    });

    // --- Unit Management ---
    function loadUnits() {
        showLoading('#units-list');
        const requestData = {
            action: 'olama_get_curriculum_units',
            subject_id: currentSubject,
            grade_id: currentGrade,
            semester_id: $('#curriculum-semester').val(),
            nonce: olamaCurriculum.nonce
        };
        console.log('Load Units Request:', requestData);
        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            data: requestData,
            success: function (response) {
                console.log('Load Units Response:', response);
                if (response.success) {
                    // Handle both old format (array) and new format (object with units)
                    const units = Array.isArray(response.data) ? response.data : (response.data.units || []);
                    renderUnits(units);
                }
            },
            error: function (xhr, status, error) {
                console.error('Load Units Error:', error);
            }
        });
    }

    function renderUnits(units) {
        let html = '';
        if (units.length === 0) {
            html = '<p>' + olamaCurriculum.i18n.noUnits + '</p>';
        } else {
            units.forEach(function (unit) {
                html += `
                <div class="olama-item unit-item" data-id="${unit.id}" data-name="${unit.unit_name}" data-number="${unit.unit_number || ''}" data-objectives="${unit.objectives || ''}">
                    <div class="item-info">
                        <strong>${unit.unit_number ? unit.unit_number + ' - ' : ''}${unit.unit_name}</strong>
                        ${parseInt(unit.lesson_count) === 0
                        ? `<span class="empty-unit-indicator" title="${olamaCurriculum.i18n.noLessons}"></span>`
                        : `<span class="lesson-count-badge">(${unit.lesson_count})</span>`}
                    </div>
                    <div class="item-actions">
                        <button class="button edit-unit" data-id="${unit.id}">${olamaCurriculum.i18n.edit}</button>
                        <button class="button delete-unit" data-id="${unit.id}">${olamaCurriculum.i18n.delete}</button>
                    </div>
                </div>`;
            });
        }
        $('#units-list').html(html);
    }

    $(document).on('click', '.unit-item', function (e) {
        if ($(e.target).closest('.item-actions').length) return;

        $('.unit-item').removeClass('active');
        $(this).addClass('active');

        currentUnit = $(this).data('id');
        toggleSection('lesson-section', true);
        toggleSection('question-section', false);
        loadLessons();
    });

    $('#add-unit-btn').on('click', function () {
        $('#unit-id').val('');
        $('#unit-name').val('');
        $('#unit-number').val('');
        $('#unit-objectives').val('');
        $('#unit-form-container').show();
        $(this).hide();
    });

    $('.cancel-unit-btn').on('click', function () {
        $('#unit-form-container').hide();
        $('#add-unit-btn').show();
    });

    $('.save-unit-btn').on('click', function () {
        const data = {
            action: 'olama_save_curriculum_unit',
            id: $('#unit-id').val(),
            unit_name: $('#unit-name').val(),
            unit_number: $('#unit-number').val(),
            subject_id: currentSubject,
            grade_id: currentGrade,
            semester_id: $('#curriculum-semester').val(),
            objectives: $('#unit-objectives').val() || '',
            nonce: olamaCurriculum.nonce
        };

        console.log('Save Unit Data:', data);

        if (!data.unit_number) return alert(olamaCurriculum.i18n.unitNumberRequired);
        if (!data.unit_name) return alert(olamaCurriculum.i18n.unitNameRequired);

        // Client-side duplicate check
        let isDuplicate = false;
        $('.unit-item').each(function () {
            if ($(this).data('id') != data.id && $(this).data('number') == data.unit_number) {
                isDuplicate = true;
                return false;
            }
        });
        if (isDuplicate) return alert(olamaCurriculum.i18n.unitExists.replace('#', '#' + data.unit_number));

        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: data,
            success: function (response) {
                console.log('Save Unit Response:', response);
                if (response.success) {
                    $('#unit-form-container').hide();
                    $('#add-unit-btn').show();
                    loadUnits();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function (xhr, status, error) {
                console.error('Save Unit Error:', error);
                alert('AJAX Error: ' + error);
            }
        });
    });
    $(document).on('click', '.edit-unit', function () {
        const id = $(this).data('id');
        const item = $(this).closest('.unit-item');
        $('#unit-id').val(id);
        $('#unit-name').val(item.data('name'));
        $('#unit-number').val(item.data('number'));
        $('#unit-objectives').val(item.data('objectives'));
        $('#unit-form-container').show();
        $('#add-unit-btn').hide();
    });

    $(document).on('click', '.delete-unit', function () {
        if (!confirm(olamaCurriculum.i18n.confirmDelete)) return;
        const id = $(this).data('id');
        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_delete_curriculum_unit',
                id: id,
                nonce: olamaCurriculum.nonce
            },
            success: function (response) {
                if (response.success) {
                    loadUnits();
                    if (currentUnit == id) {
                        currentUnit = null;
                        toggleSection('lesson-section', false);
                        toggleSection('question-section', false);
                    }
                } else {
                    alert(response.data || olamaCurriculum.i18n.errorDeletingUnit);
                }
            },
            error: function (xhr, status, error) {
                console.error('Delete Unit Error:', error);
                alert(olamaCurriculum.i18n.errorDeletingUnit + ': ' + error);
            }
        });
    });

    // --- Lesson Management ---
    function loadLessons() {
        if (!currentUnit) return;

        // Use timeout to prevent multiple calls
        if (window.loadLessonsTimeout) clearTimeout(window.loadLessonsTimeout);

        window.loadLessonsTimeout = setTimeout(function () {
            showLoading('#lessons-list');
            console.log('Load Lessons Request:', {
                action: 'olama_get_curriculum_lessons',
                unit_id: currentUnit
            });

            $.ajax({
                url: olamaCurriculum.ajaxUrl,
                data: {
                    action: 'olama_get_curriculum_lessons',
                    unit_id: currentUnit,
                    nonce: olamaCurriculum.nonce
                },
                success: function (response) {
                    console.log('Load Lessons Response:', response);
                    if (response.success) {
                        const lessons = response.data.lessons || response.data || [];
                        renderLessons(lessons);
                    } else {
                        $('#lessons-list').html('<p>' + olamaCurriculum.i18n.errorLoadingLessons + '</p>');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Load Lessons Error:', error);
                    $('#lessons-list').html('<p>' + olamaCurriculum.i18n.errorConnection + '</p>');
                }
            });
        }, 100);
    }

    function renderLessons(lessons) {
        let html = '';
        if (!lessons || lessons.length === 0) {
            html = '<p>' + olamaCurriculum.i18n.noLessons + '</p>';
        } else {
            lessons.forEach(function (lesson) {
                // Fallback to lesson_name if lesson_title is undefined (handling legacy/mismatch)
                const title = lesson.lesson_title || lesson.lesson_name || olamaCurriculum.i18n.noTitle;
                html += `
                <div class="olama-item lesson-item" data-id="${lesson.id}" data-title="${title}" data-number="${lesson.lesson_number || ''}" data-url="${lesson.video_url || ''}" data-periods="${lesson.periods || 1}">
                    <div class="item-info">
                        <strong>${lesson.lesson_number ? lesson.lesson_number + ' - ' : ''}${title}</strong>
                        <span class="lesson-count-badge">${olamaCurriculum.i18n.periodsLabel.replace('%d', lesson.periods || 1)}</span>
                    </div>
                    <div class="item-actions">
                        <button class="button edit-lesson" data-id="${lesson.id}">${olamaCurriculum.i18n.edit}</button>
                        <button class="button delete-lesson" data-id="${lesson.id}">${olamaCurriculum.i18n.delete}</button>
                    </div>
                </div>`;
            });
        }
        $('#lessons-list').html(html);
    }

    $(document).on('click', '.lesson-item', function (e) {
        if ($(e.target).closest('.item-actions').length) return;

        $('.lesson-item').removeClass('active');
        $(this).addClass('active');

        currentLesson = $(this).data('id');
        toggleSection('question-section', true);
        loadQuestions();
    });

    $('#add-lesson-btn').on('click', function () {
        console.log('Add Lesson button clicked!');
        console.log('Button disabled state:', $(this).prop('disabled'));
        $('#lesson-id').val('');
        $('#lesson-number').val('');
        $('#lesson-title').val('');
        $('#lesson-url').val('');
        $('#lesson-periods').val('1');
        $('#lesson-form-container').show();
        // Explicitly enable form buttons and inputs
        $('#lesson-form-container').find('button, input, select, textarea').prop('disabled', false);
        console.log('Form shown, buttons enabled');
        $(this).hide();
    });

    $('.cancel-lesson-btn').on('click', function () {
        $('#lesson-form-container').hide();
        $('#add-lesson-btn').show();
    });

    $('.save-lesson-btn').on('click', function () {
        console.log('Save Lesson button clicked!');
        console.log('Button disabled state:', $(this).prop('disabled'));

        const data = {
            action: 'olama_save_curriculum_lesson',
            id: $('#lesson-id').val(),
            lesson_number: $('#lesson-number').val(),
            lesson_title: $('#lesson-title').val(),
            video_url: $('#lesson-url').val() || '',
            periods: $('#lesson-periods').val() || 1,
            unit_id: currentUnit,
            nonce: olamaCurriculum.nonce
        };

        console.log('Save Lesson Data:', data);

        if (!data.lesson_number) return alert(olamaCurriculum.i18n.lessonNumberRequired);
        if (!data.lesson_title) return alert(olamaCurriculum.i18n.lessonTitleRequired);
        if (!currentUnit) return alert(olamaCurriculum.i18n.noUnitSelected);

        // Client-side duplicate check
        let isDuplicate = false;
        $('.lesson-item').each(function () {
            if ($(this).data('id') != data.id && $(this).data('number') == data.lesson_number) {
                isDuplicate = true;
                return false;
            }
        });
        if (isDuplicate) return alert(olamaCurriculum.i18n.lessonExists.replace('#', '#' + data.lesson_number));

        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: data,
            success: function (response) {
                console.log('Save Lesson Response:', response);
                if (response.success) {
                    $('#lesson-form-container').hide();
                    $('#add-lesson-btn').show();
                    loadLessons();
                } else {
                    alert('Error saving lesson: ' + JSON.stringify(response.data));
                }
            },
            error: function (xhr, status, error) {
                console.error('Save Lesson Error:', error);
                alert('AJAX Error: ' + error);
            }
        });
    });

    $(document).on('click', '.edit-lesson', function () {
        const id = $(this).data('id');
        const item = $(this).closest('.lesson-item');
        $('#lesson-id').val(id);
        $('#lesson-title').val(item.data('title'));
        $('#lesson-number').val(item.data('number'));
        $('#lesson-url').val(item.data('url'));
        $('#lesson-periods').val(item.data('periods') || 1);
        $('#lesson-form-container').show();
        $('#add-lesson-btn').hide();
    });

    $(document).on('click', '.delete-lesson', function () {
        if (!confirm(olamaCurriculum.i18n.confirmDelete)) return;
        const id = $(this).data('id');
        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_delete_curriculum_lesson',
                id: id,
                nonce: olamaCurriculum.nonce
            },
            success: function (response) {
                if (response.success) {
                    loadLessons();
                    if (currentLesson == id) {
                        currentLesson = null;
                        toggleSection('question-section', false);
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Delete Lesson Error:', error);
            }
        });
    });

    // --- Question Management ---
    function loadQuestions() {
        showLoading('#questions-list');
        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            data: {
                action: 'olama_get_curriculum_questions',
                lesson_id: currentLesson,
                nonce: olamaCurriculum.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderQuestions(response.data);
                }
            }
        });
    }

    function renderQuestions(questions) {
        let html = '';
        if (questions.length === 0) {
            html = '<p>' + olamaCurriculum.i18n.noQuestions + '</p>';
        } else {
            questions.forEach(function (q) {
                html += `
                <div class="olama-item question-item" data-id="${q.id}" data-text="${q.question_text}" data-answer="${q.answer_text}" data-number="${q.question_number || ''}">
                    <div class="item-info">
                        <strong>${q.question_number ? q.question_number + ' - ' : ''}${q.question_text}</strong><br>
                        <small>A: ${q.answer_text}</small>
                    </div>
                    <div class="item-actions">
                        <button class="button edit-question" data-id="${q.id}">${olamaCurriculum.i18n.edit}</button>
                        <button class="button delete-question" data-id="${q.id}">${olamaCurriculum.i18n.delete}</button>
                    </div>
                </div>`;
            });
        }
        $('#questions-list').html(html);
    }

    $('#add-question-btn').on('click', function () {
        $('#question-id').val('');
        $('#question-number').val('');
        $('#question-text').val('');
        $('#question-answer').val('');
        $('#question-form-container').show();
        $(this).hide();
    });

    $(document).on('click', '.cancel-question-btn', function () {
        $('#question-form-container').hide();
        $('#add-question-btn').show();
    });

    // Bind specific save button
    $(document).on('click', '.save-question-btn', function () {
        const data = {
            action: 'olama_save_curriculum_question',
            id: $('#question-id').val(),
            question_number: $('#question-number').val(),
            question_text: $('#question-text').val(),
            answer_text: $('#question-answer').val(),
            lesson_id: currentLesson,
            nonce: olamaCurriculum.nonce
        };

        if (!data.question_number) return alert(olamaCurriculum.i18n.questionNumberRequired);
        if (!data.question_text) return alert(olamaCurriculum.i18n.questionTextRequired);

        // Client-side duplicate check
        let isDuplicate = false;
        $('.question-item').each(function () {
            if ($(this).data('id') != data.id && $(this).data('number') == data.question_number) {
                isDuplicate = true;
                return false;
            }
        });
        if (isDuplicate) return alert(olamaCurriculum.i18n.questionExists.replace('#', '#' + data.question_number));

        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    $('#question-form-container').hide();
                    $('#add-question-btn').show();
                    loadQuestions();
                }
            }
        });
    });

    $(document).on('click', '.edit-question', function () {
        const item = $(this).closest('.question-item');
        const id = item.data('id');
        const text = item.data('text');
        const answer = item.data('answer');
        const number = item.data('number');

        $('#question-id').val(id);
        $('#question-number').val(number);
        $('#question-text').val(text);
        $('#question-answer').val(answer);
        $('#question-form-container').show();
        $('#add-question-btn').hide();
    });

    $(document).on('click', '.delete-question', function () {
        if (!confirm(olamaCurriculum.i18n.confirmDelete)) return;
        const id = $(this).data('id');
        $.ajax({
            url: olamaCurriculum.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_delete_curriculum_question',
                id: id,
                nonce: olamaCurriculum.nonce
            },
            success: function (response) {
                if (response.success) {
                    loadQuestions();
                }
            }
        });
    });

    // Initialize UI
    toggleSection('lesson-section', false);
    toggleSection('question-section', false);
    updateImportExportStatus();

});
