jQuery(function ($) {
    'use strict';

    // Check if localization data exists
    if (typeof olamaShifts === 'undefined') {
        console.log('Shifts JS: olamaShifts not found');
        return;
    }

    console.log('Shifts JS: Initialized v2.0.3');

    // ============================================
    // MODAL & GLOBAL CONTROLS (Delegated)
    // ============================================

    $(document).on('click', '.olama-modal-close', function () {
        $(this).closest('.olama-modal').hide();
    });

    $(document).on('click', '.olama-modal', function (e) {
        if ($(e.target).hasClass('olama-modal')) {
            $(this).hide();
        }
    });

    // ============================================
    // PERIODS MANAGEMENT (Delegated)
    // ============================================

    $(document).on('click', '#olama-manage-periods-btn', function (e) {
        e.preventDefault();
        loadPeriods();
        $('#olama-periods-modal').show();
    });

    $(document).on('submit', '#olama-add-period-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        var formData = $form.serialize();
        formData += '&action=olama_save_shift_period&nonce=' + olamaShifts.nonce;

        $btn.prop('disabled', true);

        $.post(olamaShifts.ajaxUrl, formData, function (response) {
            if (response.success) {
                $form[0].reset();
                loadPeriods();
            } else {
                alert(response.data || 'Error saving period');
            }
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.delete-period', function (e) {
        e.preventDefault();
        if (!confirm('Delete this period?')) return;

        var id = $(this).data('id');
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_delete_shift_period',
            nonce: olamaShifts.nonce,
            id: id
        }, function () {
            loadPeriods();
            loadShifts();
        });
    });

    // ============================================
    // LOCATIONS MANAGEMENT (Delegated)
    // ============================================

    $(document).on('click', '#olama-manage-locations-btn', function (e) {
        e.preventDefault();
        loadLocations();
        $('#olama-locations-modal').show();
    });

    $(document).on('submit', '#olama-add-location-form', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        formData += '&action=olama_save_shift_location&nonce=' + olamaShifts.nonce;

        $.post(olamaShifts.ajaxUrl, formData, function (response) {
            if (response.success) {
                $('#olama-add-location-form')[0].reset();
                loadLocations();
            } else {
                alert(response.data || 'Error saving location');
            }
        });
    });

    $(document).on('click', '.delete-location', function (e) {
        e.preventDefault();
        if (!confirm('Delete this location?')) return;

        var id = $(this).data('id');
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_delete_shift_location',
            nonce: olamaShifts.nonce,
            id: id
        }, function () {
            loadLocations();
        });
    });

    // ============================================
    // TIME SLOTS MANAGEMENT (Delegated)
    // ============================================

    $(document).on('click', '#olama-manage-slots-btn', function (e) {
        e.preventDefault();
        loadSlots();
        $('#olama-slots-modal').show();
    });

    $(document).on('submit', '#olama-add-slot-form', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        formData += '&action=olama_save_shift_time_slot&nonce=' + olamaShifts.nonce;

        $.post(olamaShifts.ajaxUrl, formData, function (response) {
            if (response.success) {
                $('#olama-add-slot-form')[0].reset();
                loadSlots();
            } else {
                alert(response.data || 'Error saving time slot');
            }
        });
    });

    $(document).on('click', '.delete-slot', function (e) {
        e.preventDefault();
        if (!confirm('Delete this time slot?')) return;

        var id = $(this).data('id');
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_delete_shift_slot',
            nonce: olamaShifts.nonce,
            id: id
        }, function () {
            loadSlots();
        });
    });

    // ============================================
    // SHIFT ASSIGNMENTS (Delegated)
    // ============================================

    $(document).on('click', '#olama-add-shift-btn', function (e) {
        e.preventDefault();
        $('#olama-save-assignment-form')[0].reset();
        $('#olama-teacher-multiselect input').prop('checked', false);

        var currentPeriod = $('#olama-shift-period-select').val();
        if (currentPeriod) {
            $('#olama-modal-period-select').val(currentPeriod);
        }

        loadLocations();
        loadSlots();
        $('#olama-assignment-modal').show();
    });

    $(document).on('submit', '#olama-save-assignment-form', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        formData += '&action=olama_save_shift_and_assignments&nonce=' + olamaShifts.nonce;

        $.post(olamaShifts.ajaxUrl, formData, function (response) {
            if (response.success) {
                $('#olama-assignment-modal').hide();
                loadShifts();
            } else {
                alert(response.data || 'Error saving shift');
            }
        });
    });

    $(document).on('click', '.delete-shift', function (e) {
        e.preventDefault();
        if (!confirm('Delete this shift and all its assignments?')) return;

        var id = $(this).data('id');
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_delete_shift',
            nonce: olamaShifts.nonce,
            id: id
        }, function () {
            loadShifts();
        });
    });

    $(document).on('change', '#olama-shift-period-select', function () {
        loadShifts();
    });

    // ============================================
    // HELPER FUNCTIONS
    // ============================================

    function loadPeriods() {
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_get_shift_periods',
            nonce: olamaShifts.nonce
        }, function (response) {
            if (!response.success) {
                return;
            }

            var periods = response.data || [];
            var tableHtml = '';
            var optionsHtml = '<option value="">Select Period</option>';

            for (var i = 0; i < periods.length; i++) {
                var p = periods[i];
                tableHtml += '<tr>' +
                    '<td>' + (p.shift_type || 'N/A') + '</td>' +
                    '<td>Sem ' + (p.semester_id || 'N/A') + '</td>' +
                    '<td><button class="button delete-period" data-id="' + p.id + '"><span class="dashicons dashicons-trash"></span></button></td>' +
                    '</tr>';
                optionsHtml += '<option value="' + p.id + '">' + p.shift_type + ' (Sem ' + p.semester_id + ')</option>';
            }

            $('#olama-periods-list tbody').html(tableHtml);

            // Update main selection dropdown and preserve value
            var mainSelect = $('#olama-shift-period-select');
            var currentVal = mainSelect.val();
            mainSelect.html(optionsHtml);
            if (currentVal) {
                mainSelect.val(currentVal);
            }

            // Update modal dropdown
            $('#olama-modal-period-select').html(optionsHtml);
        });
    }

    function loadLocations() {
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_get_shift_locations',
            nonce: olamaShifts.nonce
        }, function (response) {
            if (!response.success) return;

            var locations = response.data || [];
            var tableHtml = '';
            var optionsHtml = '<option value="">Select Location</option>';

            for (var i = 0; i < locations.length; i++) {
                var loc = locations[i];
                tableHtml += '<tr>' +
                    '<td>' + loc.location_name + '</td>' +
                    '<td>' + loc.area_floor + '</td>' +
                    '<td>' + loc.gender + '</td>' +
                    '<td><button class="button delete-location" data-id="' + loc.id + '"><span class="dashicons dashicons-trash"></span></button></td>' +
                    '</tr>';
                optionsHtml += '<option value="' + loc.id + '">' + loc.location_name + ' (' + loc.gender + ')</option>';
            }

            $('#olama-locations-list tbody').html(tableHtml);
            $('#olama-modal-location-select').html(optionsHtml);
        });
    }

    function loadSlots() {
        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_get_shift_time_slots',
            nonce: olamaShifts.nonce
        }, function (response) {
            if (!response.success) return;

            var slots = response.data || [];
            var tableHtml = '';
            var optionsHtml = '<option value="">Select Time Slot</option>';

            for (var i = 0; i < slots.length; i++) {
                var slot = slots[i];
                tableHtml += '<tr>' +
                    '<td>' + slot.slot_label + '</td>' +
                    '<td>' + slot.start_time + ' - ' + slot.end_time + '</td>' +
                    '<td><button class="button delete-slot" data-id="' + slot.id + '"><span class="dashicons dashicons-trash"></span></button></td>' +
                    '</tr>';
                optionsHtml += '<option value="' + slot.id + '">' + slot.slot_label + '</option>';
            }

            $('#olama-slots-list tbody').html(tableHtml);
            $('#olama-modal-slot-select').html(optionsHtml);
        });
    }

    function loadShifts() {
        var periodId = $('#olama-shift-period-select').val();
        var $grid = $('#olama-shifts-grid');

        if (!periodId) {
            $grid.html('<div class="notice notice-info"><p>Please select a period to view shifts.</p></div>');
            return;
        }

        $('.olama-loading-overlay').show();

        $.post(olamaShifts.ajaxUrl, {
            action: 'olama_get_shift_schedule',
            nonce: olamaShifts.nonce,
            period_id: periodId
        }, function (response) {
            $('.olama-loading-overlay').hide();

            if (!response.success) {
                $grid.html('<div class="notice notice-error"><p>Error loading shifts.</p></div>');
                return;
            }

            renderShiftsTable(response.data || []);
        }).fail(function () {
            $('.olama-loading-overlay').hide();
            $grid.html('<div class="notice notice-error"><p>Server error.</p></div>');
        });
    }

    function renderShiftsTable(shifts) {
        var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Day</th>';
        html += '<th>Time Slot</th>';
        html += '<th>Location</th>';
        html += '<th>Gender</th>';
        html += '<th>Teachers</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        if (shifts.length === 0) {
            html += '<tr><td colspan="6">No shifts for this period.</td></tr>';
        } else {
            for (var i = 0; i < shifts.length; i++) {
                var s = shifts[i];
                var dayName = dayNames[s.day_of_week] || 'N/A';
                var teachers = s.teacher_names || '<em>No teachers</em>';

                html += '<tr>';
                html += '<td><strong>' + dayName + '</strong></td>';
                html += '<td>' + s.slot_label + ' (' + s.start_time + ' - ' + s.end_time + ')</td>';
                html += '<td>' + s.location_name + ' (' + s.area_floor + ')</td>';
                html += '<td><span class="olama-badge badge-' + s.location_gender + '">' + s.location_gender + '</span></td>';
                html += '<td>' + teachers + '</td>';
                html += '<td><button class="button delete-shift" data-id="' + s.shift_id + '"><span class="dashicons dashicons-trash"></span></button></td>';
                html += '</tr>';
            }
        }

        html += '</tbody></table>';
        $('#olama-shifts-grid').html(html);
    }

    // Initial Load
    loadPeriods();
});
