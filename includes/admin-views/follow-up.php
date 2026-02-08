<?php
/**
 * Follow Up Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

$can_manage_attendance = Olama_School_Permissions::can('olama_manage_attendance');
$can_manage_shifts = Olama_School_Permissions::can('olama_manage_shifts');

if (!$can_manage_attendance && !$can_manage_shifts) {
    wp_die(__('Unauthorized', 'olama-school'));
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : ($can_manage_attendance ? 'student_attendance' : 'employee_shifts');

// Validate active tab access
if ($active_tab === 'student_attendance' && !$can_manage_attendance) {
    $active_tab = 'employee_shifts';
} elseif ($active_tab === 'employee_shifts' && !$can_manage_shifts) {
    $active_tab = 'student_attendance';
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
        <?php endif; ?>
    </div>
</div>

<style>
    .attendance-status-selector[value="absent"] {
        background: #ffe6e6;
        border-color: #dc3232;
    }
</style>