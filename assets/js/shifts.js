jQuery(document).ready(function ($) {
    console.log('Olama Shifts JS Loaded');
    const shiftsGrid = $('#olama-shifts-grid');
    const loadingOverlay = $('.olama-loading-overlay');

    // Modals
    const locationModal = $('#olama-locations-modal');
    const slotModal = $('#olama-slots-modal');
    const assignmentModal = $('#olama-assignment-modal');

    // Load initial data
    loadShifts();

    function loadShifts() {
        loadingOverlay.show();
        const teacher_id = $('#olama-shift-teacher-select').val();

        $.ajax({
            url: olamaShifts.ajaxUrl,
            type: 'POST',
            data: {
                action: 'olama_get_shift_schedule',
                nonce: olamaShifts.nonce,
                teacher_id: teacher_id
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
            <th>${olamaShifts.i18n.selectTeacher}</th>
            <th>${olamaShifts.i18n.selectLocation}</th>
            <th>${olamaShifts.i18n.selectSlot}</th>
            <th>Day</th>
            <th>Actions</th>
        </tr></thead><tbody>`;

        if (data.length === 0) {
            html += '<tr><td colspan="5">No shifts found.</td></tr>';
        } else {
            data.forEach(shift => {
                html += `<tr>
                    <td>${shift.teacher_name}</td>
                    <td>${shift.location_name} (${shift.area_floor})</td>
                    <td>${shift.slot_label} (${shift.start_time} - ${shift.end_time})</td>
                    <td>${getDayName(shift.day_of_week)}</td>
                    <td>
                        <button class="button delete-shift" data-id="${shift.id}"><span class="dashicons dashicons-trash"></span></button>
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

    $('#olama-shift-teacher-select').on('change', loadShifts);

    // Modal Global Controls
    $('.olama-modal-close').on('click', function () {
        $(this).closest('.olama-modal').hide();
    });

    // --- Locations ---
    $('#olama-manage-locations-btn').on('click', function () {
        loadLocations();
        locationModal.show();
    });

    function loadLocations() {
        $.post(olamaShifts.ajaxUrl, { action: 'olama_get_shift_locations', nonce: olamaShifts.nonce }, function (res) {
            if (res.success) {
                let html = '';
                res.data.forEach(loc => {
                    html += `<tr><td>${loc.location_name}</td><td>${loc.area_floor}</td><td><button class="button delete-location" data-id="${loc.id}"><span class="dashicons dashicons-trash"></span></button></td></tr>`;
                });
                $('#olama-locations-list tbody').html(html);

                // Also update dropdown in assignment modal
                let options = '<option value="">Select Location</option>';
                res.data.forEach(loc => { options += `<option value="${loc.id}">${loc.location_name}</option>`; });
                $('#olama-modal-location-select').html(options);
            } else {
                console.error('Failed to load locations', res);
            }
        }).fail(function () {
            console.error('Server error loading locations');
        });
    }

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
        }).fail(function () {
            alert('Server error while saving location');
        });
    });

    $(document).on('click', '.delete-location', function () {
        if (!confirm('Delete location? Assignments will remain but might look broken.')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_location', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadLocations();
        }).fail(function () {
            alert('Server error while deleting location');
        });
    });

    // --- Time Slots ---
    $('#olama-manage-slots-btn').on('click', function () {
        loadSlots();
        slotModal.show();
    });

    function loadSlots() {
        $.post(olamaShifts.ajaxUrl, { action: 'olama_get_shift_time_slots', nonce: olamaShifts.nonce }, function (res) {
            if (res.success) {
                let html = '';
                res.data.forEach(slot => {
                    html += `<tr><td>${slot.slot_label}</td><td>${slot.start_time} - ${slot.end_time}</td><td>${slot.gender_focus}</td><td><button class="button delete-slot" data-id="${slot.id}"><span class="dashicons dashicons-trash"></span></button></td></tr>`;
                });
                $('#olama-slots-list tbody').html(html);

                // Also update dropdown in assignment modal
                let options = '<option value="">Select Slot</option>';
                res.data.forEach(slot => { options += `<option value="${slot.id}">${slot.slot_label}</option>`; });
                $('#olama-modal-slot-select').html(options);
            } else {
                console.error('Failed to load slots', res);
            }
        }).fail(function () {
            console.error('Server error loading slots');
        });
    }

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
        }).fail(function () {
            alert('Server error while saving time slot');
        });
    });

    // --- Assignments ---
    $('#olama-add-shift-btn').on('click', function () {
        loadLocations();
        loadSlots();
        assignmentModal.show();
    });

    $('#olama-save-assignment-form').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=olama_save_shift_assignment&nonce=' + olamaShifts.nonce;
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
        if (!confirm(olamaShifts.i18n.confirmDelete)) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_assignment', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadShifts();
        });
    });

    // --- Bulk Copy ---
    $('#olama-bulk-copy-btn').on('click', function () {
        if (!confirm('Copy all shifts to Semester 2?')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_bulk_copy_shifts', nonce: olamaShifts.nonce, from_semester_id: 1, to_semester_id: 2 }, function (res) {
            alert(res.data);
            loadShifts();
        });
    });

    $(document).on('click', '.delete-slot', function () {
        if (!confirm('Delete slot? This will affect multiple assignments.')) return;
        $.post(olamaShifts.ajaxUrl, { action: 'olama_delete_shift_slot', nonce: olamaShifts.nonce, id: $(this).data('id') }, function () {
            loadSlots();
        });
    });
});
