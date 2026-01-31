jQuery(document).ready(function ($) {
    const $subjectSelect = $('#olama-subject-select');
    const $unitSelect = $('#olama-unit-select');
    const $lessonSelect = $('#olama-lesson-select');
    const $questionsArea = $('#olama-questions-area');
    const $questionsList = $('#olama-questions-list');
    const $planIdInput = $('#olama-plan-id');
    const $saveButton = $('#olama-save-plan-btn');
    const $submitButton = $('#olama-submit-plan-btn');
    const $cancelButton = $('#olama-cancel-edit-btn');

    // Homework and Notes fields
    const $homeworkSB = $('textarea[name="homework_sb"]');
    const $homeworkEB = $('textarea[name="homework_eb"]');
    const $homeworkNB = $('textarea[name="homework_nb"]');
    const $homeworkWS = $('textarea[name="homework_ws"]');
    const $teacherNotes = $('textarea[name="teacher_notes"]');

    let currentEditingPlan = null;

    // Plan Type Toggle and Homework Section Elements
    const $planTypeInput = $('#olama-plan-type');
    const $homeworkSection = $('.olama-homework-grid');

    // Handle Plan Type Toggle (using event delegation for dynamic elements)
    $(document).on('click', '.olama-plan-type-btn', function () {
        const $btn = $(this);
        const planType = $btn.data('type');
        const $allBtns = $('.olama-plan-type-btn');

        console.log('Plan Type Toggle clicked:', planType);

        // Update active state
        $allBtns.removeClass('active');
        $btn.addClass('active');

        // Update styles
        if (planType === 'homework') {
            $allBtns.filter('[data-type="homework"]').css({
                'background': '#3b82f6',
                'color': '#fff',
                'border-color': '#3b82f6'
            });
            $allBtns.filter('[data-type="review"]').css({
                'background': '#fff',
                'color': '#8b5cf6',
                'border-color': '#8b5cf6'
            });
        } else {
            $allBtns.filter('[data-type="review"]').css({
                'background': '#8b5cf6',
                'color': '#fff',
                'border-color': '#8b5cf6'
            });
            $allBtns.filter('[data-type="homework"]').css({
                'background': '#fff',
                'color': '#3b82f6',
                'border-color': '#3b82f6'
            });
        }

        // Update hidden input
        $('#olama-plan-type').val(planType);

        // Show/hide homework section based on plan type
        if (planType === 'review') {
            $('.olama-homework-grid').slideUp();
            // Clear homework fields for review plans
            $homeworkSB.val('');
            $homeworkEB.val('');
            $homeworkNB.val('');
            $homeworkWS.val('');
        } else {
            $('.olama-homework-grid').slideDown();
        }

        // Update subject filtering when plan type changes
        handleSubjectFiltering();
    });

    function handleSubjectFiltering() {
        const editingSubjectId = currentEditingPlan ? currentEditingPlan.subject_id.toString() : null;
        const currentPlanType = $planTypeInput.val();

        $subjectSelect.find('option').each(function () {
            const $option = $(this);
            const val = $option.val();
            if (!val) return;

            const isFilled = $option.hasClass('olama-filled-subject');
            const hasReview = $option.data('has-review') === true;

            // For homework plans, disable subjects that have review plans on this date
            if (currentPlanType === 'homework' && hasReview) {
                // Allow if we're editing this same subject
                if (editingSubjectId && val === editingSubjectId) {
                    $option.addClass('is-editing').prop('disabled', false);
                } else {
                    $option.prop('disabled', true);
                }
            } else if (isFilled) {
                if (editingSubjectId && val === editingSubjectId) {
                    $option.addClass('is-editing').prop('disabled', false);
                } else {
                    $option.removeClass('is-editing').prop('disabled', true);
                }
            } else {
                $option.prop('disabled', false);
            }
        });
    }

    // Trigger on load
    handleSubjectFiltering();

    // Handle Subject selection -> Load Units
    $subjectSelect.on('change', function () {
        const subjectId = $(this).val();
        const gradeId = $('select[name="grade_id"]').val();
        const semesterId = olamaPlan.semesterId;

        // Reset descendants
        $unitSelect.val('').prop('disabled', true).html('<option value="">' + (olamaPlan.i18n.selectUnit || 'Select Unit') + '</option>');
        $lessonSelect.val('').prop('disabled', true).html('<option value="">' + (olamaPlan.i18n.selectLesson || 'Select Lesson') + '</option>');
        $questionsArea.hide();
        $questionsList.empty();

        if (!subjectId || !gradeId) return;

        $unitSelect.prop('disabled', true).html('<option value="">' + (olamaPlan.i18n.loading || 'Loading...') + '</option>');

        $.ajax({
            url: olamaPlan.ajaxUrl,
            type: 'GET',
            data: {
                action: 'olama_get_curriculum_units',
                subject_id: subjectId,
                grade_id: gradeId,
                semester_id: semesterId,
                nonce: olamaPlan.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">' + (olamaPlan.i18n.selectUnit || 'Select Unit') + '</option>';
                    response.data.forEach(function (unit) {
                        options += `<option value="${unit.id}">${unit.unit_number}: ${unit.unit_name}</option>`;
                    });
                    $unitSelect.html(options).prop('disabled', false);

                    // If editing, auto-select unit
                    if (currentEditingPlan && currentEditingPlan.unit_id) {
                        $unitSelect.val(currentEditingPlan.unit_id).trigger('change');
                    }
                } else {
                    $unitSelect.html('<option value="">' + (olamaPlan.i18n.noUnits || 'No units found') + '</option>');
                }
            },
            error: function () {
                $unitSelect.html('<option value="">' + (olamaPlan.i18n.errorLoadingUnits || 'Error loading units') + '</option>');
            }
        });
    });

    // Handle Unit selection -> Load Lessons
    $unitSelect.on('change', function () {
        const unitId = $(this).val();

        // Reset descendants
        $lessonSelect.val('').prop('disabled', true).html('<option value="">' + (olamaPlan.i18n.selectLesson || 'Select Lesson') + '</option>');
        $questionsArea.hide();
        $questionsList.empty();

        if (!unitId) return;

        $lessonSelect.prop('disabled', true).html('<option value="">' + (olamaPlan.i18n.loading || 'Loading...') + '</option>');

        $.ajax({
            url: olamaPlan.ajaxUrl,
            type: 'GET',
            data: {
                action: 'olama_get_curriculum_lessons',
                unit_id: unitId,
                nonce: olamaPlan.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    let options = '<option value="">' + (olamaPlan.i18n.selectLesson || 'Select Lesson') + '</option>';
                    response.data.forEach(function (lesson) {
                        options += `<option value="${lesson.id}" data-start="${lesson.start_date || ''}" data-end="${lesson.end_date || ''}">${lesson.lesson_number}: ${lesson.lesson_title}</option>`;
                    });
                    $lessonSelect.html(options).prop('disabled', false);

                    // If editing, auto-select lesson
                    if (currentEditingPlan && currentEditingPlan.lesson_id) {
                        $lessonSelect.val(currentEditingPlan.lesson_id).trigger('change');
                    }
                } else {
                    $lessonSelect.html('<option value="">' + (olamaPlan.i18n.noLessons || 'No lessons found') + '</option>');
                }
            },
            error: function () {
                $lessonSelect.html('<option value="">' + (olamaPlan.i18n.errorLoadingLessons || 'Error loading lessons') + '</option>');
            }
        });
    });

    // Calculate Progress Status
    function calculateStatus(planDate, start, end) {
        if (!start || !end) return null;
        const p = new Date(planDate).getTime();
        const s = new Date(start).getTime();
        const e = new Date(end).getTime();

        if (p >= s && p <= e) {
            return { label: olamaPlan.i18n.onTime || 'On-time', class: 'status-ontime' };
        } else if (p > e) {
            const diff = Math.ceil((p - e) / (1000 * 60 * 60 * 24));
            return { label: (olamaPlan.i18n.delayedBy || 'Delayed by %d days').replace('%d', diff), class: 'status-delayed' };
        } else {
            const diff = Math.ceil((s - p) / (1000 * 60 * 60 * 24));
            return { label: (olamaPlan.i18n.bypassBy || 'Bypass by %d days').replace('%d', diff), class: 'status-bypass' };
        }
    }

    // Handle Lesson selection -> Load Questions & Progress Check
    $lessonSelect.on('change', function () {
        const lessonId = $(this).val();
        const $selectedOption = $(this).find('option:selected');
        const startDate = $selectedOption.data('start');
        const endDate = $selectedOption.data('end');
        const planDate = $('input[name="plan_date"]').val();
        const $progressCheck = $('#olama-lesson-progress-check');

        $questionsArea.hide();
        $questionsList.empty();
        $progressCheck.hide().removeClass('status-ontime status-delayed status-bypass').text('');

        if (!lessonId) return;

        // Show Progress Status
        const status = calculateStatus(planDate, startDate, endDate);
        if (status) {
            $progressCheck.addClass(status.class).text(status.label).fadeIn();
            $progressCheck.css({
                'display': 'inline-block',
                'padding': '4px 12px',
                'border-radius': '20px',
                'color': '#fff',
                'font-weight': '600',
                'font-size': '0.85rem'
            });
        }

        $questionsList.html('<p>' + (olamaPlan.i18n.loadingQuestions || 'Loading questions...') + '</p>');
        $questionsArea.show();

        $.ajax({
            url: olamaPlan.ajaxUrl,
            type: 'GET',
            data: {
                action: 'olama_get_curriculum_questions',
                lesson_id: lessonId,
                nonce: olamaPlan.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.length > 0) {
                    let html = '<ul style="margin: 0; padding: 0; list-style: none;">';
                    response.data.forEach(function (q) {
                        const isChecked = currentEditingPlan && currentEditingPlan.question_ids && currentEditingPlan.question_ids.includes(q.id.toString()) ? 'checked' : '';
                        html += `
                            <li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                                <label style="display: flex; gap: 10px; align-items: flex-start; cursor: pointer;">
                                    <input type="checkbox" name="question_ids[]" value="${q.id}" style="margin-top: 3px;" ${isChecked}>
                                    <span>${(q.question_number ? q.question_number + ' - ' : '') + q.question}</span>
                                </label>
                            </li>`;
                    });
                    html += '</ul>';
                    $questionsList.html(html);

                    // Cascade finished for this plan
                    if (currentEditingPlan) {
                        currentEditingPlan = null;
                    }
                } else {
                    $questionsList.html('<p>' + (olamaPlan.i18n.noQuestions || 'No questions found') + '</p>');
                    currentEditingPlan = null;
                }
            },
            error: function () {
                $questionsList.html('<p>' + (olamaPlan.i18n.errorLoadingQuestions || 'Error loading questions') + '</p>');
                currentEditingPlan = null;
            }
        });
    });

    // Handle Edit Plan
    $(document).on('click', '.olama-edit-plan', function (e) {
        e.preventDefault();
        const $item = $(this).closest('.olama-plan-item');
        // Use attr for raw data to avoid jQuery cache issues
        const rawPlan = $item.attr('data-plan');
        let planData;
        try {
            planData = JSON.parse(rawPlan);
        } catch (err) {
            console.error('Error parsing plan data:', err, rawPlan);
            planData = $item.data('plan');
        }

        console.log('Editing Plan:', planData);
        if (!planData) return;

        // Populate fields dynamically
        $('#olama-plan-id').val(planData.id);
        $('textarea[name="homework_sb"]').val(planData.homework_sb || '');
        $('textarea[name="homework_eb"]').val(planData.homework_eb || '');
        $('textarea[name="homework_nb"]').val(planData.homework_nb || '');
        $('textarea[name="homework_ws"]').val(planData.homework_ws || '');
        $('textarea[name="teacher_notes"]').val(planData.teacher_notes || '');

        // Show supervisor feedback box if there's feedback
        const $feedbackBox = $('#olama-supervisor-feedback-box');
        const $feedbackContent = $('#olama-supervisor-feedback-content');
        if (planData.supervisor_feedback && planData.supervisor_feedback.trim()) {
            $feedbackContent.text(planData.supervisor_feedback);
            $feedbackBox.fadeIn();
        } else {
            $feedbackBox.hide();
        }

        // Indicate we are in edit mode for cascading logic
        currentEditingPlan = planData;

        // Set status
        $('#olama-plan-status').val(planData.status || 'draft');
        const $statusContainer = $('#olama-edit-status-container');
        const $statusBadge = $('#olama-current-status-badge');
        const $revertCheck = $('#olama-revert-draft-check');

        if (planData.status === 'published') {
            $statusBadge.text(olamaPlan.i18n.published).css({
                'color': '#15803d',
                'background': '#dcfce7',
                'padding': '2px 10px',
                'border-radius': '12px',
                'font-size': '0.75rem',
                'font-weight': '700'
            });
            $revertCheck.prop('checked', false);
            $statusContainer.fadeIn();
        } else {
            $statusContainer.hide();
        }

        // Set period number if available
        if (planData.period_number !== undefined) {
            $('input[name="period_number"]').val(planData.period_number);
        }

        // Set plan type
        const editPlanType = planData.plan_type || 'homework';
        $('#olama-plan-type').val(editPlanType);
        $('.olama-plan-type-btn').filter(`[data-type="${editPlanType}"]`).trigger('click');

        // Start cascading from Subject
        $subjectSelect.val(planData.subject_id);
        handleSubjectFiltering();

        $subjectSelect.trigger('change');

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#olama-weekly-plan-form').offset().top - 50
        }, 500);

        // UI update
        $saveButton.val(olamaPlan.i18n.updatePlan || 'Update Plan');
        $cancelButton.show();
    });

    // Handle Revert to Draft Checkbox
    $('#olama-revert-draft-check').on('change', function () {
        if ($(this).is(':checked')) {
            $('#olama-plan-status').val('draft');
        } else {
            $('#olama-plan-status').val('published');
        }
    });

    // Handle Cancel Edit
    $cancelButton.on('click', function () {
        // Reset form
        $('#olama-weekly-plan-form')[0].reset();
        $planIdInput.val('0');
        $('#olama-plan-status').val('draft');
        $('#olama-edit-status-container').hide();
        $('#olama-supervisor-feedback-box').hide();

        // Reset cascading selects
        $subjectSelect.val('');
        handleSubjectFiltering();
        $subjectSelect.trigger('change');

        // Reset UI
        $saveButton.val(olamaPlan.i18n.saveAsDraft || 'Save as Draft');
        $cancelButton.hide();

        // Reset plan type to homework (default)
        $('.olama-plan-type-btn').filter('[data-type="homework"]').trigger('click');
        currentEditingPlan = null;

        // Scroll back up a bit
        $('html, body').animate({
            scrollTop: $('#olama-weekly-plan-form').offset().top - 50
        }, 500);
    });

    // Handle Submit button
    $submitButton.on('click', function (e) {
        e.preventDefault();
        $('#olama-plan-status').val('submitted');
        $('#olama-weekly-plan-form').submit();
    });

    // Handle Delete Plan
    $(document).on('click', '.olama-delete-plan', function (e) {
        e.preventDefault();
        const $item = $(this).closest('.olama-plan-item');
        const planData = $item.data('plan');
        if (!planData) return;

        if (!confirm(olamaPlan.i18n.confirmDelete || 'Are you sure you want to delete this plan?')) {
            return;
        }

        const $btn = $(this);
        $btn.css('opacity', '0.5').css('pointer-events', 'none');

        $.ajax({
            url: olamaPlan.ajaxUrl,
            type: 'POST',
            data: {
                action: 'olama_delete_plan',
                plan_id: planData.id,
                nonce: olamaPlan.savePlanNonce
            },
            success: function (response) {
                if (response.success) {
                    $item.fadeOut(function () {
                        const deletedPlan = $(this).data('plan');
                        const subjectId = deletedPlan ? deletedPlan.subject_id : null;

                        $(this).remove();

                        if (subjectId) {
                            $subjectSelect.find(`option[value="${subjectId}"]`).removeClass('olama-filled-subject');
                            handleSubjectFiltering();
                        }

                        if ($('.olama-plan-item').length === 0) {
                            $('.olama-plans-today-list').html('<p>' + (olamaPlan.i18n.noPlansToday || 'No plans saved for today yet.') + '</p>');
                        }
                    });
                } else {
                    alert(response.data || 'Failed to delete plan.');
                    $btn.css('opacity', '1').css('pointer-events', 'auto');
                }
            },
            error: function () {
                alert(olamaPlan.i18n.deletePlanError || 'An error occurred while deleting the plan.');
                $btn.css('opacity', '1').css('pointer-events', 'auto');
            }
        });
    });
});
