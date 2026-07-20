<?php
/**
 * Plugin Name: Olama School System
 * Plugin URI: https://olama.online/olama-school-weekly-plan
 * Description: A comprehensive WordPress plugin for managing school weekly plans, including hierarchical structures (Grades, Sections), subject management, and teacher/student assignments.
 * Version: 2.4.2
 * Author: د. مصعب الحنيطي
 * Author URI: https://olama.online
 * Text Domain: olama-school
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
// Get version from plugin header to keep it synced
$plugin_header_data = file_get_contents(__FILE__, false, null, 0, 500);
preg_match('/Version:\s*(.*)$/mi', $plugin_header_data, $matches);
define('OLAMA_SCHOOL_VERSION', isset($matches[1]) ? trim($matches[1]) : '2.4.2');
define('OLAMA_SCHOOL_PATH', plugin_dir_path(__FILE__));
define('OLAMA_SCHOOL_URL', plugin_dir_url(__FILE__));
define('OLAMA_SCHOOL_FILE', __FILE__);

/**
 * The standalone Exam Management plugin owns the exam module when active.
 * Checking the active-plugin options makes this independent of plugin load order.
 */
function olama_school_should_load_legacy_exam_module()
{
    if (defined('OLAMA_EXAM_MANAGEMENT_FILE') || class_exists('Olama_Exam_Management_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-exam-management/olama-exam-management.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * The standalone Transportation plugin owns bus management when active.
 * Checking active-plugin options keeps this independent of plugin load order.
 */
function olama_school_should_load_legacy_transportation_module()
{
    if (defined('OLAMA_TRANSPORTATION_FILE') || class_exists('Olama_Transportation_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-transportation/olama-transportation.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * The standalone KG plugin owns KG session management when active.
 * Checking active-plugin options keeps this independent of plugin load order.
 */
function olama_school_should_load_legacy_kg_module()
{
    if (defined('OLAMA_KG_FILE') || class_exists('Olama_KG_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-kg/olama-kg.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * The standalone Student Evaluation plugin owns evaluation when active.
 * Checking active-plugin options keeps this independent of plugin load order.
 */
function olama_school_should_load_legacy_student_evaluation_module()
{
    if (defined('OLAMA_STUDENT_EVALUATION_FILE') || class_exists('Olama_Student_Evaluation_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-student-evaluation/olama-student-evaluation.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * Return the evaluation admin page currently responsible for the module.
 */
function olama_school_evaluation_admin_page()
{
    return olama_school_should_load_legacy_student_evaluation_module()
        ? 'olama-school-evaluation'
        : 'olama-student-evaluation';
}

/**
 * The standalone Olama Supervision plugin owns academic supervision when active.
 */
function olama_school_should_load_legacy_supervision_module()
{
    if (defined('OLAMA_SUPERVISION_FILE') || class_exists('Olama_Supervision_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-supervision/olama-supervision.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * Return the admin page currently responsible for academic supervision.
 */
function olama_school_supervision_admin_page()
{
    return olama_school_should_load_legacy_supervision_module()
        ? 'olama-school-supervision'
        : 'olama-supervision';
}

/**
 * The standalone Olama Employees plugin owns shifts and cleaning when active.
 */
function olama_school_should_load_legacy_employees_module()
{
    if (defined('OLAMA_EMPLOYEES_FILE') || class_exists('Olama_Employees_Plugin', false)) {
        return false;
    }

    $plugin = 'olama-employees/olama-employees.php';
    if (in_array($plugin, (array) get_option('active_plugins', array()), true)) {
        return false;
    }

    if (is_multisite()) {
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        if (isset($network_plugins[$plugin])) {
            return false;
        }
    }

    return true;
}

/**
 * Return the admin page currently responsible for employee follow-up.
 */
function olama_school_follow_up_admin_page()
{
    return olama_school_should_load_legacy_employees_module()
        ? 'olama-school-follow-up'
        : 'olama-employees';
}

// Load Composer autoloader for PHPSpreadsheet
if (file_exists(OLAMA_SCHOOL_PATH . 'vendor/autoload.php')) {
    require_once OLAMA_SCHOOL_PATH . 'vendor/autoload.php';
}

// Include required classes
require_once OLAMA_SCHOOL_PATH . 'includes/class-db.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-admin.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-academic.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-grade.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-section.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-subject.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-teacher.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-student.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-family.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-curriculum.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-plan.php';
if (olama_school_should_load_legacy_exam_module()) {
    require_once OLAMA_SCHOOL_PATH . 'includes/class-exam.php';
    require_once OLAMA_SCHOOL_PATH . 'includes/class-exam-attachment.php';
}
require_once OLAMA_SCHOOL_PATH . 'includes/class-stationary.php';
if (olama_school_should_load_legacy_transportation_module()) {
    require_once OLAMA_SCHOOL_PATH . 'includes/class-bus.php';
}
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-template.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-curriculum.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-record.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-manager.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-form.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-report.php';
require_once OLAMA_SCHOOL_PATH . 'includes/lesson-planner-config.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-lesson-planner.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-schedule.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-shifts.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-units.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-lessons.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-questions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-template.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-logger.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-system-logger.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-exporter.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-importer.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-permissions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-helpers.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-backup.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ajax-handlers.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-supervision-ajax-handlers.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-shortcodes.php';
if (olama_school_should_load_legacy_exam_module()) {
    require_once OLAMA_SCHOOL_PATH . 'includes/class-exam-hall.php';
    require_once OLAMA_SCHOOL_PATH . 'includes/class-exam-hall-ajax.php';
}

// Service Layer
require_once OLAMA_SCHOOL_PATH . 'includes/Services/ScheduleValidatorService.php';
require_once OLAMA_SCHOOL_PATH . 'includes/Services/EvaluationScoringService.php';
require_once OLAMA_SCHOOL_PATH . 'includes/Services/SupervisorVisitService.php';

/**
 * Contribute School and extension capabilities to the Core permissions registry.
 */
function olama_school_register_core_capabilities($groups)
{
    if (!class_exists('Olama_School_Permissions')) {
        return $groups;
    }

    return array_replace($groups, Olama_School_Permissions::get_all_capabilities());
}
add_filter('olama_core_capability_groups', 'olama_school_register_core_capabilities');

// Register custom cron schedules (WordPress only has hourly/twicedaily/daily)
function olama_school_cron_schedules($schedules)
{
    $schedules['weekly'] = array(
        'interval' => 604800, // 7 days in seconds
        'display' => 'Once Weekly', // Label for UI
    );
    return $schedules;
}
add_filter('cron_schedules', 'olama_school_cron_schedules');

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    require_once OLAMA_SCHOOL_PATH . 'includes/class-cli.php';
    WP_CLI::add_command('olama', 'Olama_School_CLI');
}

/**
 * Plugin activation
 */
function olama_school_activate()
{
    // Capture all output during activation to prevent "unexpected output" errors
    ob_start();

    try {
        // Initialize Database
        $olama_db = new Olama_School_DB();
        $olama_db->create_tables();

        // Initialize Permissions

        // Flush rewrite rules
        flush_rewrite_rules();
    } catch (Exception $e) {
        // Log error but don't output anything
        error_log('Olama Activation Error: ' . $e->getMessage());
    }

    // Clean any stray output
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
}
register_activation_hook(__FILE__, 'olama_school_activate');

/**
 * Plugin deactivation
 */
function olama_school_deactivate()
{
    // Core owns shared roles and capabilities. Preserve them when Core is active.
    if (!class_exists('Olama_Core_Permissions')) {
        Olama_School_Permissions::remove_capabilities();
    }

    // Flush rewrite rules
    flush_rewrite_rules();

    // Clear scheduled backup
    wp_clear_scheduled_hook('olama_scheduled_backup');
}
register_deactivation_hook(__FILE__, 'olama_school_deactivate');

/**
 * Initialize the plugin
 */
function olama_school_init()
{

    // Load translations
    load_plugin_textdomain('olama-school', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Schema Updates
    $installed_ver = get_option('olama_school_version');
    if ($installed_ver !== OLAMA_SCHOOL_VERSION) {
        $olama_db = new Olama_School_DB();
        $olama_db->create_tables();
        update_option('olama_school_version', OLAMA_SCHOOL_VERSION);
    }

    // Initialize Permissions (ensure caps are updated if code changes)
    Olama_School_Permissions::init();

    // Initialize Admin Components
    new Olama_School_Admin();
    if (is_admin()) {
        new Olama_School_Ajax_Handlers();
        if (olama_school_should_load_legacy_supervision_module()) {
            new Olama_School_Supervision_Ajax_Handlers();
        }
    }

    // Initialize Shortcodes
    new Olama_School_Shortcodes();

    // Hook scheduled backup
    add_action('olama_scheduled_backup', array('Olama_School_Backup', 'run_scheduled_backup'));

    // Self-healing: ensure cron event exists when backups are enabled
    $frequency = get_option('olama_backup_frequency', 'disabled');
    if ($frequency !== 'disabled' && !wp_next_scheduled('olama_scheduled_backup')) {
        $recurrence = ($frequency === 'daily') ? 'daily' : 'weekly';
        wp_schedule_event(time(), $recurrence, 'olama_scheduled_backup');
        error_log('[OLAMA CRON] Self-healed: re-scheduled ' . $recurrence . ' backup event.');
    }
}
add_action('init', 'olama_school_init', 5);

/**
 * Check for DB Reset Action
 */
function olama_check_db_reset()
{
    if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'olama_retabulate' && current_user_can('manage_options')) {
        $olama_db = new Olama_School_DB();
        $olama_db->drop_tables();
        $olama_db->create_tables();

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>All Olama School tables have been successfully dropped and recreated.</p></div>';
        });
    }
}
add_action('admin_init', 'olama_check_db_reset');

/**
 * Force Arabic locale if set in plugin settings
 */
function olama_school_force_locale($locale)
{
    if (is_admin() && Olama_School_Helpers::is_arabic()) {
        return 'ar';
    }
    return $locale;
}
add_filter('plugin_locale', 'olama_school_force_locale');
add_filter('locale', 'olama_school_force_locale');

/**
 * Filter gettext to provide Arabic translations from our map
 */
function olama_school_translate_strings($translated, $text, $domain)
{
    if ($domain === 'olama-school' && Olama_School_Helpers::is_arabic()) {
        return Olama_School_Helpers::translate($text);
    }
    return $translated;
}
add_filter('gettext', 'olama_school_translate_strings', 10, 3);

// Media Library Module
// Phase 1 extraction: when the standalone olama-media-library plugin is active,
// it owns the media admin page, AJAX handlers, Google Drive upload flow, and diagnostics.
function olama_school_should_load_legacy_media_module()
{
    if (class_exists('Olama_Media_Library_Plugin') || defined('OLAMA_MEDIA_LIBRARY_FILE')) {
        return false;
    }

    $active_plugins = (array) get_option('active_plugins', array());
    if (in_array('olama-media-library/olama-media-library.php', $active_plugins, true)) {
        return false;
    }

    return true;
}

if (is_admin() && olama_school_should_load_legacy_media_module()) {
    require_once OLAMA_SCHOOL_PATH . 'media-library/class-media-library.php';
}
