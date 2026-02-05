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

        <?php elseif ($active_tab === 'employee_shifts'): ?>
            <div class="card" style="padding: 40px; text-align: center;">
                <span class="dashicons dashicons-clock"
                    style="font-size: 60px; width: 60px; height: 60px; color: #ccc;"></span>
                <h2>
                    <?php _e('Employee Shifts ‐ Coming Soon', 'olama-school'); ?>
                </h2>
                <p>
                    <?php _e('The Employee Shifts management feature is currently under development.', 'olama-school'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .attendance-status-selector[value="absent"] {
        background: #ffe6e6;
        border-color: #dc3232;
    }
</style>