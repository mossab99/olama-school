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
        if (!get_option('academy_media_db_version')) {
            $db = new Academy_Media_DB();
            $db->init();
            update_option('academy_media_db_version', '1.0.0');
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

        add_action('admin_menu', [$this, 'add_submenu'], 20);
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
        add_submenu_page(
            'olama-school',
            __('مكتبة الوسائط', 'olama-school'),
            __('مكتبة الوسائط', 'olama-school'),
            'olama_access_media_library',
            'academy-media-library',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'academy-media-library') === false) {
            return;
        }

        wp_enqueue_style('academy-media-library-css', ACADEMY_MEDIA_URL . 'assets/css/media-library.css', [], '1.0.0');
        wp_enqueue_script('academy-media-library-js', ACADEMY_MEDIA_URL . 'assets/js/media-library.js', ['jquery'], '1.0.0', true);

        wp_localize_script('academy-media-library-js', 'academyMedia', [
            'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce('olama_admin_nonce'),
            'i18n' => [
                'confirm_delete' => __('هل أنت متأكد من حذف هذا السجل؟', 'olama-school'),
                'uploading' => __('جاري الرفع...', 'olama-school'),
                'error' => __('حدث خطأ ما', 'olama-school')
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
