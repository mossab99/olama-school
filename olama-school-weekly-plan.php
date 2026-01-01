<?php
/**
 * Plugin Name: Mossab Olama School Weekly Plan System
 * Plugin URI: https://example.com/olama-school-weekly-plan
 * Description: A comprehensive WordPress plugin for managing school weekly plans, including hierarchical structures (Grades, Sections), subject management, and teacher/student assignments.
 * Version:           1.2.5.88
 * Author: Antigravity
 * Author URI: https://example.com
 * Text Domain: olama-school
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('OLAMA_SCHOOL_VERSION', '1.2.5');
define('OLAMA_SCHOOL_PATH', plugin_dir_path(__FILE__));
define('OLAMA_SCHOOL_URL', plugin_dir_url(__FILE__));

// Include required classes
require_once OLAMA_SCHOOL_PATH . 'includes/class-db.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-admin.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-academic.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-grade.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-section.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-subject.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-teacher.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-student.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-curriculum.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-plan.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-schedule.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-units.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-lessons.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-questions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-template.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-logger.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-exporter.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-importer.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-permissions.php';
require_once OLAMA_SCHOOL_PATH . 'includes/class-shortcodes.php';

/**
 * Plugin activation
 */
function olama_school_activate()
{
    // Initialize Database
    $olama_db = new Olama_School_DB();
    $olama_db->create_tables();

    // Initialize Permissions
    Olama_School_Permissions::add_capabilities();

    // Flush rewrite rules
    flush_rewrite_rules();
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
    // Initialize Permissions (ensure caps are updated if code changes)
    Olama_School_Permissions::init();

    // Initialize Admin
    if (is_admin()) {
        new Olama_School_Admin();
    }

    // Initialize Shortcodes
    new Olama_School_Shortcodes();
}
add_action('plugins_loaded', 'olama_school_init');
