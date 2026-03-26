<?php
/**
 * Media Library Main Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Academy_Media_Library
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        if (is_admin()) {
            $this->maybe_init_db();
        }
        $this->init_hooks();
    }

    private function maybe_init_db()
    {
        $current_version = get_option('academy_media_db_version', '0');
        if (version_compare($current_version, '1.0.1', '<')) {
            $db = new Academy_Media_DB();
            $db->init();
            update_option('academy_media_db_version', '1.0.1');
        }
    }

    private function define_constants()
    {
        if (!defined('ACADEMY_MEDIA_PATH')) {
            define('ACADEMY_MEDIA_PATH', plugin_dir_path(__FILE__));
        }
        if (!defined('ACADEMY_MEDIA_URL')) {
            define('ACADEMY_MEDIA_URL', plugin_dir_url(__FILE__));
        }
    }

    private function includes()
    {
        require_once ACADEMY_MEDIA_PATH . 'class-media-db.php';
        require_once ACADEMY_MEDIA_PATH . 'class-media-drive.php';
        require_once ACADEMY_MEDIA_PATH . 'class-media-ajax.php';
    }

    private function init_hooks()
    {
        register_activation_hook(OLAMA_SCHOOL_FILE, [$this, 'activate']);

        add_action('admin_menu', [$this, 'add_submenu'], 11);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_oauth_callback']);

        // Initialize submodules
        new Academy_Media_AJAX();
    }

    public function activate()
    {
        $db = new Academy_Media_DB();
        $db->init();
    }

    public function add_submenu()
    {
        $is_arabic = Olama_School_Helpers::is_arabic();
        $title = $is_arabic ? 'مكتبة الوسائط' : 'Multimedia';

        add_submenu_page(
            'olama-school',
            $title,
            $title,
            'olama_access_media_library',
            'academy-media-library',
            [$this, 'render_page']
        );

        // Reorder menu items to ensure Settings is always last
        global $submenu;
        if (isset($submenu['olama-school'])) {
            $items = $submenu['olama-school'];
            $settings_index = false;

            foreach ($items as $index => $item) {
                if (isset($item[2]) && $item[2] === 'olama-school-settings') {
                    $settings_index = $index;
                    break;
                }
            }

            if ($settings_index !== false) {
                $settings_item = $items[$settings_index];
                unset($items[$settings_index]);
                $items[] = $settings_item; // Append to end
                $submenu['olama-school'] = array_values($items);
            }
        }
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'academy-media-library') === false) {
            return;
        }

        wp_enqueue_style('academy-media-library-css', ACADEMY_MEDIA_URL . 'assets/css/media-library.css', [], '1.0.3');
        wp_enqueue_script('academy-media-library-js', ACADEMY_MEDIA_URL . 'assets/js/media-library.js', ['jquery'], '1.0.3', true);

        wp_localize_script('academy-media-library-js', 'academyMedia', [
            'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce('olama_admin_nonce'),
            'can_approve' => Olama_School_Permissions::can('olama_media_approve_video'),
            'current_user_id' => get_current_user_id(),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this record?', 'olama-school'),
                'uploading' => __('Uploading...', 'olama-school'),
                'error' => __('Something went wrong', 'olama-school'),
                'select' => __(' -- Select -- ', 'olama-school'),
                'select_all' => __('Please select all filters first.', 'olama-school'),
                'load_curriculum' => __('Load Curriculum', 'olama-school'),
                'syncing' => __('Syncing...', 'olama-school'),
                'no_curriculum' => __('No curriculum found for these filters.', 'olama-school'),
                'unit' => __('Unit', 'olama-school'),
                'lesson_title' => __('Lesson Title', 'olama-school'),
                'status' => __('Status', 'olama-school'),
                'actions' => __('Actions', 'olama-school'),
                'no_video' => __('No Video', 'olama-school'),
                'view' => __('View', 'olama-school'),
                'replace' => __('Replace', 'olama-school'),
                'upload' => __('Upload', 'olama-school'),
                'no_lessons' => __('No lessons found.', 'olama-school'),
                'saving' => __('Saving...', 'olama-school'),
                'testing' => __('Testing...', 'olama-school'),
                'loading' => __('Loading...', 'olama-school'),
                'no_logs' => __('No logs found.', 'olama-school'),
                'view_on_drive' => __('View on Drive', 'olama-school'),
                'delete' => __('Delete', 'olama-school'),
                'status_completed' => __('Completed', 'olama-school'),
                'status_none' => __('No Video', 'olama-school'),
                'status_pending' => __('Pending', 'olama-school'),
                'status_failed' => __('Failed', 'olama-school'),
                'status_approved' => __('Approved', 'olama-school'),
                'status_rejected' => __('Rejected', 'olama-school'),
                'uploader' => __('Uploader', 'olama-school'),
                'date' => __('Date', 'olama-school'),
                'approve' => __('Approve', 'olama-school'),
                'reject' => __('Reject', 'olama-school'),
                'comments' => __('Notes', 'olama-school'),
                'save' => __('Save', 'olama-school'),
                'cancel' => __('Cancel', 'olama-school')
            ]
        ]);
    }

    public function handle_oauth_callback()
    {
        if (isset($_GET['page']) && $_GET['page'] === 'academy-media-library' && isset($_GET['code'])) {

            if (!current_user_can('manage_options')) {
                return;
            }

            $drive = new Academy_Media_Drive();
            $result = $drive->authenticate($_GET['code']);

            if (is_wp_error($result)) {
                set_transient('academy_media_auth_message', [
                    'type' => 'error',
                    'message' => $result->get_error_message()
                ], 45);
            } else {
                set_transient('academy_media_auth_message', [
                    'type' => 'success',
                    'message' => __('Google Drive authenticated successfully!', 'olama-school')
                ], 45);
            }

            // Remove code from URL to prevent re-submission
            wp_redirect(remove_query_arg('code'));
            exit;
        }
    }

    public function render_page()
    {
        $db = new Academy_Media_DB();
        $active_year = Olama_School_Academic::get_active_year();
        $active_semester = Olama_School_Academic::get_active_semester();

        // Check for auth messages
        $message = get_transient('academy_media_auth_message');
        if ($message) {
            add_settings_error('academy_media', 'auth_msg', $message['message'], $message['type']);
            delete_transient('academy_media_auth_message');
        }

        // Get dropdown lists using existing plugin classes
        $grades = Olama_School_Grade::get_grades();

        settings_errors('academy_media');
        include ACADEMY_MEDIA_PATH . 'views/media-library-page.php';
    }
}

// Initialize the module
Academy_Media_Library::get_instance();
