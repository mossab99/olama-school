jQuery(document).ready(function ($) {
    console.log('Olama Shifts JS Loaded (Refactored)');

    // Fallback for localization data
    if (typeof olamaShifts === 'undefined') {
        console.error('olamaShifts is not defined. Localization failed.');
        return;
    }

    // Modals
    const periodModal = $('#olama-periods-modal');
    const locationModal = $('#olama-locations-modal');
    const slotModal = $('#olama-slots-modal');
    const assignmentModal = $('#olama-assignment-modal');
    const shiftsGrid = $('#olama-shifts-grid');
    const loadingOverlay = $('.olama-loading-overlay');

    // --- Event Registration (Move to top for resilience) ---

    // Modal Global Controls
    $('.olama-modal-close').on('click', function () {
        $(this).closest('.olama-modal').hide();
    });

    // --- Periods ---
    $('#olama-manage-periods-btn').on('click', function () {
        loadPeriods();
        periodModal.show();
    });

    $('#olama-add-period-form').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=olama_save_shift_period&nonce=' + olamaShifts.nonce;
        $.post(olamaShifts.ajaxUrl, data, function (res) {
            if (res.success) {
                $('#olama-add-period-form')[0].reset();
                loadPeriods();
            } else {
                alert(res.data || 'Error saving period');
            }
        });
    });

    $(document).on('click', '.delete-period', function () {
        if (!confirm('Delete period? This will affect all shifts in this period.')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_period', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadPeriods();
            loadShifts();
        });
    });

    // --- Locations ---
    $('#olama-manage-locations-btn').on('click', function () {
        loadLocations();
        locationModal.show();
    });

    $('#olama-add-location-form').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=olama_save_shift_location&nonce=' + olamaShifts.nonce;
        $.post(olamaShifts.ajaxUrl, data, function (res) {
            if (res.success) {
                $('#olama-add-location-form')[0].reset();
                loadLocations();
            } else {
                alert(res.data || 'Error saving location');
            }
        });
    });

    $(document).on('click', '.delete-location', function () {
        if (!confirm('Delete location?')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_location', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadLocations();
        });
    });

    // --- Time Slots ---
    $('#olama-manage-slots-btn').on('click', function () {
        loadSlots();
        slotModal.show();
    });

    $('#olama-add-slot-form').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=olama_save_shift_time_slot&nonce=' + olamaShifts.nonce;
        $.post(olamaShifts.ajaxUrl, data, function (res) {
            if (res.success) {
                $('#olama-add-slot-form')[0].reset();
                loadSlots();
            } else {
                alert(res.data || 'Error saving time slot');
            }
        });
    });

    $(document).on('click', '.delete-slot', function () {
        if (!confirm('Delete slot?')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_slot', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadSlots();
        });
    });

    // --- Assignments ---
    $('#olama-add-shift-btn').on('click', function () {
        $('#olama-save-assignment-form')[0].reset();
        $('#olama-teacher-multiselect input').prop('checked', false);

        const currentPeriod = $('#olama-shift-period-select').val();
        if (currentPeriod) $('#olama-modal-period-select').val(currentPeriod);

        loadLocations();
        loadSlots();
        assignmentModal.show();
    });

    $('#olama-save-assignment-form').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=olama_save_shift_and_assignments&nonce=' + olamaShifts.nonce;
        $.post(olamaShifts.ajaxUrl, data, function (res) {
            if (res.success) {
                assignmentModal.hide();
                loadShifts();
            } else {
                alert(res.data || 'Conflict or Error detected');
            }
        });
    });

    $(document).on('click', '.delete-shift', function () {
        if (!confirm('Delete entire shift and all associated assignments?')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadShifts();
        });
    });

    $('#olama-shift-period-select').on('change', loadShifts);

    // --- Helper Functions ---

    function loadPeriods() {
        $.post(olamaShifts.ajaxUrl, { action: 'olama_get_shift_periods', nonce: olamaShifts.nonce }, function (res) {
            if (res.success) {
                let html = '';
                let options = '<option value="">Select Period</option>';
                (res.data || []).forEach(p => {
                    html += `<tr>
                        <td>${p.shift_type}</td>
                        <td>Sem ${p.semester_id}</td>
                        <td>
                            <button class="button delete-period" data-id="${p.id}"><span class="dashicons dashicons-trash"></span></button>
                        </td>
                    </tr>`;
                    options += `<option value="${p.id}">${p.shift_type} (Sem ${p.semester_id})</option>`;
                });
                $('#olama-periods-list tbody').html(html);
                $('#olama-shift-period-select').html(options);
                $('#olama-modal-period-select').html(options);
            }
        });
    }

    function loadShifts() {
        const period_id = $('#olama-shift-period-select').val();
        if (!period_id) {
            shiftsGrid.html('<div class="notice notice-info"><p>Please select a period to view shifts.</p></div>');
            return;
        }

        loadingOverlay.show();
        $.ajax({
            url: olamaShifts.ajaxUrl,
            type: 'POST',
            data: {
                action: 'olama_get_shift_schedule',
                nonce: olamaShifts.nonce,
                period_id: period_id
            },
            success: function (response) {
                loadingOverlay.hide();
                if (response.success) {
                    renderGrid(response.data);
                }
            }
        });
    }

    function renderGrid(data) {
        let html = '<table class="wp-list-table widefat fixed striped">';
        html += `<thead><tr>
            <th>${olamaShifts.i18n.day}</th>
            <th>${olamaShifts.i18n.slot}</th>
            <th>${olamaShifts.i18n.location}</th>
            <th>${olamaShifts.i18n.gender}</th>
            <th>${olamaShifts.i18n.teachers}</th>
            <th>${olamaShifts.i18n.actions}</th>
        </tr></thead><tbody>`;

        if (data.length === 0) {
            html += '<tr><td colspan="6">No shifts defined for this period.</td></tr>';
        } else {
            data.forEach(shift => {
                const teachersHtml = shift.teacher_names ? shift.teacher_names : '<em>No teachers assigned</em>';
                html += `<tr>
                    <td><strong>${getDayName(shift.day_of_week)}</strong></td>
                    <td>${shift.slot_label} (${shift.start_time} - ${shift.end_time})</td>
                    <td>${shift.location_name} (${shift.area_floor})</td>
                    <td><span class="olama-badge badge-${shift.location_gender}">${shift.location_gender}</span></td>
                    <td>${teachersHtml}</td>
                    <td>
                        <button class="button delete-shift" data-id="${shift.shift_id}"><span class="dashicons dashicons-trash"></span></button>
                    </td>
                </tr>`;
            });
        }
        html += '</tbody></table>';
        shiftsGrid.html(html);
    }

    function getDayName(dayIndex) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        return days[dayIndex] || 'N/A';
    }

    function loadLocations() {
        $.post(olamaShifts.ajaxUrl, { action: 'olama_get_shift_locations', nonce: olamaShifts.nonce }, function (res) {
            if (res.success) {
                let html = '';
                (res.data || []).forEach(loc => {
                    html += `<tr><td>${loc.location_name}</td><td>${loc.area_floor}</td><td>${loc.gender}</td><td><button class="button delete-location" data-id="${loc.id}"><span class="dashicons dashicons-trash"></span></button></td></tr>`;
                });
                $('#olama-locations-list tbody').html(html);

                let options = '<option value="">Select Location</option>';
                (res.data || []).forEach(loc => { options += `<option value="${loc.id}">${loc.location_name} (${loc.gender})</option>`; });
                $('#olama-modal-location-select').html(options);
            }
        });
    }

    function loadSlots() {
        $.post(olamaShifts.ajaxUrl, { action: 'olama_get_shift_time_slots', nonce: olamaShifts.nonce }, function (res) {
            if (res.success) {
                let html = '';
                (res.data || []).forEach(slot => {
                    html += `<tr><td>${slot.slot_label}</td><td>${slot.start_time} - ${slot.end_time}</td><td><button class="button delete-slot" data-id="${slot.id}"><span class="dashicons dashicons-trash"></span></button></td></tr>`;
                });
                $('#olama-slots-list tbody').html(html);

                let options = '<option value="">Select Slot</option>';
                (res.data || []).forEach(slot => { options += `<option value="${slot.id}">${slot.slot_label}</option>`; });
                $('#olama-modal-slot-select').html(options);
            }
        });
    }

    // Load initial data last
    try {
        loadPeriods();
    } catch (e) {
        console.error('Initial data load failed:', e);
    }
});
