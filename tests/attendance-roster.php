<?php
/** Standalone regression: php tests/attendance-roster.php (requires pdo_sqlite). */
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('OLAMA_SCHOOL_PATH', dirname(__DIR__) . '/');
define('OLAMA_SCHOOL_URL', 'https://example.test/olama-school/');
define('OLAMA_STUDENT_EVALUATION_PATH', dirname(__DIR__, 2) . '/olama-student-evaluation/');

function expect_roster($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

// Only this in-memory database is used; WordPress and the site's database are never loaded.
class Attendance_Test_DB {
    public $prefix = 'wp_';
    public $db;
    public function __construct() {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function prepare($sql, ...$values) {
        if (count($values) === 1 && is_array($values[0])) { $values = $values[0]; }
        return preg_replace_callback('/%[ds]/', function ($match) use (&$values) {
            $value = array_shift($values);
            return $match[0] === '%d' ? (string) (int) $value : $this->db->quote((string) $value);
        }, $sql);
    }
    public function get_results($sql) { return $this->db->query($sql)->fetchAll(PDO::FETCH_OBJ); }
    public function get_var($sql) {
        $value = $this->db->query($sql)->fetchColumn();
        return $value === false ? null : $value;
    }
    public function query($sql) {
        // SQLite's equivalent of the MySQL upsert used by the actual save handler.
        $sql = str_replace('ON DUPLICATE KEY UPDATE', 'ON CONFLICT DO UPDATE SET', $sql);
        $sql = preg_replace('/VALUES\((\w+)\)/', 'excluded.$1', $sql);
        return $this->db->exec($sql);
    }
}
$wpdb = new Attendance_Test_DB();
$wpdb->query('CREATE TABLE core_students (
    id INTEGER PRIMARY KEY, student_uid TEXT, student_name TEXT, family_uid TEXT,
    birth_date TEXT, student_national_no TEXT, student_gender_name TEXT, student_gender TEXT,
    student_status TEXT, student_status_name TEXT
)');
$wpdb->query('CREATE TABLE core_student_years (
    id INTEGER PRIMARY KEY, student_uid TEXT, study_year TEXT, class_id INTEGER,
    section_id INTEGER, student_status TEXT, student_status_name TEXT
)');
$wpdb->query('CREATE TABLE core_families (family_uid TEXT, sponsor_full_name TEXT, father_name TEXT)');
$wpdb->query('CREATE TABLE wp_olama_academic_years (id INTEGER PRIMARY KEY, year_name TEXT)');
$wpdb->query('CREATE TABLE wp_olama_sections (
    id INTEGER PRIMARY KEY, grade_id INTEGER, section_name TEXT, core_study_year TEXT,
    core_grade_id INTEGER, core_section_id INTEGER
)');
$wpdb->query('CREATE TABLE wp_olama_grades (id INTEGER PRIMARY KEY, grade_name TEXT)');
$wpdb->query('CREATE TABLE wp_olama_attendance (
    student_id INTEGER, student_uid TEXT, academic_year_id INTEGER, semester_id INTEGER,
    section_id INTEGER, attendance_date TEXT, status TEXT, reason TEXT, recorded_by INTEGER,
    UNIQUE(student_uid, attendance_date)
)');
$wpdb->query('CREATE TABLE wp_olama_attendance_sheets (
    academic_year_id INTEGER, section_id INTEGER, attendance_date TEXT, recorded_by INTEGER,
    status TEXT, UNIQUE(section_id, attendance_date)
)');
$wpdb->query("INSERT INTO wp_olama_academic_years VALUES (1, '2026-2027')");
$wpdb->query("INSERT INTO wp_olama_grades VALUES (10, 'First Grade')");
$wpdb->query("INSERT INTO wp_olama_sections VALUES (99, 10, 'A', '2026-2027', 10, 20)");
$wpdb->query("INSERT INTO wp_olama_sections VALUES (100, 10, 'B', '2026-2027', 10, 21)");

// The selected year's enrollment takes precedence over the registry's older status.
$fixtures = array(
    // id, name, registry status/name, year status/name, core section
    array(1, 'Continuing Student', '1', 'مستمر', '1', 'مستمر', 20),
    array(2, 'Withdrawn By Code', '1', 'مستمر', '2', '', 20),
    array(3, 'Withdrawn Arabic Status', '1', 'مستمر', 'منسحب', '', 20),
    array(4, 'طالبة اختبار منسحبة', '1', 'مستمر', '1', 'غير مستمر', 20),
    array(5, 'Withdrawn English Status', '1', 'مستمر', ' WithdrawN ', '', 20),
    array(6, 'Withdrawn Registry Fallback', '2', 'غير مستمر', '', '', 20),
    array(7, 'Continuing This Year', '2', 'غير مستمر', '1', 'مستمر', 20),
    array(8, 'Different Section', '1', 'مستمر', '1', 'مستمر', 21),
);
foreach ($fixtures as $fixture) {
    list($id, $name, $registry, $registry_name, $year_status, $year_name, $section) = $fixture;
    $wpdb->query($wpdb->prepare(
        'INSERT INTO core_students (id, student_uid, student_name, student_status, student_status_name) VALUES (%d, %s, %s, %s, %s)',
        $id, 'student-' . $id, $name, $registry, $registry_name
    ));
    $wpdb->query($wpdb->prepare(
        'INSERT INTO core_student_years VALUES (%d, %s, %s, %d, %d, %s, %s)',
        $id, 'student-' . $id, '2026-2027', 10, $section, $year_status, $year_name
    ));
}
$wpdb->query("INSERT INTO wp_olama_attendance VALUES (1, 'student-1', 1, 2, 99, '2026-10-26', 'absent', 'Existing absence', 9)");
$wpdb->query("INSERT INTO wp_olama_attendance VALUES (2, 'student-2', 1, 2, 99, '2026-10-26', 'absent', 'Historical withdrawn mark', 9)");

class Attendance_Test_Core {
    public function read_models() { return $this; }
    public function available($table) { return true; }
    public function table($table) { return 'core_' . $table; }
}
function olama_core() { static $core; return $core ?: ($core = new Attendance_Test_Core()); }
class Olama_School_Academic_Bridge {
    public static function get_study_year($id) { return '2026-2027'; }
    public static function sync($year) {}
}
class Olama_School_Academic {
    public static function get_active_year() { return (object) array('id' => 1, 'year_name' => '2026-2027'); }
    public static function get_active_semester($id) { return (object) array('id' => 2, 'semester_name' => 'First'); }
}
class Olama_School_Section {
    public static function get_sections_by_year($id) {
        return array((object) array('id' => 99, 'grade_id' => 10, 'grade_name' => 'First Grade', 'section_name' => 'A'));
    }
}
class Olama_School_Grade {
    public static function get_grades() { return array((object) array('id' => 10, 'grade_name' => 'First Grade')); }
}
class Olama_School_Permissions {
    public static function can($capability) { return true; }
    public static function is_assistant() { return false; }
}
class Olama_School_Helpers {
    public static function translate($text) { return $text; }
    public static function sanitize_date($value) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : ''; }
}
class Olama_Student_Evaluation_DB {}
class Attendance_Test_Saved extends RuntimeException {}

$transients = array();
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
function absint($value) { return abs((int) $value); }
function add_action(...$args) {}
function add_shortcode(...$args) {}
function is_user_logged_in() { return true; }
function current_user_can($capability) { return true; }
function get_current_user_id() { return 42; }
function current_time($format) { return '2026-10-26'; }
function shortcode_atts($defaults, $atts, $tag) { return array_merge($defaults, $atts); }
function wp_list_pluck($rows, $field) { return array_map(function ($row) use ($field) { return $row->$field; }, $rows); }
function wp_unslash($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function __($value, $domain = '') { return $value; }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function is_rtl() { return true; }
function is_admin() { return true; }
function get_query_var($name) { return 0; }
function add_query_arg($args) { return 'https://example.test/?' . http_build_query($args); }
function wp_nonce_field($name) {}
function wp_json_encode($value) { return json_encode($value); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function check_admin_referer($name) { return true; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_doing_ajax() { return true; }
function wp_send_json_success($data) { throw new Attendance_Test_Saved($data['message']); }
function wp_die($message, ...$args) { throw new RuntimeException($message); }

require dirname(__DIR__) . '/includes/class-student.php';
// An optional alternate source is useful for proving the regression fails against old code.
require $argv[1] ?? dirname(__DIR__) . '/includes/class-attendance-shortcode.php';

$args = array('academic_year_id' => 1, 'section_id' => 99);
$all = Olama_School_Student::get_students($args);
expect_roster(count($all) === 7, 'The ordinary student list must retain withdrawn students.');
$eligible = Olama_School_Student::get_students($args + array('attendance_only' => true));
$eligible_ids = array_map('intval', wp_list_pluck($eligible, 'id'));
sort($eligible_ids);
expect_roster($eligible_ids === array(1, 7), 'Attendance adapter must filter code, Arabic and English withdrawal statuses.');
expect_roster(count($transients) === 2, 'Filtered attendance must use a separate cache from the warm ordinary student list.');

$_GET = array('grade_id' => 10, 'section_id' => 99, 'attendance_date' => '2026-10-26');
$shortcode = new Olama_School_Attendance_Shortcode();
$html = $shortcode->render(array());
foreach ($fixtures as $fixture) {
    $shown = strpos($html, esc_html($fixture[1])) !== false;
    expect_roster($shown === in_array($fixture[0], array(1, 7), true), 'Incorrect attendance card visibility: ' . $fixture[1]);
}
expect_roster(substr_count($html, 'class="olama-student-card"') === 2, 'The card roster must contain only continuing students.');
expect_roster(strpos($html, 'data-present-count>1</strong>') !== false, 'Present count must exclude withdrawn students.');
expect_roster(strpos($html, 'data-absent-count>1</strong>') !== false, 'Absent count must exclude historical withdrawn marks.');

function save_attendance_fixture($shortcode, $data) {
    $_POST = array('olama_frontend_attendance' => 1, 'section_id' => 99, 'attendance_date' => '2026-10-26', 'attendance' => $data);
    try {
        $shortcode->save();
        throw new RuntimeException('The save request did not finish successfully.');
    } catch (Attendance_Test_Saved $saved) {}
}
$submitted = array();
foreach ($all as $student) {
    $submitted[$student->id] = array('status' => 'present', 'reason' => 'Stale submitted row');
}
$submitted[1] = array('status' => 'present', 'reason' => '');
$submitted[7] = array('status' => 'absent', 'reason' => 'Continuing student absent');
save_attendance_fixture($shortcode, $submitted);
$saved = $wpdb->get_results('SELECT * FROM wp_olama_attendance ORDER BY student_id');
expect_roster(array_map('intval', wp_list_pluck($saved, 'student_id')) === array(1, 2, 7), 'Stale submitted withdrawn rows must not create attendance records.');
expect_roster($saved[0]->status === 'present' && $saved[2]->status === 'absent', 'Eligible attendance changes must still save.');
expect_roster($saved[1]->status === 'absent' && $saved[1]->reason === 'Historical withdrawn mark' && (int) $saved[1]->recorded_by === 9, 'Saving must leave a withdrawn student\'s historical attendance unchanged.');
expect_roster($wpdb->get_var('SELECT status FROM wp_olama_attendance_sheets') === 'completed', 'Saving eligible students must complete the section attendance sheet.');

// A fresh filtered form must save without requiring hidden withdrawn students in its POST.
save_attendance_fixture($shortcode, array(1 => $submitted[1], 7 => $submitted[7]));
echo "Attendance roster render/save regression checks passed.\n";
