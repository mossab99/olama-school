<?php
/**
 * Admin Transportation - Buses View
 */
if (!defined('ABSPATH')) {
    exit;
}

$translate = array('Olama_School_Helpers', 'translate');
?>
<div class="wrap olama-school-wrap">
    <h1>
        <?php echo $translate('Transportation'); ?>
    </h1>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($allowed_tabs as $id => $label): ?>
            <a href="?page=olama-school-transport&tab=<?php echo $id; ?>"
                class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div class="olama-tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'buses'): ?>
            <div class="olama-card"
                style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">
                        <?php echo $translate('Bus Management'); ?>
                    </h2>
                    <button type="button" class="button button-primary" onclick="olamaOpenBusModal()">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                        <?php echo $translate('Add New Bus'); ?>
                    </button>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php echo $translate('Bus Number'); ?>
                            </th>
                            <th>
                                <?php echo $translate('Plate Number'); ?>
                            </th>
                            <th>
                                <?php echo $translate('Passenger Capacity'); ?>
                            </th>
                            <th>
                                <?php echo $translate('Driver'); ?>
                            </th>
                            <th>
                                <?php echo $translate('Companion'); ?>
                            </th>
                            <th>
                                <?php echo $translate('License Expiry'); ?>
                            </th>
                            <th>
                                <?php echo $translate('Status'); ?>
                            </th>
                            <th style="width: 120px;">
                                <?php echo $translate('Actions'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($buses): ?>
                            <?php foreach ($buses as $bus): ?>
                                <tr>
                                    <td><strong>
                                            <?php echo esc_html($bus->bus_number); ?>
                                        </strong></td>
                                    <td>
                                        <?php echo esc_html($bus->plate_number); ?>
                                    </td>
                                    <td>
                                        <?php echo intval($bus->passenger_capacity); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($bus->driver_user_id) {
                                            $driver = get_userdata($bus->driver_user_id);
                                            echo $driver ? esc_html($driver->display_name) : '-';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($bus->companion_user_id) {
                                            $companion = get_userdata($bus->companion_user_id);
                                            echo $companion ? esc_html($companion->display_name) : '-';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $bus->license_expiry_date ? Olama_School_Helpers::format_date($bus->license_expiry_date) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="olama-status-pill olama-status-<?php echo esc_attr($bus->status); ?>">
                                            <?php echo $bus->status === 'active' ? $translate('Active') : $translate('Inactive'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button type="button" class="button button-small"
                                                onclick='olamaOpenBusModal(<?php echo json_encode($bus); ?>)'>
                                                <span class="dashicons dashicons-edit"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                            </button>
                                            <button type="button" class="button button-small" style="color: #d63638;"
                                                onclick="olamaDeleteBus(<?php echo $bus->id; ?>, '<?php echo esc_js($bus->bus_number); ?>')">
                                                <span class="dashicons dashicons-trash"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <?php echo $translate('No data'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bus Modal -->
            <div id="olama-bus-modal" class="olama-modal">
                <div class="olama-modal-content" style="margin: 5% auto; width: 600px;">
                    <div class="olama-modal-header">
                        <h2 id="bus-modal-title">
                            <?php echo $translate('Add New Bus'); ?>
                        </h2>
                        <span class="olama-modal-close" onclick="olamaCloseBusModal()">&times;</span>
                    </div>
                    <form id="olama-bus-form">
                        <div class="olama-modal-body">
                            <input type="hidden" name="id" id="bus-id" />

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Bus Number'); ?>
                                    </label>
                                    <input type="text" name="bus_number" id="bus-number" required class="widefat" />
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Plate Number'); ?>
                                    </label>
                                    <input type="text" name="plate_number" id="bus-plate-number" required class="widefat" />
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Passenger Capacity'); ?>
                                    </label>
                                    <input type="number" name="passenger_capacity" id="bus-capacity" required
                                        class="widefat" min="1" />
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('License Expiry'); ?>
                                    </label>
                                    <input type="text" name="license_expiry_date" id="bus-license-expiry"
                                        class="widefat olama-datepicker" autocomplete="off" />
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Driver'); ?>
                                    </label>
                                    <select name="driver_user_id" id="bus-driver-id" class="widefat">
                                        <option value="">
                                            <?php echo $translate('Select Driver'); ?>
                                        </option>
                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?php echo $driver->ID; ?>">
                                                <?php echo esc_html($driver->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Companion'); ?>
                                    </label>
                                    <select name="companion_user_id" id="bus-companion-id" class="widefat">
                                        <option value="">
                                            <?php echo $translate('Select Companion'); ?>
                                        </option>
                                        <?php foreach ($companions as $companion): ?>
                                            <option value="<?php echo $companion->ID; ?>">
                                                <?php echo esc_html($companion->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Engine Capacity'); ?>
                                    </label>
                                    <input type="text" name="engine_capacity" id="bus-engine-capacity" class="widefat" />
                                </div>
                                <div>
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php echo $translate('Fuel Type'); ?>
                                    </label>
                                    <input type="text" name="fuel_type" id="bus-fuel-type" class="widefat" />
                                </div>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                                    <?php echo $translate('Status'); ?>
                                </label>
                                <select name="status" id="bus-status" class="widefat">
                                    <option value="active">
                                        <?php echo $translate('Active'); ?>
                                    </option>
                                    <option value="inactive">
                                        <?php echo $translate('Inactive'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="olama-modal-footer">
                            <button type="button" class="button" onclick="olamaCloseBusModal()">
                                <?php echo $translate('Cancel'); ?>
                            </button>
                            <button type="submit" class="button button-primary">
                                <?php echo $translate('Save Bus'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'assignments'): ?>
            <div class="olama-card"
                style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0;"><?php echo $translate('Student Assignments'); ?></h2>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                            <?php echo $translate('Academic Year'); ?>
                        </label>
                        <select id="assignment-year-filter" class="widefat">
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year->id; ?>" <?php selected($selected_year_id, $year->id); ?>>
                                    <?php echo esc_html($year->year_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                            <?php echo $translate('Select Bus'); ?>
                        </label>
                        <select id="assignment-bus-filter" class="widefat">
                            <option value=""><?php echo $translate('Select a bus'); ?></option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?php echo $bus->id; ?>" <?php selected($selected_bus_id, $bus->id); ?>>
                                    <?php echo esc_html($bus->bus_number . ' - ' . $bus->plate_number); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="assignment-content" style="display: none;">
                    <!-- Capacity Info -->
                    <div id="capacity-info"
                        style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo $translate('Capacity'); ?>:</strong>
                                <span id="capacity-text">0/0</span>
                            </div>
                            <div
                                style="width: 200px; background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                                <div id="capacity-bar"
                                    style="height: 100%; background: #0073aa; width: 0%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Students -->
                    <div style="margin-bottom: 30px;">
                        <h3><?php echo $translate('Assigned Students'); ?></h3>
                        <table class="wp-list-table widefat fixed striped" id="assigned-students-table">
                            <thead>
                                <tr>
                                    <th><?php echo $translate('Student Name'); ?></th>
                                    <th><?php echo $translate('Student ID'); ?></th>
                                    <th><?php echo $translate('Grade'); ?></th>
                                    <th><?php echo $translate('Section'); ?></th>
                                    <th style="width: 100px;"><?php echo $translate('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="assigned-students-body">
                                <tr>
                                    <td colspan="5" style="text-align: center;">
                                        <?php echo $translate('Loading...'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Unassigned Students -->
                    <div>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;"><?php echo $translate('Unassigned Students'); ?></h3>
                            <button type="button" class="button button-primary" id="assign-selected-btn" disabled>
                                <?php echo $translate('Assign Selected'); ?>
                            </button>
                        </div>
                        <table class="wp-list-table widefat fixed striped" id="unassigned-students-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all-students" />
                                    </th>
                                    <th><?php echo $translate('Student Name'); ?></th>
                                    <th><?php echo $translate('Student ID'); ?></th>
                                    <th><?php echo $translate('Grade'); ?></th>
                                    <th><?php echo $translate('Section'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="unassigned-students-body">
                                <tr>
                                    <td colspan="5" style="text-align: center;">
                                        <?php echo $translate('Loading...'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-bus-selected" style="text-align: center; padding: 40px; color: #666;">
                    <span class="dashicons dashicons-bus" style="font-size: 48px; opacity: 0.3;"></span>
                    <p><?php echo $translate('Please select a bus to manage student assignments'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        $('.olama-datepicker').datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true
        });

        $('#olama-bus-form').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var $submitBtn = $(this).find('button[type="submit"]');

            $submitBtn.prop('disabled', true).text('<?php echo $translate("Saving..."); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=olama_save_bus&nonce=' + olamaAdmin.adminNonce,
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                        $submitBtn.prop('disabled', false).text('<?php echo $translate("Save Bus"); ?>');
                    }
                },
                error: function () {
                    alert('<?php echo $translate("Communication error"); ?>');
                    $submitBtn.prop('disabled', false).text('<?php echo $translate("Save Bus"); ?>');
                }
            });
        });
    });

    function olamaOpenBusModal(bus = null) {
        var $ = jQuery;
        var $modal = $('#olama-bus-modal');
        var $form = $('#olama-bus-form');

        if (bus) {
            $('#bus-modal-title').text('<?php echo $translate("Edit Bus"); ?>');
            $('#bus-id').val(bus.id);
            $('#bus-number').val(bus.bus_number);
            $('#bus-plate-number').val(bus.plate_number);
            $('#bus-capacity').val(bus.passenger_capacity);
            $('#bus-license-expiry').val(bus.license_expiry_date ? olamaFormatDate(bus.license_expiry_date) : '');
            $('#bus-driver-id').val(bus.driver_user_id);
            $('#bus-companion-id').val(bus.companion_user_id);
            $('#bus-engine-capacity').val(bus.engine_capacity);
            $('#bus-fuel-type').val(bus.fuel_type);
            $('#bus-status').val(bus.status);
        } else {
            $('#bus-modal-title').text('<?php echo $translate("Add New Bus"); ?>');
            $form[0].reset();
            $('#bus-id').val('');
        }

        $modal.show();
    }

    function olamaCloseBusModal() {
        jQuery('#olama-bus-modal').hide();
    }

    function olamaDeleteBus(id, busNumber) {
        if (!confirm('<?php echo $translate("Are you sure you want to delete this bus?"); ?>' + ' (' + busNumber + ')')) {
            return;
        }

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'olama_delete_bus',
                id: id,
                nonce: olamaAdmin.adminNonce
            },
            success: function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    }

    function olamaFormatDate(dateStr) {
        if (!dateStr) return '';
        var date = new Date(dateStr);
        var day = ("0" + date.getDate()).slice(-2);
        var month = ("0" + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();
        return day + "-" + month + "-" + year;
    }


    // ==========================================
    // Student Assignment Functions
    // ==========================================

    jQuery(document).ready(function ($) {
        var currentBusId = 0;
        var currentYearId = 0;

        function loadBusAssignments() {
            var busId = $('#assignment-bus-filter').val();
            var yearId = $('#assignment-year-filter').val();

            if (!busId) {
                $('#assignment-content').hide();
                $('#no-bus-selected').show();
                return;
            }

            currentBusId = busId;
            currentYearId = yearId;

            $('#no-bus-selected').hide();
            $('#assignment-content').show();

            // Load assigned students
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'olama_get_bus_students',
                    bus_id: busId,
                    academic_year_id: yearId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        updateCapacityInfo(response.data.capacity);
                        renderAssignedStudents(response.data.students);
                    } else {
                        alert(response.data);
                    }
                }
            });

            // Load unassigned students
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'olama_get_unassigned_students',
                    academic_year_id: yearId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        // Log debug information
                        if (response.data.debug) {
                            console.log('Student Assignment Debug:', response.data.debug);
                        }
                        // Handle both old format (array) and new format (object with students array)
                        var students = response.data.students || response.data;
                        renderUnassignedStudents(students);
                    } else {
                        alert(response.data);
                    }
                }
            });
        }

        function updateCapacityInfo(capacity) {
            var percentage = capacity.percentage;
            var text = capacity.assigned + '/' + capacity.total;

            $('#capacity-text').text(text);
            $('#capacity-bar').css('width', percentage + '%');

            // Change color based on capacity
            if (percentage >= 100) {
                $('#capacity-bar').css('background', '#dc3232');
            } else if (percentage >= 80) {
                $('#capacity-bar').css('background', '#f56e28');
            } else {
                $('#capacity-bar').css('background', '#0073aa');
            }
        }

        function renderAssignedStudents(students) {
            var tbody = $('#assigned-students-body');
            tbody.empty();

            if (students.length === 0) {
                tbody.append('<tr><td colspan="5" style="text-align: center;"><?php echo $translate('No students assigned'); ?></td></tr>');
                return;
            }

            students.forEach(function (student) {
                var row = $('<tr>');
                row.append('<td>' + (student.student_name || '') + '</td>');
                row.append('<td>' + (student.student_uid || '') + '</td>');
                row.append('<td>' + (student.grade_name || '') + '</td>');
                row.append('<td>' + (student.section_name || '') + '</td>');
                row.append('<td><button class="button button-small unassign-btn" data-student-id="' + student.id + '"><?php echo $translate('Unassign'); ?></button></td>');
                tbody.append(row);
            });
        }

        function renderUnassignedStudents(students) {
            var tbody = $('#unassigned-students-body');
            tbody.empty();

            if (students.length === 0) {
                tbody.append('<tr><td colspan="5" style="text-align: center;"><?php echo $translate('All students are assigned'); ?></td></tr>');
                return;
            }

            students.forEach(function (student) {
                var row = $('<tr>');
                row.append('<td><input type="checkbox" class="student-checkbox" value="' + student.id + '" /></td>');
                row.append('<td>' + (student.student_name || '') + '</td>');
                row.append('<td>' + (student.student_uid || '') + '</td>');
                row.append('<td>' + (student.grade_name || '') + '</td>');
                row.append('<td>' + (student.section_name || '') + '</td>');
                tbody.append(row);
            });
        }

        // Event handlers for assignments tab
        $('#assignment-bus-filter, #assignment-year-filter').on('change', function () {
            loadBusAssignments();
        });

        $('#select-all-students').on('change', function () {
            $('.student-checkbox').prop('checked', $(this).prop('checked'));
            updateAssignButton();
        });

        $(document).on('change', '.student-checkbox', function () {
            updateAssignButton();
        });

        function updateAssignButton() {
            var checkedCount = $('.student-checkbox:checked').length;
            $('#assign-selected-btn').prop('disabled', checkedCount === 0);
        }

        $('#assign-selected-btn').on('click', function () {
            var studentIds = $('.student-checkbox:checked').map(function () {
                return $(this).val();
            }).get();

            if (studentIds.length === 0) {
                return;
            }

            if (!confirm('<?php echo $translate('Assign selected students to this bus?'); ?>')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'olama_assign_students_to_bus',
                    bus_id: currentBusId,
                    student_ids: studentIds,
                    academic_year_id: currentYearId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data.message);
                        loadBusAssignments();
                    } else {
                        alert(response.data);
                    }
                }
            });
        });

        $(document).on('click', '.unassign-btn', function () {
            var studentId = $(this).data('student-id');

            if (!confirm('<?php echo $translate('Unassign this student from the bus?'); ?>')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'olama_unassign_student_from_bus',
                    student_id: studentId,
                    academic_year_id: currentYearId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        alert(response.data);
                        loadBusAssignments();
                    } else {
                        alert(response.data);
                    }
                }
            });
        });

        // Load assignments if bus is pre-selected
        <?php if ($active_tab === 'assignments' && $selected_bus_id): ?>
            loadBusAssignments();
        <?php endif; ?>
    });
</script>