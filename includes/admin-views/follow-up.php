<?php
/**
 * Follow Up Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'student_attendance';
$grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$attendance_date = isset($_GET['attendance_date']) ? sanitize_text_field($_GET['attendance_date']) : current_time('Y-m-d');

$active_year = Olama_School_Academic::get_active_year();
$active_sem = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;

$grades = Olama_School_Grade::get_grades();
$sections = $grade_id ? Olama_School_Section::get_by_grade($grade_id, $active_year->id) : array();

$students = array();
$attendance_records = array();

if ($section_id && $active_year) {
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
                <?php _e('Attendance saved successfully.', 'olama-school'); ?>
            </p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=olama-school-follow-up&tab=student_attendance"
            class="nav-tab <?php echo $active_tab === 'student_attendance' ? 'nav-tab-active' : ''; ?>">
            <?php echo Olama_School_Helpers::translate('Student Attendance'); ?>
        </a>
        <a href="?page=olama-school-follow-up&tab=employee_shifts"
            class="nav-tab <?php echo $active_tab === 'employee_shifts' ? 'nav-tab-active' : ''; ?>">
            <?php echo Olama_School_Helpers::translate('Employee Shifts'); ?>
        </a>
    </h2>

    <div class="olama-tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'student_attendance'): ?>
            <div class="attendance-filters card" style="padding: 15px; margin-bottom: 20px; max-width: 100%;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="olama-school-follow-up">
                    <input type="hidden" name="tab" value="student_attendance">

                    <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div>
                            <label><strong>
                                    <?php _e('Academic Year:', 'olama-school'); ?>
                                </strong></label><br>
                            <input type="text" value="<?php echo esc_attr($active_year->year_name ?? ''); ?>" readonly
                                disabled class="regular-text" style="width: 150px;">
                        </div>
                        <div>
                            <label><strong>
                                    <?php _e('Semester:', 'olama-school'); ?>
                                </strong></label><br>
                            <input type="text" value="<?php echo esc_attr($active_sem->semester_name ?? ''); ?>" readonly
                                disabled class="regular-text" style="width: 150px;">
                        </div>
                        <div>
                            <label><strong>
                                    <?php _e('Grade:', 'olama-school'); ?>
                                </strong></label><br>
                            <select name="grade_id" onchange="this.form.submit()">
                                <option value="">
                                    <?php _e('Select Grade', 'olama-school'); ?>
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
                                    <?php _e('Section:', 'olama-school'); ?>
                                </strong></label><br>
                            <select name="section_id" onchange="this.form.submit()" <?php echo empty($sections) ? 'disabled' : ''; ?>>
                                <option value="">
                                    <?php _e('Select Section', 'olama-school'); ?>
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
                                    <?php _e('Date:', 'olama-school'); ?>
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
                                    <?php _e('ID', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Student Name', 'olama-school'); ?>
                                </th>
                                <th width="150">
                                    <?php _e('Status', 'olama-school'); ?>
                                </th>
                                <th>
                                    <?php _e('Reason (if absent)', 'olama-school'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="4">
                                        <?php _e('No students found in this section.', 'olama-school'); ?>
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
                                                    <?php _e('Present', 'olama-school'); ?>
                                                </option>
                                                <option value="absent" <?php selected($status, 'absent'); ?> style="color: red;
                                    font-weight: bold;">
                                                    <?php _e('Absent', 'olama-school'); ?>
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="attendance[<?php echo $stu->id; ?>][reason]"
                                                value="<?php echo esc_attr($reason); ?>" class="large-text"
                                                placeholder="<?php _e('Reason...', 'olama-school'); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php _e('Save Attendance', 'olama-school'); ?>
                        </button>
                    </p>
                </form>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>
                        <?php _e('Please select a Grade and Section to load students.', 'olama-school'); ?>
                    </p>
                </div>
            <?php endif; ?>

        <?php elseif ($active_tab === 'employee_shifts'):
            $all_teachers = get_users(array('role__in' => array('administrator', 'editor', 'author', 'teacher')));
            ?>
            <div class="olama-shifts-container">
                <div class="olama-shifts-header"
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <button class="button button-secondary" id="olama-manage-periods-btn">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php _e('Manage Periods', 'olama-school'); ?>
                        </button>
                        <button class="button button-secondary" id="olama-manage-locations-btn">
                            <span class="dashicons dashicons-location-alt"></span>
                            <?php _e('Manage Locations', 'olama-school'); ?>
                        </button>
                        <button class="button button-secondary" id="olama-manage-slots-btn">
                            <span class="dashicons dashicons-clock"></span> <?php _e('Time Slots', 'olama-school'); ?>
                        </button>
                    </div>
                    <div>
                        <button class="button button-primary" id="olama-add-shift-btn">
                            <span class="dashicons dashicons-plus"></span> <?php _e('Define Shift', 'olama-school'); ?>
                        </button>
                    </div>
                </div>

                <div class="card olama-shifts-controls" style="padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; gap: 15px; align-items: flex-end;">
                        <div>
                            <label><strong><?php _e('Select Period:', 'olama-school'); ?></strong></label><br>
                            <select id="olama-shift-period-select" class="regular-text">
                                <option value=""><?php _e('Select Period', 'olama-school'); ?></option>
                                <!-- Populated via JS -->
                            </select>
                        </div>
                    </div>
                </div>

                <div id="olama-shifts-grid-container">
                    <div class="olama-loading-overlay" style="display:none; text-align:center; padding: 20px;">
                        <span class="spinner is-active" style="float:none;"></span>
                        <?php _e('Loading shifts...', 'olama-school'); ?>
                    </div>
                    <div id="olama-shifts-grid"></div>
                </div>
            </div>

            <!-- Periods Management Modal -->
            <div id="olama-periods-modal" class="olama-modal" style="display:none;">
                <div class="olama-modal-content card" style="max-width: 600px; margin: 10% auto; padding: 25px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0;"><?php _e('Manage Shift Periods', 'olama-school'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-period-form" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; align-items: end;">
                            <div>
                                <label style="font-size: 0.8em;"><?php _e('Year:', 'olama-school'); ?></label>
                                <select name="academic_year_id" class="widefat" required>
                                    <?php
                                    $years = Olama_School_Academic::get_academic_years();
                                    foreach ($years as $y)
                                        echo '<option value="' . $y->id . '" ' . selected($active_year->id, $y->id, false) . '>' . $y->year_name . '</option>';
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.8em;"><?php _e('Semester:', 'olama-school'); ?></label>
                                <select name="semester_id" class="widefat" required>
                                    <?php
                                    $sems = Olama_School_Academic::get_semesters($active_year->id);
                                    foreach ($sems as $s)
                                        echo '<option value="' . $s->id . '" ' . selected($active_sem->id, $s->id, false) . '>' . $s->semester_name . '</option>';
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.8em;"><?php _e('Type:', 'olama-school'); ?></label>
                                <input type="text" name="shift_type"
                                    placeholder="<?php _e('Morning, Evening...', 'olama-school'); ?>" required
                                    class="widefat">
                            </div>
                            <button type="submit" class="button button-primary"><?php _e('Add', 'olama-school'); ?></button>
                        </div>
                    </form>

                    <div id="olama-periods-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'olama-school'); ?></th>
                                    <th><?php _e('Semester', 'olama-school'); ?></th>
                                    <th><?php _e('Actions', 'olama-school'); ?></th>
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
                        <h3 style="margin:0;"><?php _e('Manage Locations', 'olama-school'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-location-form"
                        style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="location_name"
                                placeholder="<?php _e('Location Name (e.g. Playground)', 'olama-school'); ?>" required
                                class="regular-text" style="flex:2;">
                            <input type="text" name="area_floor" placeholder="<?php _e('Area/Floor', 'olama-school'); ?>"
                                class="regular-text" style="flex:1;">
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="gender" class="regular-text">
                                <option value="mixed"><?php _e('Mixed Gender', 'olama-school'); ?></option>
                                <option value="male"><?php _e('Boys School', 'olama-school'); ?></option>
                                <option value="female"><?php _e('Girls School', 'olama-school'); ?></option>
                            </select>
                            <button type="submit" class="button button-primary"
                                style="flex-shrink: 0;"><?php _e('Add Location', 'olama-school'); ?></button>
                        </div>
                    </form>

                    <div id="olama-locations-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'olama-school'); ?></th>
                                    <th><?php _e('Area', 'olama-school'); ?></th>
                                    <th><?php _e('Gender', 'olama-school'); ?></th>
                                    <th><?php _e('Actions', 'olama-school'); ?></th>
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
                        <h3 style="margin:0;"><?php _e('Time Slots', 'olama-school'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-add-slot-form" style="margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="slot_label"
                                placeholder="<?php _e('Slot Label (e.g. Morning Break)', 'olama-school'); ?>" required
                                class="widefat">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; align-items: end;">
                            <div>
                                <label style="font-size: 0.8em;"><?php _e('Start:', 'olama-school'); ?></label>
                                <input type="time" name="start_time" required class="widefat">
                            </div>
                            <div>
                                <label style="font-size: 0.8em;"><?php _e('End:', 'olama-school'); ?></label>
                                <input type="time" name="end_time" required class="widefat">
                            </div>
                            <button type="submit"
                                class="button button-primary"><?php _e('Add Slot', 'olama-school'); ?></button>
                        </div>
                    </form>

                    <div id="olama-slots-list">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Label', 'olama-school'); ?></th>
                                    <th><?php _e('Time', 'olama-school'); ?></th>
                                    <th><?php _e('Actions', 'olama-school'); ?></th>
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
                        <h3 style="margin:0;"><?php _e('Define Shift & Assign Teachers', 'olama-school'); ?></h3>
                        <button class="olama-modal-close" style="background:none; border:none; cursor:pointer;"><span
                                class="dashicons dashicons-no-alt"></span></button>
                    </div>

                    <form id="olama-save-assignment-form">
                        <div class="shift-definition-phase">
                            <div style="margin-bottom: 15px;">
                                <label><strong><?php _e('Period:', 'olama-school'); ?></strong></label>
                                <select name="period_id" required class="widefat" id="olama-modal-period-select">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label><strong><?php _e('Day of Week:', 'olama-school'); ?></strong></label>
                                    <select name="day_of_week" required class="widefat">
                                        <option value="0"><?php _e('Sunday', 'olama-school'); ?></option>
                                        <option value="1"><?php _e('Monday', 'olama-school'); ?></option>
                                        <option value="2"><?php _e('Tuesday', 'olama-school'); ?></option>
                                        <option value="3"><?php _e('Wednesday', 'olama-school'); ?></option>
                                        <option value="4"><?php _e('Thursday', 'olama-school'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label><strong><?php _e('Time Slot:', 'olama-school'); ?></strong></label>
                                    <select name="slot_id" required class="widefat" id="olama-modal-slot-select">
                                        <!-- Populated via JS -->
                                    </select>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label><strong><?php _e('Location:', 'olama-school'); ?></strong></label>
                                <select name="location_id" required class="widefat" id="olama-modal-location-select">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                        </div>

                        <div class="teacher-assignment-phase"
                            style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                            <label><strong><?php _e('Assign Teachers:', 'olama-school'); ?></strong></label>
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
                                class="button button-primary button-large widefat"><?php _e('Save Shift & Assignments', 'olama-school'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <style>
                .olama-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 10000;
                }

                .olama-modal-content {
                    background: #fff;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                    border-radius: 8px;
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