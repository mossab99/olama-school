<?php
/** Standalone regression: php tests/weekly-plan-exam-shortcode.php */
define('ABSPATH', __DIR__);
function absint($value) { return abs((int) $value); }
function shortcode_atts($defaults, $values, $name = '') { return array_merge($defaults, $values); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function date_i18n($format, $time) { return date($format, $time); }
function home_url($path) { return 'https://school.test' . $path; }
function get_posts($args) { return array(); }
function apply_filters($name, $value) { return $value; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
class Olama_School_Academic {
    public static function get_year($id) { return (object) array('id' => $id, 'year_name' => '2026-2027'); }
    public static function get_semester($id) { return (object) array('id' => $id, 'academic_year_id' => 2); }
    public static function get_academic_weeks(...$args) { return array('2026-09-27' => array('number' => 5)); }
}
class Olama_School_Section {
    public static function get_section($id) { return (object) array('id' => $id, 'grade_id' => 23, 'academic_year_id' => 2, 'section_name' => 'أ'); }
}
class Olama_School_Grade {
    public static function get_grade($id) { return (object) array('grade_name' => 'أول ثانوي'); }
}
class Olama_School_Permissions {
    public static function can($cap) { return false; }
}
class Olama_School_Helpers {
    public static function translate($text) { return $text; }
    public static function get_week_range($date) { return array('start' => '2026-09-27', 'end' => '2026-10-01'); }
    public static function get_school_days() { return array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'); }
    public static function format_date($date, $unused, $format) { return date($format, strtotime($date)); }
    public static function get_subject_palette($color) { return array('background' => '#fff', 'border' => '#ccc', 'text' => '#333'); }
}
class Report_DB {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $selected = array();
    public $plans = array();
    public function prepare($sql, ...$values) { return vsprintf(str_replace('%d', '%s', $sql), $values); }
    public function get_results($sql) { return $this->plans; }
    public function get_col($sql) {
        preg_match('/plan_id = (\d+)/', $sql, $match);
        return $this->selected[(int) $match[1]] ?? array();
    }
}
class Olama_Exam_Manager {
    public static $rows = array();
    public static function get_exams($filters) {
        return array_values(array_filter(self::$rows, static function ($row) use ($filters) {
            foreach ($filters as $key => $value) { if ($row->$key != $value) { return false; } }
            return true;
        }));
    }
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
require dirname(__DIR__) . '/includes/class-plan.php';
require dirname(__DIR__) . '/includes/class-shortcodes.php';
$wpdb = new Report_DB();
$plan = (object) array('id' => 10, 'academic_year_id' => 2, 'semester_id' => 3, 'section_id' => 104, 'subject_id' => 20, 'lesson_id' => 100, 'plan_date' => '2026-09-28', 'plan_type' => 'review', 'status' => 'approved', 'subject_name' => 'تاريخ الأردن', 'unit_name' => 'العصور الإسلامية', 'lesson_title' => 'صدر الإسلام', 'teacher_notes' => '', 'homework_sb' => '', 'homework_eb' => '', 'homework_nb' => '', 'homework_ws' => '');
$exam = (object) array('id' => 703, 'academic_year_id' => 2, 'semester_id' => 3, 'section_id' => 104, 'subject_id' => 20, 'direct_lesson_id' => 100, 'exam_type' => 'direct', 'is_placement' => 0, 'status' => 'active', 'title' => 'اختبار <صدر الإسلام>');
Olama_Exam_Manager::$rows = array($exam);
$wpdb->plans = array($plan);
$shortcode = (new ReflectionClass('Olama_School_Shortcodes'))->newInstanceWithoutConstructor();
$atts = array('year' => 2, 'semester' => 3, 'grade' => 23, 'section' => 104, 'week' => '2026-09-27');
$html = $shortcode->render_weekly_plan_shortcode($atts);
check(strpos($html, 'exam_id=703') !== false, 'Older approved review plans must resolve the exam automatically.');
check(strpos($html, '&lt;صدر الإسلام&gt;') !== false, 'Exam titles must be escaped.');
check(strpos($html, 'يتطلب تسجيل الدخول') !== false, 'The report explains that login is required.');
$wpdb->selected[10] = array('703');
check(strpos($shortcode->render_weekly_plan_shortcode($atts), 'exam_id=703') !== false, 'Explicit selections must render through the real shortcode.');
$other = clone $exam; $other->id = 704; $other->title = 'Other';
Olama_Exam_Manager::$rows[] = $other;
check(count(Olama_School_Plan::get_report_exams($plan)) === 1, 'Explicit selections must not include other exams.');
$exam->status = 'closed';
check(array() === Olama_School_Plan::get_report_exams($plan), 'Closed selections must not be replaced by an unselected exam.');
$wpdb->selected = array();
Olama_Exam_Manager::$rows = array($other);
foreach (array('section_id' => 105, 'semester_id' => 4, 'academic_year_id' => 1, 'subject_id' => 21, 'direct_lesson_id' => 101) as $key => $wrong) {
    $original = $other->$key; $other->$key = $wrong;
    check(array() === Olama_School_Plan::get_report_exams($plan), 'Never link exams outside plan scope: ' . $key);
    $other->$key = $original;
}
$plan->plan_type = 'homework';
$plan->homework_sb = 'Page 10';
check(strpos($shortcode->render_weekly_plan_shortcode($atts), 'exam_id=704') !== false, 'Homework plans must also show the exam section.');
$plan->status = 'draft';
check(strpos($shortcode->render_weekly_plan_shortcode($atts), 'exam_id=') === false, 'Parents must not see exams from unapproved plans.');
echo "Weekly plan exam shortcode regression passed.\n";
