<?php
/** Frontend attendance using the Student Evaluation attendance tables. */
if (!defined('ABSPATH')) { exit; }

class Olama_School_Attendance_Shortcode
{
    public function __construct()
    {
        add_shortcode('olama_attendance', array($this, 'render'));
        add_action('template_redirect', array($this, 'save'));
        add_action('admin_init', array($this, 'save'));
        add_action('wp_ajax_olama_frontend_attendance_save', array($this, 'save'));
    }

    private function available()
    {
        return defined('OLAMA_STUDENT_EVALUATION_PATH') && class_exists('Olama_Student_Evaluation_DB');
    }

    private function sections($year_id)
    {
        $sections = Olama_School_Section::get_sections_by_year($year_id);
        if (current_user_can('manage_options') || Olama_School_Permissions::can('olama_manage_academic_assignment')) {
            return $sections;
        }
        $assignments = Olama_School_Teacher::get_all_assignments(get_current_user_id(), $year_id);
        $ids = array_map('intval', wp_list_pluck($assignments, 'section_id'));
        return array_values(array_filter($sections, function ($section) use ($ids) {
            return in_array((int) $section->id, $ids, true);
        }));
    }

    public function render($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html(Olama_School_Helpers::translate('Please log in to mark attendance.')) . '</p>';
        }
        if (!Olama_School_Permissions::can('olama_manage_attendance')) {
            return '<p>' . esc_html__('You do not have permission to record attendance.', 'olama-school') . '</p>';
        }
        if (!$this->available()) {
            return '<p>' . esc_html__('Activate Olama Student Evaluation to record attendance.', 'olama-school') . '</p>';
        }
        $atts = shortcode_atts(array('grade' => 0, 'section' => 0), $atts, 'olama_attendance');
        $active_year = Olama_School_Academic::get_active_year();
        $active_semester = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;
        $allowed_sections = $active_year ? $this->sections($active_year->id) : array();
        $allowed_sections = array_values(array_filter($allowed_sections, function ($section) use ($atts) {
            return (!$atts['grade'] || (int) $section->grade_id === absint($atts['grade']))
                && (!$atts['section'] || (int) $section->id === absint($atts['section']));
        }));
        $grade_id = isset($_GET['grade_id']) ? absint($_GET['grade_id']) : absint($atts['grade']);
        $section_id = isset($_GET['section_id']) ? absint($_GET['section_id']) : absint($atts['section']);
        if (!$section_id && !isset($_GET['section_id']) && !empty($allowed_sections)) {
            $section_id = (int) $allowed_sections[0]->id;
            $grade_id = (int) $allowed_sections[0]->grade_id;
        }
        if (!$grade_id && $section_id) {
            foreach ($allowed_sections as $section) {
                if ((int) $section->id === $section_id) { $grade_id = (int) $section->grade_id; break; }
            }
        }
        $sections = array_values(array_filter($allowed_sections, function ($section) use ($grade_id) {
            return (int) $section->grade_id === $grade_id;
        }));
        if (!in_array($section_id, array_map('intval', wp_list_pluck($sections, 'id')), true)) {
            $section_id = 0;
        }
        $grade_ids = array_map('intval', wp_list_pluck($allowed_sections, 'grade_id'));
        $grades = array_values(array_filter(Olama_School_Grade::get_grades(), function ($grade) use ($grade_ids) {
            return in_array((int) $grade->id, $grade_ids, true);
        }));
        $attendance_date = isset($_GET['attendance_date'])
            ? Olama_School_Helpers::sanitize_date(wp_unslash($_GET['attendance_date'])) : current_time('Y-m-d');
        $attendance_date = $attendance_date ?: current_time('Y-m-d');
        $students = $section_id && $active_semester ? Olama_School_Student::get_students(array(
            'academic_year_id' => $active_year->id, 'section_id' => $section_id,
        )) : array();
        $attendance_records = array();
        if ($students) {
            global $wpdb;
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT student_uid, status, reason FROM {$wpdb->prefix}olama_attendance WHERE academic_year_id = %d AND section_id = %d AND attendance_date = %s",
                $active_year->id, $section_id, $attendance_date
            ));
            foreach ($records as $record) { $attendance_records[$record->student_uid] = $record; }
        }
        ob_start();
        include __DIR__ . '/frontend-views/attendance.php';
        return ob_get_clean();
    }

    public function save()
    {
        if (!isset($_POST['olama_frontend_attendance'])) { return; }
        if (!$this->available() || !Olama_School_Permissions::can('olama_manage_attendance')) {
            wp_die(esc_html__('Unauthorized', 'olama-school'), '', array('response' => 403));
        }
        check_admin_referer('olama_frontend_attendance');
        $year = Olama_School_Academic::get_active_year();
        $semester = $year ? Olama_School_Academic::get_active_semester($year->id) : null;
        $section_id = absint($_POST['section_id'] ?? 0);
        $date = Olama_School_Helpers::sanitize_date(wp_unslash($_POST['attendance_date'] ?? ''));
        $ids = $year ? array_map('intval', wp_list_pluck($this->sections($year->id), 'id')) : array();
        if (!$semester || !$date || !in_array($section_id, $ids, true)) {
            wp_die(esc_html__('Invalid attendance section or date.', 'olama-school'), '', array('response' => 400));
        }
        $students = Olama_School_Student::get_students(array('academic_year_id' => $year->id, 'section_id' => $section_id));
        $data = wp_unslash($_POST['attendance'] ?? array());
        if (!$students || !is_array($data)) {
            wp_die(esc_html__('No students found in this section.', 'olama-school'));
        }
        global $wpdb;
        foreach ($students as $student) {
            $row = $data[$student->id] ?? null;
            if (!is_array($row) || !in_array($row['status'] ?? '', array('present', 'absent'), true)) {
                wp_die(esc_html__('Invalid attendance status.', 'olama-school'), '', array('response' => 400));
            }
        }
        foreach ($students as $student) {
            $row = $data[$student->id];
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}olama_attendance (student_id, student_uid, academic_year_id, semester_id, section_id, attendance_date, status, reason, recorded_by)
                 VALUES (%d, %s, %d, %d, %d, %s, %s, %s, %d)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason), recorded_by = VALUES(recorded_by)",
                $student->id, $student->student_uid, $year->id, $semester->id, $section_id, $date,
                $row['status'], sanitize_text_field($row['reason'] ?? ''), get_current_user_id()
            ));
            if (false === $result) { wp_die(esc_html__('Attendance could not be saved. Please try again.', 'olama-school')); }
        }
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
             VALUES (%d, %d, %s, %d, 'completed') ON DUPLICATE KEY UPDATE recorded_by = VALUES(recorded_by), status = 'completed'",
            $year->id, $section_id, $date, get_current_user_id()
        ));
        if (false === $result) { wp_die(esc_html__('Attendance could not be saved. Please try again.', 'olama-school')); }
        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => Olama_School_Helpers::translate('Attendance saved successfully.')));
        }
        wp_safe_redirect(add_query_arg('message', 'attendance_saved', wp_get_referer() ?: home_url('/')));
        exit;
    }
}
