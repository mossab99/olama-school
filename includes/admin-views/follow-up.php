<?php
/**
 * Follow Up Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

$can_manage_attendance = Olama_School_Permissions::can('olama_manage_attendance');
$can_manage_shifts = Olama_School_Permissions::can('olama_manage_shifts');
$can_manage_cleaning = Olama_School_Permissions::can('olama_manage_cleaning');

if (!$can_manage_attendance && !$can_manage_shifts && !$can_manage_cleaning) {
    wp_die(__('Unauthorized', 'olama-school'));
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : ($can_manage_attendance ? 'student_attendance' : ($can_manage_shifts ? 'employee_shifts' : 'cleaning'));

// Validate active tab access
if ($active_tab === 'student_attendance' && !$can_manage_attendance) {
    $active_tab = $can_manage_shifts ? 'employee_shifts' : 'cleaning';
} elseif ($active_tab === 'employee_shifts' && !$can_manage_shifts) {
    $active_tab = $can_manage_attendance ? 'student_attendance' : 'cleaning';
} elseif ($active_tab === 'cleaning' && !$can_manage_cleaning) {
    $active_tab = $can_manage_attendance ? 'student_attendance' : 'employee_shifts';
}

$grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$attendance_date = isset($_GET['attendance_date']) ? sanitize_text_field($_GET['attendance_date']) : current_time('Y-m-d');

$active_year = Olama_School_Academic::get_active_year();
$active_sem = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;

$grades = Olama_School_Grade::get_grades();
$sections = $grade_id ? Olama_School_Section::get_by_grade($grade_id, $active_year->id) : array();

$students = array();
$attendance_records = array();

if ($section_id && $active_year && $can_manage_attendance) {
    $students = Olama_School_Student::get_students(array(
        'academic_year_id' => $active_year->id,
        'section_id' => $section_id
    ));

    global $wpdb;
    $table = $wpdb->prefix . 'olama_attendance';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT student_id, status, reason FROM $table WHERE section_id = %d AND attendance_date = %s",
        $section_id,
        $attendance_date
    ));

    foreach ($results as $res) {
        $attendance_records[$res->student_id] = $res;
    }
}
?>

<div class="wrap olama-school-wrap">

    <h1>
        <?php echo Olama_School_Helpers::translate('Follow Up'); ?>
    </h1>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'attendance_saved'): ?>
        <div class="updated notice is-dismissible">
            <p>
                <?php echo Olama_School_Helpers::translate('Attendance saved successfully.'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'cleaning_saved'): ?>
        <div class="updated notice is-dismissible">
            <p>
                <?php echo Olama_School_Helpers::translate('Cleaning log saved successfully.'); ?>
            </p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <?php if ($can_manage_attendance): ?>
            <a href="?page=olama-school-follow-up&tab=student_attendance"
                class="nav-tab <?php echo $active_tab === 'student_attendance' ? 'nav-tab-active' : ''; ?>">
                <?php echo Olama_School_Helpers::translate('Student Attendance'); ?>
            </a>
        <?php endif; ?>
        <?php if ($can_manage_shifts): ?>
            <a href="?page=olama-school-follow-up&tab=employee_shifts"
                class="nav-tab <?php echo $active_tab === 'employee_shifts' ? 'nav-tab-active' : ''; ?>">
                <?php echo Olama_School_Helpers::translate('Employee Shifts'); ?>
            </a>
        <?php endif; ?>
        <?php if ($can_manage_cleaning): ?>
            <a href="?page=olama-school-follow-up&tab=cleaning"
                class="nav-tab <?php echo $active_tab === 'cleaning' ? 'nav-tab-active' : ''; ?>">
                <?php echo Olama_School_Helpers::translate('Cleaning'); ?>
            </a>
        <?php endif; ?>
    </h2>

    <div class="olama-tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'student_attendance' && $can_manage_attendance): ?>
            <div class="attendance-filters card" style="padding: 15px; margin-bottom: 20px; max-width: 100%;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="olama-school-follow-up">
                    <input type="hidden" name="tab" value="student_attendance">

                    <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div>
                            <label><strong>
                                    <?php echo Olama_School_Helpers::translate('Academic Year:'); ?>
                                </strong></label><br>
                            <input type="text" value="<?php echo esc_attr($active_year->year_name ?? ''); ?>" readonly
                                disabled class="regular-text" style="width: 150px;">
                        </div>
                        <div>
                            <label><strong>
                                    <?php echo Olama_School_Helpers::translate('Semester:'); ?>
                                </strong></label><br>
                            <input type="text" value="<?php echo esc_attr($active_sem->semester_name ?? ''); ?>" readonly
                                disabled class="regular-text" style="width: 150px;">
                        </div>
                        <div>
                            <label><strong>
                                    <?php echo Olama_School_Helpers::translate('Grade:'); ?>
                                </strong></label><br>
                            <select name="grade_id" onchange="this.form.submit()">
                                <option value="">
                                    <?php echo Olama_School_Helpers::translate('Select Grade'); ?>
                                </option>
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?php echo $g->id; ?>" <?php selected($grade_id, $g->id); ?>>
                                        <?php echo esc_html($g->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><strong>
                                    <?php echo Olama_School_Helpers::translate('Section:'); ?>
                                </strong></label><br>
                            <select name="section_id" onchange="this.form.submit()" <?php echo empty($sections) ? 'disabled' : ''; ?>>
                                <option value="">
                                    <?php echo Olama_School_Helpers::translate('Select Section'); ?>
                                </option>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?php echo $s->id; ?>" <?php selected($section_id, $s->id); ?>>
                                        <?php echo esc_html($s->section_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label><strong>
                                    <?php echo Olama_School_Helpers::translate('Date:'); ?>
                                </strong></label><br>
                            <input type="date" name="attendance_date" value="<?php echo esc_attr($attendance_date); ?>"
                                onchange="this.form.submit()">
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($section_id): ?>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_bulk_attendance'); ?>
                    <input type="hidden" name="olama_save_bulk_attendance" value="1">
                    <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
                    <input type="hidden" name="academic_year_id" value="<?php echo $active_year->id; ?>">
                    <input type="hidden" name="semester_id" value="<?php echo $active_sem->id ?? 0; ?>">

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="50">
                                    <?php echo Olama_School_Helpers::translate('ID'); ?>
                                </th>
                                <th>
                                    <?php echo Olama_School_Helpers::translate('Student Name'); ?>
                                </th>
                                <th width="150">
                                    <?php echo Olama_School_Helpers::translate('Status'); ?>
                                </th>
                                <th>
                                    <?php echo Olama_School_Helpers::translate('Reason (if absent)'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="4">
                                        <?php echo Olama_School_Helpers::translate('No students found in this section.'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $stu):
                                    $record = $attendance_records[$stu->id] ?? null;
                                    $status = $record ? $record->status : 'present';
                                    $reason = $record ? $record->reason : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($stu->student_uid); ?>
                                        </td>
                                        <td><strong>
                                                <?php echo esc_html($stu->student_name); ?>
                                            </strong></td>
                                        <td>
                                            <select name="attendance[<?php echo $stu->id; ?>][status]"
                                                class="attendance-status-selector" data-student="<?php echo $stu->id; ?>">
                                                <option value="present" <?php selected($status, 'present'); ?>>
                                                    <?php echo Olama_School_Helpers::translate('Present'); ?>
                                                </option>
                                                <option value="absent" <?php selected($status, 'absent'); ?> style="color: red;
                                    font-weight: bold;">
                                                    <?php echo Olama_School_Helpers::translate('Absent'); ?>
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="attendance[<?php echo $stu->id; ?>][reason]"
                                                value="<?php echo esc_attr($reason); ?>" class="large-text"
                                                placeholder="<?php echo Olama_School_Helpers::translate('Reason...'); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php echo Olama_School_Helpers::translate('Save Attendance'); ?>
                        </button>
                    </p>
                </form>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>
                        <?php echo Olama_School_Helpers::translate('Please select a Grade and Section to load students.'); ?>
                    </p>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'employee_shifts' && $can_manage_shifts):
            $all_teachers = get_users(array('role__in' => array('administrator', 'editor', 'author', 'teacher')));
            ?>
            <div class="olama-shifts-container">
                <div class="olama-shifts-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <button class="button button-secondary" id="olama-manage-periods-btn">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo Olama_School_Helpers::translate('Manage Periods'); ?>
                        </button>
                        <button class="button button-secondary" id="olama-manage-locations-btn">
                            <span class="dashicons dashicons-location-alt"></span>
                            <?php echo Olama_School_Helpers::translate('Manage Locations'); ?>
                        </button>
                        <button class="button button-secondary" id="olama-manage-slots-btn">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo Olama_School_Helpers::translate('Time Slots'); ?>
                        </button>
                    </div>
                    <div>
                        <button class="button button-primary" id="olama-add-shift-btn">
                            <span class="dashicons dashicons-plus"></span>
                            <?php echo Olama_School_Helpers::translate('Define Shift'); ?>
                        </button>
                    </div>
                </div>

                <div class="card olama-shifts-controls" style="padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 15px; align-items: flex-end;">
                        <div>
                            <label><strong><?php echo Olama_School_Helpers::translate('Select Period:'); ?></strong></label><br>
                            <select id="olama-shift-period-select" class="regular-text">
                                <option value=""><?php echo Olama_School_Helpers::translate('Select Period'); ?></option>
                                <!-- Populated via JS -->
                            </select>
                        </div>
                    </div>
                </div>

                <div id="olama-shifts-grid-container">
                    <div class="olama-loading-overlay" style="display:none; text-align:center; padding: 20px;">
                        <span class="spinner is-active" style="float:none;"></span>
                        <?php echo Olama_School_Helpers::translate('Loading shifts...'); ?>
                    </div>
                    <div id="olama-shifts-grid"></div>
                </div>
            </div>

            <!-- Periods Management Modal -->
            <div id="olama-periods-modal" class="olama-modal" style="display:none;">
                <div class="olama-modal-content card" style="max-width: 600px; margin: 10% auto; padding: 25px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0;"><?php echo Olama_School_Helpers::translate('Manage Shift Periods'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-period-form" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; align-items: end;">
                            <div>
                                <label
                                    style="font-size: 0.8em;"><?php echo Olama_School_Helpers::translate('Year:'); ?></label>
                                <select name="academic_year_id" class="widefat" required>
                                    <?php
                                    $years = Olama_School_Academic::get_years();
                                    foreach ($years as $y)
                                        echo '<option value="' . $y->id . '" ' . selected($active_year->id, $y->id, false) . '>' . $y->year_name . '</option>';
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label
                                    style="font-size: 0.8em;"><?php echo Olama_School_Helpers::translate('Semester:'); ?></label>
                                <select name="semester_id" class="widefat" required>
                                    <?php
                                    $sems = Olama_School_Academic::get_semesters($active_year->id);
                                    foreach ($sems as $s)
                                        echo '<option value="' . $s->id . '" ' . selected($active_sem->id, $s->id, false) . '>' . $s->semester_name . '</option>';
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label
                                    style="font-size: 0.8em;"><?php echo Olama_School_Helpers::translate('Type:'); ?></label>
                                <input type="text" name="shift_type"
                                    placeholder="<?php echo Olama_School_Helpers::translate('Morning, Evening...'); ?>"
                                    required class="widefat">
                            </div>
                            <button type="submit"
                                class="button button-primary"><?php echo Olama_School_Helpers::translate('Add'); ?></button>
                        </div>
                    </form>

                    <div id="olama-periods-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo Olama_School_Helpers::translate('Type'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Semester'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Location Management Modal -->
            <div id="olama-locations-modal" class="olama-modal" style="display:none;">
                <div class="olama-modal-content card" style="max-width: 600px; margin: 10% auto; padding: 25px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0;"><?php echo Olama_School_Helpers::translate('Manage Locations'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-location-form"
                        style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="location_name"
                                placeholder="<?php echo Olama_School_Helpers::translate('Location Name (e.g. Playground)'); ?>"
                                required class="regular-text" style="flex:2;">
                            <input type="text" name="area_floor"
                                placeholder="<?php echo Olama_School_Helpers::translate('Area/Floor'); ?>"
                                class="regular-text" style="flex:1;">
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="gender" class="regular-text">
                                <option value="mixed"><?php echo Olama_School_Helpers::translate('Mixed Gender'); ?>
                                </option>
                                <option value="male"><?php echo Olama_School_Helpers::translate('Boys School'); ?></option>
                                <option value="female"><?php echo Olama_School_Helpers::translate('Girls School'); ?>
                                </option>
                            </select>
                            <button type="submit" class="button button-primary"
                                style="flex-shrink: 0;"><?php echo Olama_School_Helpers::translate('Add Location'); ?></button>
                        </div>
                    </form>

                    <div id="olama-locations-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo Olama_School_Helpers::translate('Name'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Area'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Gender'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Time Slot Modal -->
            <div id="olama-slots-modal" class="olama-modal" style="display:none;">
                <div class="olama-modal-content card" style="max-width: 600px; margin: 10% auto; padding: 25px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0;"><?php echo Olama_School_Helpers::translate('Time Slots'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-slot-form" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="slot_label"
                                placeholder="<?php echo Olama_School_Helpers::translate('Slot Label (e.g. Morning Break)'); ?>"
                                required class="widefat">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; align-items: end;">
                            <div>
                                <label
                                    style="font-size: 0.8em;"><?php echo Olama_School_Helpers::translate('Start:'); ?></label>
                                <input type="time" name="start_time" required class="widefat">
                            </div>
                            <div>
                                <label
                                    style="font-size: 0.8em;"><?php echo Olama_School_Helpers::translate('End:'); ?></label>
                                <input type="time" name="end_time" required class="widefat">
                            </div>
                            <button type="submit"
                                class="button button-primary"><?php echo Olama_School_Helpers::translate('Add Slot'); ?></button>
                        </div>
                    </form>

                    <div id="olama-slots-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo Olama_School_Helpers::translate('Label'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Time'); ?></th>
                                    <th><?php echo Olama_School_Helpers::translate('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Shift Assignment Modal (Two Phases) -->
            <div id="olama-assignment-modal" class="olama-modal" style="display:none;">
                <div class="olama-modal-content card" style="max-width: 500px; margin: 5% auto; padding: 25px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0;">
                            <?php echo Olama_School_Helpers::translate('Define Shift & Assign Teachers'); ?>
                        </h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-save-assignment-form">
                        <div class="shift-definition-phase">
                            <div style="margin-bottom: 15px;">
                                <label><strong><?php echo Olama_School_Helpers::translate('Period:'); ?></strong></label>
                                <select name="period_id" required class="widefat" id="olama-modal-period-select">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label><strong><?php echo Olama_School_Helpers::translate('Day of Week:'); ?></strong></label>
                                    <select name="day_of_week" required class="widefat">
                                        <option value="0"><?php echo Olama_School_Helpers::translate('Sunday'); ?></option>
                                        <option value="1"><?php echo Olama_School_Helpers::translate('Monday'); ?></option>
                                        <option value="2"><?php echo Olama_School_Helpers::translate('Tuesday'); ?></option>
                                        <option value="3"><?php echo Olama_School_Helpers::translate('Wednesday'); ?>
                                        </option>
                                        <option value="4"><?php echo Olama_School_Helpers::translate('Thursday'); ?>
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label><strong><?php echo Olama_School_Helpers::translate('Time Slot:'); ?></strong></label>
                                    <select name="slot_id" required class="widefat" id="olama-modal-slot-select">
                                        <!-- Populated via JS -->
                                    </select>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label><strong><?php echo Olama_School_Helpers::translate('Location:'); ?></strong></label>
                                <select name="location_id" required class="widefat" id="olama-modal-location-select">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                        </div>

                        <div class="teacher-assignment-phase"
                            style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                            <label><strong><?php echo Olama_School_Helpers::translate('Assign Teachers:'); ?></strong></label>
                            <div id="olama-teacher-multiselect"
                                style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 5px; border-radius: 4px;">
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <div style="margin-bottom: 5px;">
                                        <label>
                                            <input type="checkbox" name="teacher_ids[]" value="<?php echo $teacher->ID; ?>">
                                            <?php echo esc_html($teacher->display_name); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div style="margin-top: 25px;">
                            <button type="submit"
                                class="button button-primary button-large widefat"><?php echo Olama_School_Helpers::translate('Save Shift & Assignments'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <style>
                .olama-modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    z-index: 100000;
                    overflow: auto;
                }

                .olama-modal-content {
                    background: #fff;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
                    border-radius: 8px;
                    position: relative;
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 25px;
                    min-height: 200px;
                    overflow: visible;
                }

                .olama-modal-close {
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: 20px;
                }
            </style>
        <?php elseif ($active_tab === 'cleaning' && $can_manage_cleaning): ?>
            <?php
            global $wpdb;
            $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'log';
            $config_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'items';
            
            // Manage Setup Access
            $can_configure = current_user_can('manage_options');
            
            if ($view === 'config' && $can_configure): 
                include OLAMA_SCHOOL_PATH . 'includes/admin-views/follow-up-cleaning-config.php';
            else:
                // --- LOG VIEW ---
                $floor_id = isset($_GET['floor_id']) ? intval($_GET['floor_id']) : 0;
                $slot_id = isset($_GET['slot_id']) ? intval($_GET['slot_id']) : 0;
                $cleaning_date = isset($_GET['cleaning_date']) ? sanitize_text_field($_GET['cleaning_date']) : current_time('Y-m-d');
                
                // Fetch dynamic data
                $floors_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors WHERE is_active = 1");
                $slots_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_slots WHERE is_active = 1");
                $items_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_items WHERE is_active = 1");
                
                // If no floor selected, pick first
                if (!$floor_id && !empty($floors_list)) $floor_id = $floors_list[0]->id;
                if (!$slot_id && !empty($slots_list)) $slot_id = $slots_list[0]->id;
                
                $current_floor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors WHERE id = %d", $floor_id));
                $current_slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_cleaning_slots WHERE id = %d", $slot_id));

                // Fetch Auto-assigned cleaner
                $assigned_cleaner = $wpdb->get_row($wpdb->prepare(
                    "SELECT c.* FROM {$wpdb->prefix}olama_cleaning_assignments a 
                    JOIN {$wpdb->prefix}olama_cleaning_cleaners c ON a.cleaner_id = c.id 
                    WHERE a.floor_id = %d", $floor_id
                ));

                // Fetch Existing Log
                $cleaning_log = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}olama_cleaning_logs WHERE floor_id = %d AND cleaning_date = %s AND slot_id = %d",
                    $floor_id, $cleaning_date, $slot_id
                ));
                
                $checkpoints = $cleaning_log ? json_decode($cleaning_log->checkpoints_data, true) : array();
                $logged_staff_name = Olama_School_Helpers::get_user_display_name(get_current_user_id());
                ?>

                <div class="cleaning-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div></div>
                    <?php if ($can_configure): ?>
                        <a href="?page=olama-school-follow-up&tab=cleaning&view=config" class="button">
                            <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php echo Olama_School_Helpers::translate('Configuration'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="cleaning-filters card" style="padding: 20px; margin-bottom: 25px; border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fdfdfd;">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="olama-school-follow-up">
                        <input type="hidden" name="tab" value="cleaning">
                        <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">
                                    <?php echo Olama_School_Helpers::translate('Floor Selection'); ?>
                                </label>
                                <select name="floor_id" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 6px;">
                                    <?php foreach ($floors_list as $f): ?>
                                        <option value="<?php echo $f->id; ?>" <?php selected($floor_id, $f->id); ?>><?php echo esc_html($f->floor_name); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($floors_list)): ?>
                                        <option value=""><?php echo Olama_School_Helpers::translate('Setup floors first'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">
                                    <?php echo Olama_School_Helpers::translate('Checkup Time Slot'); ?>
                                </label>
                                <select name="slot_id" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 6px;">
                                    <?php foreach ($slots_list as $s): ?>
                                        <option value="<?php echo $s->id; ?>" <?php selected($slot_id, $s->id); ?>><?php echo esc_html($s->slot_time); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($slots_list)): ?>
                                        <option value=""><?php echo Olama_School_Helpers::translate('Setup slots first'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 600;">
                                    <?php echo Olama_School_Helpers::translate('Date:'); ?>
                                </label>
                                <input type="date" name="cleaning_date" value="<?php echo esc_attr($cleaning_date); ?>" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 6px;">
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($floor_id && $slot_id): ?>
                    <div class="cleaning-form-container card" style="padding: 30px; border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: #fff;">
                        <form method="post" action="">
                            <?php wp_nonce_field('olama_save_cleaning_log'); ?>
                            <input type="hidden" name="olama_save_cleaning_log" value="1">
                            <input type="hidden" name="academic_year_id" value="<?php echo $active_year->id ?? 0; ?>">
                            <input type="hidden" name="floor_id" value="<?php echo $floor_id; ?>">
                            <input type="hidden" name="floor_name" value="<?php echo esc_attr($current_floor ? $current_floor->floor_name : ''); ?>">
                            <input type="hidden" name="slot_id" value="<?php echo $slot_id; ?>">
                            <input type="hidden" name="slot_time" value="<?php echo esc_attr($current_slot ? $current_slot->slot_time : ''); ?>">
                            <input type="hidden" name="cleaning_date" value="<?php echo esc_attr($cleaning_date); ?>">

                            <div style="text-align: center; border-bottom: 2px solid #eef2f7; padding-bottom: 20px; margin-bottom: 30px;">
                                <h2 style="margin: 0; color: #1e88e5; font-size: 28px; font-weight: 700;">
                                    <?php echo Olama_School_Helpers::translate('Toilet Cleaning Follow-up'); ?>
                                </h2>
                                <div style="margin-top: 15px; display: inline-flex; gap: 10px; background: #f0f7ff; padding: 8px 20px; border-radius: 50px; color: #1e88e5; font-weight: 600;">
                                    <span><i class="dashicons dashicons-location-alt" style="font-size: 16px; margin-top:2px;"></i> <?php echo esc_html($current_floor ? $current_floor->floor_name : ''); ?></span>
                                    <span style="color: #ccc;">|</span>
                                    <span><i class="dashicons dashicons-clock" style="font-size: 16px; margin-top:2px;"></i> <?php echo esc_html($current_slot ? $current_slot->slot_time : ''); ?></span>
                                    <span style="color: #ccc;">|</span>
                                    <span><i class="dashicons dashicons-calendar" style="font-size: 16px; margin-top:2px;"></i> <?php echo $cleaning_date; ?></span>
                                </div>
                            </div>

                            <div style="background: #f8fbff; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #e1e9f1;">
                                <div style="display: flex; gap: 40px; align-items: center;">
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 600;">
                                            <?php echo Olama_School_Helpers::translate('Assigned Cleaner'); ?>
                                        </label>
                                        <input type="text" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->cleaner_name : Olama_School_Helpers::translate('Not Assigned')); ?>" readonly class="regular-text" style="width: 100%; border-color: #d1d9e4; background: #fff;">
                                        <input type="hidden" name="cleaner_id" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->id : 0); ?>">
                                        <input type="hidden" name="cleaner_name" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->cleaner_name : ''); ?>">
                                    </div>
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 8px; color: #333; font-weight: 600;">
                                            <?php echo Olama_School_Helpers::translate('Staff/Signature'); ?> (<?php echo Olama_School_Helpers::translate('Not Editable'); ?>)
                                        </label>
                                        <input type="text" value="<?php echo esc_attr($logged_staff_name); ?>" readonly class="regular-text" style="width: 100%; border-color: #d1d9e4; background: #f9f9f9; color: #666; font-style: italic;">
                                    </div>
                                </div>
                            </div>

                            <div class="cleaning-items-table" style="border: 1px solid #eef2f7; border-radius: 10px; overflow: hidden;">
                                <table class="wp-list-table widefat fixed striped" style="border: none;">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%; padding: 15px; font-weight: 700; background: #fff;"><?php echo Olama_School_Helpers::translate('Item'); ?></th>
                                            <th style="text-align: center; padding: 15px; font-weight: 700; background: #fff;"><?php echo Olama_School_Helpers::translate('Done'); ?></th>
                                            <th style="text-align: center; padding: 15px; font-weight: 700; background: #fff;"><?php echo Olama_School_Helpers::translate('Not Done'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($items_list)): ?>
                                            <tr><td colspan="3" style="text-align: center; padding: 30px; color: #999;"><?php echo Olama_School_Helpers::translate('No cleaning items defined in setup.'); ?></td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($items_list as $item): 
                                            $val = $checkpoints[$item->id] ?? '';
                                        ?>
                                            <tr>
                                                <td style="padding: 18px; font-size: 16px; color: #2c3e50;">
                                                    <span class="dashicons dashicons-clipboard" style="color: #3498db; margin-right: 10px;"></span>
                                                    <?php echo esc_html($item->item_name); ?>
                                                </td>
                                                <td style="text-align: center; padding: 15px;">
                                                    <div class="status-choice done">
                                                        <input type="radio" name="checkpoints[<?php echo $item->id; ?>]" value="done" id="done_<?php echo $item->id; ?>" <?php checked($val, 'done'); ?>>
                                                        <label for="done_<?php echo $item->id; ?>"></label>
                                                    </div>
                                                </td>
                                                <td style="text-align: center; padding: 15px;">
                                                    <div class="status-choice not-done">
                                                        <input type="radio" name="checkpoints[<?php echo $item->id; ?>]" value="not_done" id="ndone_<?php echo $item->id; ?>" <?php checked($val, 'not_done'); ?>>
                                                        <label for="ndone_<?php echo $item->id; ?>"></label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 40px; text-align: center;">
                                <button type="submit" class="button button-primary" style="height: 50px; min-width: 250px; font-size: 18px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 15px rgba(34, 113, 177, 0.3);">
                                    <span class="dashicons dashicons-saved" style="margin-top: 10px;"></span>
                                    <?php echo Olama_School_Helpers::translate('Save Cleaning Log'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <style>
                        .status-choice input { display: none; }
                        .status-choice label { display: inline-block; width: 30px; height: 30px; border-radius: 50%; border: 3px solid #ddd; cursor: pointer; transition: all 0.2s; position: relative; }
                        .status-choice.done label { border-color: #2ecc71; }
                        .status-choice.not-done label { border-color: #e74c3c; }
                        .status-choice.done input:checked + label { background: #2ecc71; }
                        .status-choice.not-done input:checked + label { background: #e74c3c; }
                        .status-choice input:checked + label::after { content: '\f147'; font-family: dashicons; color: #fff; font-size: 20px; line-height: 24px; position: absolute; left: 2px; }
                        .status-choice.not-done input:checked + label::after { content: '\f158'; }
                        .cleaning-items-table tr { transition: background 0.2s; }
                        .cleaning-items-table tr:hover { background: #fcfdfe !important; }
                        [dir="rtl"] .dashicons { margin-right: 0; margin-left: 10px; }
                        [dir="rtl"] .status-choice input:checked + label::after { left: auto; right: 2px; }
                    </style>
                <?php else: ?>
                    <div style="text-align: center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <span class="dashicons dashicons-info" style="font-size: 40px; width: 40px; height: 40px; color: #ccc;"></span>
                        <p style="font-size: 18px; color: #999; margin-top: 15px;">
                            <?php echo Olama_School_Helpers::translate('Please complete the setup to start using the cleaning module.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .attendance-status-selector[value="absent"] {
        background: #ffe6e6;
        border-color: #dc3232;
    }
</style>