<?php
/**
 * Plugin Name: Olama School System
 * Plugin URI: https://olama.online/olama-school-weekly-plan
 * Description: A comprehensive WordPress plugin for managing school weekly plans, including hierarchical structures (Grades, Sections), subject management, and teacher/student assignments.
 * Version: 2.9.0
 * Requires Plugins: olama-core, olama-users
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
define('OLAMA_SCHOOL_VERSION', isset($matches[1]) ? trim($matches[1]) : '2.9.0');
define('OLAMA_SCHOOL_PATH', plugin_dir_path(__FILE__));
define('OLAMA_SCHOOL_URL', plugin_dir_url(__FILE__));
define('OLAMA_SCHOOL_FILE', __FILE__);

// Load Composer autoloader for PHPSpreadsheet
if (file_exists(OLAMA_SCHOOL_PATH . 'vendor/autoload.php')) {
    require_once OLAMA_SCHOOL_PATH . 'vendor/autoload.php';
}

// Include required classes
require_once OLAMA_SCHOOL_PATH . 'includes/class-db.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-admin.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-academic.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-academic-bridge.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-grade.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-section.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-subject.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-teacher.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-student.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-family.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-curriculum.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-plan.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-stationary.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-schedule.php';
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
require_once OLAMA_SCHOOL_PATH . 'includes/class-shortcodes.php';

// Service Layer
require_once OLAMA_SCHOOL_PATH . 'includes/Services/ScheduleValidatorService.php';

/**
 * Contribute School capabilities to the Core permissions registry.
 */
function olama_school_register_core_capabilities($groups)
{
    if (!class_exists('Olama_School_Permissions')) {
        return $groups;
    }

    return array_replace($groups, Olama_School_Permissions::get_all_capabilities());
}
add_filter('olama_core_capability_groups', 'olama_school_register_core_capabilities');

/**
 * Register the complete School capability tree with the Olama Users matrix.
 */
function olama_school_register_users_module()
{
    if (!function_exists('olama_users_register_module')) {
        return;
    }

    $items = array();
    foreach (Olama_School_Permissions::get_all_capabilities() as $group_id => $group) {
        $actions = array();
        foreach ((array) ($group['caps'] ?? array()) as $capability => $label) {
            if ($capability === 'olama_view_dashboard') {
                continue;
            }
            $actions[] = array(
                'id' => 'olama_school.' . sanitize_key($group_id . '.' . $capability),
                'type' => 'action',
                'label' => $label,
                'capability' => $capability,
            );
        }
        $items[] = array(
            'id' => 'olama_school.' . sanitize_key($group_id),
            'type' => 'submenu',
            'label' => $group['label'],
            'actions' => $actions,
        );
    }

    olama_users_register_module(array(
        'id' => 'olama_school',
        'plugin' => 'olama-school',
        'label' => __('Olama School', 'olama-school'),
        'capability' => 'olama_view_dashboard',
        'items' => $items,
    ));
}
add_action('olama_users_register_modules', 'olama_school_register_users_module');

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
        $olama_db->migrate_core_student_source();

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

    // Keep legacy consumers read-only while Core remains the sole data owner.
    $olama_db = isset($olama_db) ? $olama_db : new Olama_School_DB();
    $core_student_migration = $olama_db->migrate_core_student_source();
    if (is_wp_error($core_student_migration)) {
        error_log('Olama School Core student bridge: ' . $core_student_migration->get_error_message());
    }

    // Initialize Permissions (ensure caps are updated if code changes)
    Olama_School_Permissions::init();

    // Initialize Admin Components
    new Olama_School_Admin();
    if (is_admin()) {
        new Olama_School_Ajax_Handlers();
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
        $olama_db->migrate_core_student_source();

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
