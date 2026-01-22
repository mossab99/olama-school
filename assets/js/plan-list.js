jQuery(document).ready(function ($) {
    $('.olama-plan-card').on('click', function () {
        const planDataRaw = $(this).attr('data-plan');
        if (!planDataRaw) return;

        let plan;
        try {
            plan = JSON.parse(planDataRaw);
        } catch (e) {
            console.error('Error parsing plan data:', e);
            return;
        }

        const container = $('#olama-plan-details-container');
        const card = $('#olama-plan-details-card');
        const i18n = olamaPlanList.i18n;

        // Calculate Progress Status
        function calculateStatus(planDate, start, end) {
            if (!start || !end) return null;
            const p = new Date(planDate).getTime();
            const s = new Date(start).getTime();
            const e = new Date(end).getTime();

            if (p >= s && p <= e) {
                return { label: i18n.onTime, class: 'status-ontime' };
            } else if (p > e) {
                const diff = Math.ceil((p - e) / (1000 * 60 * 60 * 24));
                return { label: i18n.delayedBy.replace('%d', diff), class: 'status-delayed' };
            } else {
                const diff = Math.ceil((s - p) / (1000 * 60 * 60 * 24));
                return { label: i18n.bypassBy.replace('%d', diff), class: 'status-bypass' };
            }
        }

        const status = calculateStatus(plan.plan_date, plan.lesson_start_date, plan.lesson_end_date);

        let html = `<h2><span class="dashicons dashicons-welcome-learn-more"></span> ${i18n.details}: ${plan.subject_name}</h2>`;

        if (status) {
            html += `<center><div class="olama-detail-status ${status.class}">${status.label}</div></center>`;
        }

        html += `<div class="olama-details-single-column">`;

        // Section 1: General Info
        html += `<div class="olama-detail-group">
            <div class="olama-detail-group-title">
                <span class="dashicons dashicons-info"></span>
                ${i18n.details}
            </div>
            
            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.unit}</span>
                <div class="olama-detail-value">${plan.unit_name || '-'}</div>
            </div>

            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.lesson}</span>
                <div class="olama-detail-value">${plan.lesson_title || '-'}</div>
            </div>`;

        if (plan.custom_topic) {
            html += `<div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.customTopic}</span>
                <div class="olama-detail-value">${plan.custom_topic}</div>
            </div>`;
        }

        // Add Status instead of Rating
        let statusLabel = i18n.draft;
        let statusClass = 'olama-status-draft';

        if (plan.status === 'approved' || plan.status === 'published') {
            statusLabel = i18n.approved;
            statusClass = 'olama-status-published';
        } else if (plan.status === 'submitted') {
            statusLabel = i18n.submitted;
            statusClass = 'olama-status-submitted';
        }

        html += `<div class="olama-detail-section">
            <span class="olama-detail-label">${i18n.status}</span>
            <div class="olama-detail-value">
                <span class="olama-status-pill ${statusClass}">${statusLabel}</span>
            </div>
        </div>`;

        html += `</div>`; // End General Info

        // Section 2: Homework
        html += `<div class="olama-detail-group">
            <div class="olama-detail-group-title">
                <span class="dashicons dashicons-edit"></span>
                ${i18n.homework}
            </div>

            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.homeworkSB}</span>
                <div class="olama-detail-value">${plan.homework_sb || '-'}</div>
            </div>
            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.homeworkEB}</span>
                <div class="olama-detail-value">${plan.homework_eb || '-'}</div>
            </div>
            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.homeworkNB}</span>
                <div class="olama-detail-value">${plan.homework_nb || '-'}</div>
            </div>
            <div class="olama-detail-section">
                <span class="olama-detail-label">${i18n.homeworkWS}</span>
                <div class="olama-detail-value">${plan.homework_ws || '-'}</div>
            </div>
        </div>`; // End Homework

        // Section 3: Notes
        html += `<div class="olama-detail-group">
            <div class="olama-detail-group-title">
                <span class="dashicons dashicons-admin-comments"></span>
                ${i18n.teacherNotes}
            </div>
            <div class="olama-detail-section">
                <div class="olama-detail-value">${plan.teacher_notes || '-'}</div>
            </div>
        </div>`; // End Notes

        html += `</div>`; // End Single Column

        // Section 4: Actions (Phase 3)
        if (olamaPlanList.isSupervisor) {
            html += `<div class="olama-detail-group" style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px;">
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="button button-primary olama-approve-btn" data-id="${plan.id}" style="height: 35px; min-width: 100px;">
                        <span class="dashicons dashicons-yes-alt" style="margin-top: 6px;"></span> ${i18n.approve}
                    </button>
                    <button class="button olama-reject-btn" data-id="${plan.id}" style="height: 35px; min-width: 100px;">
                        <span class="dashicons dashicons-dismiss" style="margin-top: 6px;"></span> ${i18n.requestEdits}
                    </button>
                </div>
            </div>`;
        }

        card.html(html).fadeIn();

        $('html, body').animate({
            scrollTop: container.offset().top - 50
        }, 500);
    });

    // --- Phase 3: Review Logic ---
    var currentPlanId = null;

    $(document).on('click', '.olama-approve-btn', function () {
        var $btn = $(this);
        var planId = $btn.data('id');
        var i18n = olamaPlanList.i18n;

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + i18n.approving);

        $.ajax({
            url: olamaPlanList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'olama_handle_plan_approval',
                plan_id: planId,
                status: 'approved',
                nonce: olamaPlanList.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data || 'Error processing request');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> ' + i18n.approve);
                }
            }
        });
    });

    $(document).on('click', '.olama-reject-btn', function () {
        currentPlanId = $(this).data('id');
        $('#olama-feedback-text').val('');
        $('#olama-feedback-modal').css('display', 'flex');
    });

    $('.olama-modal-cancel').on('click', function () {
        $('#olama-feedback-modal').hide();
    });

    $('.olama-modal-submit').on('click', function () {
        var feedback = $('#olama-feedback-text').val();
        var i18n = olamaPlanList.i18n;

        if (!feedback.trim()) {
            alert(i18n.enterFeedback);
            return;
        }

        $(this).prop('disabled', true).text(i18n.sending);

        $.ajax({
            url: olamaPlanList.ajaxUrl,
            type: 'POST',
            data: {
                action: 'olama_handle_plan_approval',
                plan_id: currentPlanId,
                status: 'draft',
                feedback: feedback,
                nonce: olamaPlanList.nonce
            },
            success: function (response) {
                $('#olama-feedback-modal').hide();
                $('.olama-modal-submit').prop('disabled', false).text(i18n.requestEdits);

                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data || 'Error processing request');
                }
            }
        });
    });

    // Bulk Approve Functionality
    $('#olama-bulk-approve-btn').on('click', function () {
        const btn = $(this);
        const sectionId = btn.data('section');
        const weekStart = btn.data('week');
        const nonce = btn.data('nonce');
        const i18n = olamaPlanList.i18n;

        if (!confirm(i18n.confirmBulkApprove)) {
            return;
        }

        btn.prop('disabled', true).css('opacity', '0.7');
        const originalText = btn.html();
        btn.html('<span class="dashicons dashicons-update spin"></span> ' + (i18n.approving || 'Approving...'));

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'olama_bulk_approve_plans',
                section_id: sectionId,
                week_start: weekStart,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(i18n.bulkApproveSuccess);
                    window.location.reload();
                } else {
                    alert(response.data || (i18n.errorOccurred || 'Error occurred'));
                    btn.prop('disabled', false).css('opacity', '1').html(originalText);
                }
            },
            error: function () {
                alert(i18n.communicationError || 'Communication error');
                btn.prop('disabled', false).css('opacity', '1').html(originalText);
            }
        });
    });
});
