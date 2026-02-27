/* Admin JS for Olama School Weekly Plan System */
jQuery(document).ready(function ($) {
    console.log('Olama School Weekly Plan Admin Loaded - v1.0.1');

    // Initialize datepickers with localized format
    if (typeof olamaAdmin !== 'undefined' && $.fn.datepicker) {
        $('.olama-datepicker').datepicker({
            dateFormat: olamaAdmin.dateFormat,
            changeMonth: true,
            changeYear: true,
            firstDay: 0, // Sunday
            isRTL: olamaAdmin.isArabic,
            onSelect: function (dateText, inst) {
                const day = inst.selectedDay.toString().padStart(2, '0');
                const month = (inst.selectedMonth + 1).toString().padStart(2, '0');
                const year = inst.selectedYear;
                $(this).attr('data-raw', `${year}-${month}-${day}`).trigger('change');
            }
        });
    }

    // --- Evaluation Progress Revamped Logic ---
    $(document).on('click', '.list-students-btn', function () {
        console.log('List Student button clicked');
        const $btn = $(this);
        const templateId = $btn.data('template-id');
        const sectionId = $btn.data('section-id');
        console.log('Template ID:', templateId, 'Section ID:', sectionId);

        const $detailsSection = $('#ev-details-section');
        const $studentsList = $('#ev-students-list');
        const $evaluationContent = $('#ev-evaluation-content');

        if (!$detailsSection.length) {
            console.error('Details section not found!');
        }

        // Show section and scroll to it
        $detailsSection.show();
        $('html, body').animate({
            scrollTop: $detailsSection.offset().top - 50
        }, 500);

        // Load students
        $studentsList.html('<div style="text-align:center; padding:20px;"><span class="dashicons dashicons-update spin"></span></div>');
        $evaluationContent.html('<div style="text-align:center; color:#94a3b8; padding-top:100px;"><span class="dashicons dashicons-id" style="font-size:48px; width:48px; height:48px; margin-bottom:15px;"></span><p>Select a student to view evaluation details.</p></div>');

        console.log('Sending AJAX request to olama_get_ev_progress_students');
        $.get(ajaxurl, {
            action: 'olama_get_ev_progress_students',
            nonce: olamaAdmin.adminNonce,
            template_id: templateId,
            section_id: sectionId
        }, function (response) {
            console.log('AJAX response received');
            $studentsList.html(response);
        }).fail(function (xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            $studentsList.html('<p style="color:red; text-align:center; padding:20px;">Failed to load students.</p>');
        });
    });

    // Handle student selection
    $(document).on('click', '.ev-student-item', function (e) {
        // Don't trigger if clicking the approve button itself
        if ($(e.target).closest('.ev-approve-btn').length) return;

        const $item = $(this);
        const studentId = $item.data('student-id');
        const templateId = $item.data('template-id');
        const $evaluationContent = $('#ev-evaluation-content');

        $('.ev-student-item').removeClass('active');
        $item.addClass('active');

        $evaluationContent.html('<div style="text-align:center; padding:100px;"><span class="dashicons dashicons-update spin"></span></div>');

        $.get(ajaxurl, {
            action: 'olama_get_student_evaluation',
            nonce: olamaAdmin.adminNonce,
            student_id: studentId,
            template_id: templateId
        }, function (response) {
            $evaluationContent.html(response);
        }).fail(function () {
            $evaluationContent.html('<p style="color:red; text-align:center; padding:50px;">Failed to load evaluation details.</p>');
        });
    });

    // Handle evaluation approval
    $(document).on('click', '.ev-approve-btn', function (e) {
        e.stopPropagation();
        if (!confirm('Are you sure you want to approve this evaluation?')) return;

        const $btn = $(this);
        const studentId = $btn.data('student-id');
        const templateId = $btn.data('template-id');
        const $item = $btn.closest('.ev-student-item');

        $btn.prop('disabled', true).text('...');

        $.post(ajaxurl, {
            action: 'olama_approve_evaluation',
            nonce: olamaAdmin.adminNonce,
            student_id: studentId,
            template_id: templateId
        }, function (response) {
            if (response.success) {
                // Update badge in the list
                const $listItem = $(`.ev-student-item[data-student-id="${studentId}"][data-template-id="${templateId}"]`);
                if ($listItem.length) {
                    $listItem.find('.ev-status-badge').removeClass('status-draft status-none').addClass('status-published').text('Published');
                    $listItem.find('.ev-approve-btn').remove();
                }

                // Update badge and remove button in the details view if currently displayed
                const $detailsView = $('.ev-review-wrapper');
                if ($detailsView.length) {
                    $detailsView.find('.ev-status-badge').removeClass('status-draft status-none').addClass('status-published').text('Published');
                    $('.ev-evaluation-col .ev-approve-btn').remove();
                }
            } else {
                alert('Failed to approve: ' + (response.data || 'Unknown error'));
                $btn.prop('disabled', false).text('Approve');
            }
        }).fail(function () {
            alert('Server error while approving.');
            $btn.prop('disabled', false).text('Approve');
        });
    });
});
