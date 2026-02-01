<?php
/**
 * Plugin Name: Olama School System
 * Plugin URI: https://olama.online/olama-school-weekly-plan
 * Description: A comprehensive WordPress plugin for managing school weekly plans, including hierarchical structures (Grades, Sections), subject management, and teacher/student assignments.
 * Version: 1.6.0
 * Author: د. مصعب الحنيطي
 * Author URI: https://olama.online
 * Text Domain: أكاديمية علماء المستقبل
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('OLAMA_SCHOOL_VERSION', '1.6.8');
define('OLAMA_SCHOOL_PATH', plugin_dir_path(__FILE__));
define('OLAMA_SCHOOL_URL', plugin_dir_url(__FILE__));

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
require_once OLAMA_SCHOOL_PATH . 'includes/class-exam.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-stationary.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-template.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-curriculum.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-record.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-manager.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-form.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ev-report.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-schedule.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-units.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-lessons.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-questions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-template.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-logger.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-exporter.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-importer.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-permissions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-helpers.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-ajax-handlers.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-shortcodes.php';

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
        Olama_School_Permissions::add_capabilities();

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
    // Remove Permissions
    Olama_School_Permissions::remove_capabilities();

    // Flush rewrite rules
    flush_rewrite_rules();
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

    // Initialize Admin
    if (is_admin()) {
        new Olama_School_Admin();
        new Olama_School_Ajax_Handlers();
    }

    // Initialize Shortcodes
    new Olama_School_Shortcodes();
}
add_action('plugins_loaded', 'olama_school_init');

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