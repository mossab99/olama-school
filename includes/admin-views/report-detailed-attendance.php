<?php
/**
 * Detailed Attendance Report Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : current_time('Y-m-d');

$students_list = Olama_School_Student::get_students(array('per_page' => -1));
?>

<div class="wrap olama-school-wrap">
    <h1>
        <?php echo Olama_School_Helpers::translate('Detailed Attendance Report'); ?>
    </h1>

    <div class="card" style="padding: 15px; margin-bottom: 20px; max-width: 100%;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-reports">
            <input type="hidden" name="tab" value="detailed_attendance">

            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label><strong>
                            <?php _e('Student:', 'olama-school'); ?>
                        </strong></label><br>
                    <select name="student_id" class="olama-select2" style="width: 300px;">
                        <option value="">
                            <?php _e('Select Student', 'olama-school'); ?>
                        </option>
                        <?php foreach ($students_list as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($student_id, $s->id); ?>>
                                <?php echo esc_html($s->student_name . ' (' . $s->student_uid . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><strong>
                            <?php _e('From:', 'olama-school'); ?>
                        </strong></label><br>
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                </div>
                <div>
                    <label><strong>
                            <?php _e('To:', 'olama-school'); ?>
                        </strong></label><br>
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                </div>
                <div>
                    <button type="submit" class="button button-secondary">
                        <?php _e('Generate Report', 'olama-school'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($student_id): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <?php _e('Date', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Status', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Reason', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Recorded By', 'olama-school'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance)): ?>
                    <tr>
                        <td colspan="4">
                            <?php _e('No records found for the selected period.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendance as $res):
                        $recorder = get_userdata($res->recorded_by);
                        $recorder_name = $recorder ? $recorder->display_name : __('Unknown', 'olama-school');
                        $status_label = ($res->status === 'present') ? __('Present', 'olama-school') : __('Absent', 'olama-school');
                        $status_color = ($res->status === 'present') ? 'green' : 'red';
                        ?>
                        <tr>
                            <td>
                                <?php echo esc_html($res->attendance_date); ?>
                            </td>
                            <td><span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                    <?php echo esc_html($status_label); ?>
                                </span></td>
                            <td>
                                <?php echo esc_html($res->reason); ?>
                            </td>
                            <td>
                                <?php echo esc_html($recorder_name); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="notice notice-info">
            <p>
                <?php _e('Please select a student and date range to view attendance details.', 'olama-school'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>