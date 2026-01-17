jQuery(document).ready(function ($) {
    const $semesterSelect = $('#timeline-semester');
    const $gradeSelect = $('#timeline-grade');
    const $subjectSelect = $('#timeline-subject');
    const $loadBtn = $('#load-timeline-btn');
    const $saveBtn = $('#save-timeline-btn');
    const $content = $('#timeline-content');
    const $grid = $('#timeline-grid-container');
    const $title = $('#timeline-title');

    let semesterStart = '';
    let semesterEnd = '';

    // Handle Grade Selection
    $gradeSelect.on('change', function () {
        const gradeId = $(this).val();
        $subjectSelect.prop('disabled', true).html($(document.createElement('option')).val('').text(olamaTimeline.i18n.loading));
        $loadBtn.prop('disabled', true);

        if (!gradeId) {
            $subjectSelect.html($(document.createElement('option')).val('').text(olamaTimeline.i18n.selectSubject));
            return;
        }

        $.ajax({
            url: olamaTimeline.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_get_subjects_by_grade',
                grade_id: gradeId,
                nonce: olamaTimeline.curriculumNonce
            },
            success: function (response) {
                if (response.success) {
                    let options = `<option value="">${olamaTimeline.i18n.selectSubject}</option>`;
                    response.data.forEach(function (subject) {
                        options += `<option value="${subject.id}">${subject.subject_name}</option>`;
                    });
                    $subjectSelect.html(options).prop('disabled', false);
                } else {
                    console.error('Timeline Get Subjects Error:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('Timeline Get Subjects Connection Error:', error);
                console.error('Response:', xhr.responseText);
            }
        });
    });

    // Handle Semester Selection to get bounds
    $semesterSelect.on('change', function () {
        const $option = $(this).find('option:selected');
        semesterStart = $option.data('start');
        semesterEnd = $option.data('end');
        validateLoadButton();
    });

    $subjectSelect.on('change', validateLoadButton);

    function validateLoadButton() {
        if ($semesterSelect.val() && $gradeSelect.val() && $subjectSelect.val()) {
            $loadBtn.prop('disabled', false);
        } else {
            $loadBtn.prop('disabled', true);
        }
    }

    // Load Timeline Data
    $loadBtn.on('click', function () {
        const semesterId = $semesterSelect.val();
        const gradeId = $gradeSelect.val();
        const subjectId = $subjectSelect.val();
        const subjectName = $subjectSelect.find('option:selected').text();

        $loadBtn.prop('disabled', true).text(olamaTimeline.i18n.loading);

        $.ajax({
            url: olamaTimeline.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_get_timeline_data',
                semester_id: semesterId,
                grade_id: gradeId,
                subject_id: subjectId,
                nonce: olamaTimeline.nonce
            },
            success: function (response) {
                $loadBtn.prop('disabled', false).text(olamaTimeline.i18n.loadTimeline);
                if (response.success) {
                    renderTimeline(response.data);
                    $title.text(subjectName);
                    $content.show();
                } else {
                    console.error('Load Timeline Server Error:', response.data);
                    alert(olamaTimeline.i18n.error + '\nServer said: ' + (typeof response.data === 'string' ? response.data : JSON.stringify(response.data)));
                }
            },
            error: function (xhr, status, error) {
                $loadBtn.prop('disabled', false).text(olamaTimeline.i18n.loadTimeline);
                console.error('Load Timeline Connection Error:', error);
                console.error('Response:', xhr.responseText);
                alert(olamaTimeline.i18n.error + '\nStatus: ' + status + ' (' + error + ')\nStatus Code: ' + xhr.status);
            }
        });
    });

    function renderTimeline(data) {
        let html = '';
        if (data.length === 0) {
            html = `<p style="padding: 20px; text-align: center;">${olamaTimeline.i18n.noUnitsFound}</p>`;
        } else {
            data.forEach(function (unit) {
                html += `
                    <div class="timeline-unit-row" data-id="${unit.id}">
                        <div class="timeline-unit-header">
                            <h3 class="timeline-unit-title">${olamaTimeline.i18n.unit} ${unit.unit_number}: ${unit.unit_name}</h3>
                            <div class="timeline-unit-dates">
                                <div class="date-group">
                                    <label>${olamaTimeline.i18n.unitStart}</label>
                                    <input type="text" class="timeline-date-input unit-start olama-datepicker" value="${unit.formatted_start_date || ''}" data-raw="${unit.start_date || ''}" autocomplete="off">
                                </div>
                                <div class="date-group">
                                    <label>${olamaTimeline.i18n.unitEnd}</label>
                                    <input type="text" class="timeline-date-input unit-end olama-datepicker" value="${unit.formatted_end_date || ''}" data-raw="${unit.end_date || ''}" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="timeline-lessons">
                            <table class="timeline-lesson-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>${olamaTimeline.i18n.lessonTitle}</th>
                                        <th style="width: 100px;">${olamaTimeline.i18n.periods}</th>
                                        <th style="width: 180px;">${olamaTimeline.i18n.startDate}</th>
                                        <th style="width: 180px;">${olamaTimeline.i18n.endDate}</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                unit.lessons.forEach(function (lesson) {
                    html += `
                        <tr class="timeline-lesson-row" data-id="${lesson.id}">
                            <td class="lesson-number">${lesson.lesson_number}</td>
                            <td class="lesson-title">${lesson.lesson_title}</td>
                            <td>
                                <input type="number" class="timeline-periods-input lesson-periods" value="${lesson.periods || 1}" min="1" style="width: 60px;">
                            </td>
                            <td>
                                <input type="text" class="timeline-date-input lesson-start olama-datepicker" value="${lesson.formatted_start_date || ''}" data-raw="${lesson.start_date || ''}" autocomplete="off">
                                <div class="date-error"></div>
                            </td>
                            <td>
                                <input type="text" class="timeline-date-input lesson-end olama-datepicker" value="${lesson.formatted_end_date || ''}" data-raw="${lesson.end_date || ''}" autocomplete="off">
                                <div class="date-error"></div>
                            </td>
                        </tr>`;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>`;
            });
        }
        $grid.html(html);

        // Initialize datepickers for the new elements
        $('.olama-datepicker').datepicker({
            dateFormat: olamaAdmin.dateFormat,
            isRTL: olamaAdmin.isArabic,
            onSelect: function (dateText, inst) {
                const day = inst.selectedDay.toString().padStart(2, '0');
                const month = (inst.selectedMonth + 1).toString().padStart(2, '0');
                const year = inst.selectedYear;
                $(this).attr('data-raw', `${year}-${month}-${day}`).trigger('change');
            }
        });
    }

    // Validation logic
    $(document).on('change', '.timeline-date-input', function () {
        validateAll();
    });

    function validateAll() {
        // Clear all previous errors
        $('.timeline-date-input').removeClass('input-error');
        $('.date-error').hide().text('');

        const unitDates = [];

        $('.timeline-unit-row').each(function () {
            const $unitRow = $(this);
            const unitStart = $unitRow.find('.unit-start').attr('data-raw');
            const unitEnd = $unitRow.find('.unit-end').attr('data-raw');

            if (unitStart && unitEnd) {
                unitDates.push({
                    start: unitStart,
                    end: unitEnd,
                    el: $unitRow
                });

                // Unit start vs end
                if (unitStart > unitEnd) {
                    showError($unitRow.find('.unit-start'), olamaTimeline.i18n.dateInvalid);
                    showError($unitRow.find('.unit-end'), olamaTimeline.i18n.dateInvalid);
                }

                // Semester range
                if (unitStart < semesterStart || unitStart > semesterEnd) {
                    showError($unitRow.find('.unit-start'), olamaTimeline.i18n.outsideSemester);
                }
                if (unitEnd < semesterStart || unitEnd > semesterEnd) {
                    showError($unitRow.find('.unit-end'), olamaTimeline.i18n.outsideSemester);
                }
            }

            // Lessons within units
            $unitRow.find('.timeline-lesson-row').each(function () {
                const $lessonRow = $(this);
                const lessonStart = $lessonRow.find('.lesson-start').attr('data-raw');
                const lessonEnd = $lessonRow.find('.lesson-end').attr('data-raw');

                if (lessonStart) {
                    if (unitStart && lessonStart < unitStart) {
                        showError($lessonRow.find('.lesson-start'), olamaTimeline.i18n.lessonOutsideUnit);
                    }
                    if (unitEnd && lessonStart > unitEnd) {
                        showError($lessonRow.find('.lesson-start'), olamaTimeline.i18n.lessonOutsideUnit);
                    }
                    if (lessonStart < semesterStart || lessonStart > semesterEnd) {
                        showError($lessonRow.find('.lesson-start'), olamaTimeline.i18n.outsideSemester);
                    }
                }

                if (lessonEnd) {
                    if (unitStart && lessonEnd < unitStart) {
                        showError($lessonRow.find('.lesson-end'), olamaTimeline.i18n.lessonOutsideUnit);
                    }
                    if (unitEnd && lessonEnd > unitEnd) {
                        showError($lessonRow.find('.lesson-end'), olamaTimeline.i18n.lessonOutsideUnit);
                    }
                    if (lessonEnd < semesterStart || lessonEnd > semesterEnd) {
                        showError($lessonRow.find('.lesson-end'), olamaTimeline.i18n.outsideSemester);
                    }
                }

                if (lessonStart && lessonEnd && lessonStart > lessonEnd) {
                    showError($lessonRow.find('.lesson-start'), olamaTimeline.i18n.dateInvalid);
                    showError($lessonRow.find('.lesson-end'), olamaTimeline.i18n.dateInvalid);
                }
            });
        });

        // Unit overlap check
        unitDates.sort((a, b) => a.start.localeCompare(b.start));
        for (let i = 0; i < unitDates.length - 1; i++) {
            if (unitDates[i].end >= unitDates[i + 1].start) {
                showError(unitDates[i].el.find('.unit-end'), olamaTimeline.i18n.unitsOverlap);
                showError(unitDates[i + 1].el.find('.unit-start'), olamaTimeline.i18n.unitsOverlap);
            }
        }
    }

    function showError($el, msg) {
        $el.addClass('input-error');
        $el.siblings('.date-error').text(msg).show();
    }

    // Save Timeline
    $saveBtn.on('click', function () {
        if ($('.input-error:visible').length > 0) {
            alert(olamaTimeline.i18n.fixErrors);
            return;
        }

        const data = [];
        $('.timeline-unit-row').each(function () {
            const $unitRow = $(this);
            const unit = {
                id: $unitRow.data('id'),
                start_date: $unitRow.find('.unit-start').attr('data-raw'),
                end_date: $unitRow.find('.unit-end').attr('data-raw'),
                lessons: []
            };

            $unitRow.find('.timeline-lesson-row').each(function () {
                const $lessonRow = $(this);
                unit.lessons.push({
                    id: $lessonRow.data('id'),
                    start_date: $lessonRow.find('.lesson-start').attr('data-raw'),
                    end_date: $lessonRow.find('.lesson-end').attr('data-raw'),
                    periods: $lessonRow.find('.lesson-periods').val()
                });
            });

            data.push(unit);
        });

        $saveBtn.prop('disabled', true).addClass('saving').text(olamaTimeline.i18n.saving);

        $.ajax({
            url: olamaTimeline.ajaxUrl,
            method: 'POST',
            data: {
                action: 'olama_save_timeline_dates',
                timeline_data: JSON.stringify(data),
                nonce: olamaTimeline.nonce
            },
            success: function (response) {
                $saveBtn.prop('disabled', false).removeClass('saving').text(olamaTimeline.i18n.saveAllDates);
                if (response.success) {
                    alert(response.data);
                } else {
                    console.error('Save Timeline Server Error:', response.data);
                    alert(response.data || olamaTimeline.i18n.error);
                }
            },
            error: function (xhr, status, error) {
                $saveBtn.prop('disabled', false).removeClass('saving').text(olamaTimeline.i18n.saveAllDates);
                console.error('Save Timeline Connection Error:', error);
                console.error('Response text:', xhr.responseText);
                alert(olamaTimeline.i18n.error + '\nStatus: ' + status + ' (' + error + ')\nStatus Code: ' + xhr.status);
            }
        });
    });

    // Clear All Dates
    $(document).on('click', '#clear-timeline-btn', function () {
        if (!confirm(olamaTimeline.i18n.confirmClear)) return;

        $('.timeline-date-input').val('');
        // Also clear previous errors
        $('.timeline-date-input').removeClass('input-error');
        $('.date-error').hide().text('');

        // Trigger validation to clear highlights
        validateAll();
    });
});
