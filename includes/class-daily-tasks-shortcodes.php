<?php
/** Authenticated teacher tools, including historical page aliases. */
if (!defined('ABSPATH')) { exit; }

class Olama_School_Daily_Tasks_Shortcodes {
    public function __construct() {
        add_shortcode('olama_logged_user_shifts', array($this, 'shifts'));
        add_shortcode('olama_section_students', array($this, 'students'));
        add_shortcode('student-section-pswd', array($this, 'students'));
        add_shortcode('olama_exam_halls_attendance', array($this, 'halls'));
    }

    public function shifts($atts = array()) {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please log in to view your shifts.', 'olama-school') . '</p>'; }
        if (!class_exists('Olama_School_Shifts')) { return '<p>' . esc_html__('Activate Olama Employees to view shifts.', 'olama-school') . '</p>'; }
        $rows = Olama_School_Shifts::get_teacher_weekly_shifts(get_current_user_id());
        $html = '<h2>' . esc_html__('Teacher Shifts', 'olama-school') . '</h2>';
        if (!$rows) { return $html . '<p>' . esc_html(Olama_School_Helpers::translate('No shifts assigned for this week.')) . '</p>'; }
        $html .= '<table class="widefat striped"><thead><tr>';
        foreach (array('Day', 'Location', 'Time Slot', 'Start Time', 'End Time') as $label) { $html .= '<th>' . esc_html__($label, 'olama-school') . '</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
            $row = clone $row;
            $row->day_of_week = Olama_School_Helpers::translate($days[(int) $row->day_of_week] ?? (string) $row->day_of_week);
            $html .= '<tr>';
            foreach (array('day_of_week', 'location_name', 'slot_label', 'start_time', 'end_time') as $field) { $html .= '<td>' . esc_html($row->$field) . '</td>'; }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    public function halls($atts = array()) {
        if (!is_user_logged_in() || !Olama_School_Permissions::can('olama_access_exam_halls') || !Olama_School_Permissions::can('olama_manage_hall_attendance')) {
            return '<p>' . esc_html__('You do not have permission to record exam hall attendance.', 'olama-school') . '</p>';
        }
        if (!class_exists('Olama_Exam_Management_Admin')) { return '<p>' . esc_html__('Activate Olama Exam Management to record exam hall attendance.', 'olama-school') . '</p>'; }
        return '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=olama-exam-halls&tab=attendance')) . '">' . esc_html__('Open Exam Halls Attendance', 'olama-school') . '</a></p>';
    }

    public function students($atts = array()) {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please log in to view students.', 'olama-school') . '</p>'; }
        $atts = shortcode_atts(array('grade' => 0, 'section' => 0), $atts, 'olama_section_students');
        $year = Olama_School_Academic::get_active_year();
        $sections = $year ? Olama_School_Section::get_sections_by_year($year->id) : array();
        if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_academic_assignment')) {
            $assigned = $year ? Olama_School_Teacher::get_all_assignments(get_current_user_id(), $year->id) : array();
            $ids = array_map('intval', wp_list_pluck($assigned, 'section_id'));
            $sections = array_filter($sections, function($section) use ($ids) { return in_array((int) $section->id, $ids, true); });
        }
        $sections = array_values(array_filter($sections, function($section) use ($atts) {
            return (!$atts['grade'] || (int) $section->grade_id === absint($atts['grade'])) && (!$atts['section'] || (int) $section->id === absint($atts['section']));
        }));
        $selected = isset($_GET['students_section']) ? absint($_GET['students_section']) : (int) ($sections[0]->id ?? 0);
        if (!in_array($selected, array_map('intval', wp_list_pluck($sections, 'id')), true)) { $selected = 0; }
        ob_start();
        ?>
        <h2><?php esc_html_e('Grade / Section Students', 'olama-school'); ?></h2>
        <form method="get">
            <?php if (is_admin()): ?><input type="hidden" name="page" value="olama-dashboard"><input type="hidden" name="view" value="students"><?php endif; ?>
            <label for="olama-students-section"><?php esc_html_e('Grade / Section', 'olama-school'); ?></label>
            <select id="olama-students-section" name="students_section">
                <?php foreach ($sections as $section): ?>
                <option value="<?php echo esc_attr($section->id); ?>" <?php selected($selected, $section->id); ?>><?php echo esc_html($section->grade_name . ' / ' . $section->section_name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php esc_html_e('View Students', 'olama-school'); ?></button>
        </form>
        <table class="widefat striped"><thead><tr><th><?php esc_html_e('Student Full Name', 'olama-school'); ?></th><th><?php esc_html_e('Family ID', 'olama-school'); ?></th><th><?php esc_html_e('Mother Mobile #', 'olama-school'); ?></th></tr></thead><tbody>
        <?php
        $students = $selected ? Olama_School_Student::get_students(array('academic_year_id' => $year->id, 'section_id' => $selected)) : array();
        global $wpdb;
        $phones = array();
        $family_ids = array();
        if ($students && function_exists('olama_core')) {
            $families = olama_core()->read_models()->table('families');
            $uids = array_unique(wp_list_pluck($students, 'family_id'));
            $placeholders = implode(',', array_fill(0, count($uids), '%s'));
            foreach ($wpdb->get_results($wpdb->prepare("SELECT family_uid, oracle_family_id, mother_mobile FROM {$families} WHERE family_uid IN ($placeholders)", array_values($uids))) as $family) { $phones[$family->family_uid] = $family->mother_mobile; $family_ids[$family->family_uid] = $family->oracle_family_id; }
        }
        foreach ($students as $student): ?>
        <tr><td><?php echo esc_html($student->student_name); ?></td><td dir="ltr"><?php echo esc_html($family_ids[$student->family_id] ?? ''); ?></td><td dir="ltr"><?php echo esc_html($phones[$student->family_id] ?? ''); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="3"><?php esc_html_e('No students found in your assigned sections.', 'olama-school'); ?></td></tr><?php endif; ?>
        </tbody></table>
        <?php return ob_get_clean();
    }
}
