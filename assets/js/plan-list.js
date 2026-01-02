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
        const statusLabel = plan.status === 'published' ? i18n.published : i18n.draft;
        const statusClass = plan.status === 'published' ? 'olama-status-published' : 'olama-status-draft';

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

        card.html(html).fadeIn();

        $('html, body').animate({
            scrollTop: container.offset().top - 50
        }, 500);
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
        btn.html('<span class="dashicons dashicons-update spin"></span> ' + (i18n.loading || 'Approving...'));

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
                    alert(response.data || 'Error occurred');
                    btn.prop('disabled', false).css('opacity', '1').html(originalText);
                }
            },
            error: function () {
                alert('Communication error');
                btn.prop('disabled', false).css('opacity', '1').html(originalText);
            }
        });
    });
});
