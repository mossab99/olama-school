jQuery(document).ready(function ($) {
    const $container = $('.olama-assignment-wrap');
    if (!$container.length) return;

    let selectedTeacherId = null;
    let selectedGradeId = null;
    let selectedSectionId = null;
    let teacherAssignments = [];

    const $teacherItems = $('.teacher-item');
    const $gradeCol = $('#grade-col');
    const $sectionCol = $('#section-col');
    const $subjectCol = $('#subject-col');

    const $sectionsList = $('#sections-list');
    const $subjectsList = $('#subjects-list');

    $('#teacher-assignment-search').on('input', function () {
        const term = $(this).val().toLowerCase().trim();
        $teacherItems.each(function () {
            $(this).toggle(!term || String($(this).data('search')).includes(term));
        });
    });

    function refreshSummary() {
        if (!selectedTeacherId) return;
        $.post(olamaAssignment.ajaxUrl, {
            action: 'olama_get_teacher_summary',
            nonce: olamaAssignment.curriculumNonce,
            teacher_id: selectedTeacherId
        }, function (response) {
            if (response.success) {
                teacherAssignments = response.data;
                updateHighlights();
            }
        });
    }

    function updateHighlights() {
        $('.grade-item, .section-item').each(function () {
            const $item = $(this);
            const id = $item.data('id');
            const isGrade = $item.hasClass('grade-item');

            const count = teacherAssignments.filter(a => {
                return isGrade ? parseInt(a.grade_id) === parseInt(id) : parseInt(a.section_id) === parseInt(id);
            }).length;

            $item.find('.assignment-count').remove();
            if (count > 0) {
                $item.append(`<span class="assignment-count">${count}</span>`);
            }
        });
    }

    // Helper to get hint HTML
    function getHintHtml(text) {
        return `
            <div class="select-hint">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <p>${text}</p>
            </div>`;
    }

    // 1. Select Teacher
    $teacherItems.on('click', function () {
        const $this = $(this);
        $teacherItems.removeClass('active');
        $this.addClass('active');

        selectedTeacherId = $this.data('id');
        selectedGradeId = null;
        selectedSectionId = null;

        refreshSummary();

        // Reset columns
        $gradeCol.removeClass('disabled-col');
        $('.grade-item').removeClass('active');

        $sectionsList.html(getHintHtml(olamaAssignment.i18n.selectGrade));
        $sectionCol.addClass('disabled-col');

        $subjectsList.html(getHintHtml(olamaAssignment.i18n.selectSection));
        $subjectCol.addClass('disabled-col');
    });

    // 2. Select Grade
    $(document).on('click', '.grade-item', function () {
        const $this = $(this);
        $('.grade-item').removeClass('active');
        $this.addClass('active');

        selectedGradeId = $this.data('id');
        selectedSectionId = null;

        // Reset section/subject columns
        $sectionsList.html('<div class="select-hint"><p>' + olamaAssignment.i18n.loading + '</p></div>');
        $sectionCol.removeClass('disabled-col');

        $subjectsList.html(getHintHtml(olamaAssignment.i18n.selectSection));
        $subjectCol.addClass('disabled-col');

        // Fetch Sections
        $.post(olamaAssignment.ajaxUrl, {
            action: 'olama_get_sections_by_grade',
            nonce: olamaAssignment.curriculumNonce,
            grade_id: selectedGradeId
        }, function (response) {
            if (response.success && response.data.length) {
                let html = '';
                response.data.forEach(section => {
                    html += `
                        <div class="assignment-item section-item" data-id="${section.id}">
                            <span class="item-title">${section.section_name}</span>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>`;
                });
                $sectionsList.html(html);
                updateHighlights();
            } else {
                $sectionsList.html('<div class="select-hint"><p>No sections found.</p></div>');
            }
        });
    });

    // 3. Select Section
    $(document).on('click', '.section-item', function () {
        const $this = $(this);
        $('.section-item').removeClass('active');
        $this.addClass('active');

        selectedSectionId = $this.data('id');

        // Load Subjects and Assignments
        $subjectsList.html('<div class="select-hint"><p>' + olamaAssignment.i18n.loading + '</p></div>');
        $subjectCol.removeClass('disabled-col');

        $.post(olamaAssignment.ajaxUrl, {
            action: 'olama_get_teacher_assignments',
            nonce: olamaAssignment.curriculumNonce,
            teacher_id: selectedTeacherId,
            section_id: selectedSectionId,
            grade_id: selectedGradeId
        }, function (response) {
            if (response.success && response.data.all.length) {
                const assigned = response.data.assigned.map(id => parseInt(id));
                let html = '';
                response.data.all.forEach(subject => {
                    const isChecked = assigned.includes(parseInt(subject.id)) ? 'checked' : '';
                    html += `
                        <div class="subject-item">
                            <input type="checkbox" class="assignment-checkbox" 
                                data-subject-id="${subject.id}" ${isChecked}>
                            <span class="item-title">${subject.subject_name} <small>(${subject.oracle_subject_id || subject.subject_code || ''})</small></span>
                        </div>`;
                });
                $subjectsList.html(html);
            } else {
                $subjectsList.html('<div class="select-hint"><p>No subjects found for this grade.</p></div>');
            }
        });
    });

    // 4. Toggle Assignment
    $(document).on('change', '.assignment-checkbox', function () {
        const $this = $(this);
        const subjectId = $this.data('subject-id');
        const $item = $this.closest('.subject-item');

        $item.addClass('loading-item');
        $this.prop('disabled', true);

        $.post(olamaAssignment.ajaxUrl, {
            action: 'olama_toggle_teacher_assignment',
            nonce: olamaAssignment.curriculumNonce,
            teacher_id: selectedTeacherId,
            section_id: selectedSectionId,
            subject_id: subjectId,
            grade_id: selectedGradeId
        }, function (response) {
            $item.removeClass('loading-item');
            $this.prop('disabled', false);

            if (response.success) {
                refreshSummary();
            } else {
                const message = typeof response.data === 'string'
                    ? response.data
                    : (response.data && response.data.message ? response.data.message : olamaAssignment.i18n.error);
                alert(message);
                $this.prop('checked', !$this.prop('checked')); // Revert
            }
        });
    });
});
