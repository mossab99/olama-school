<?php
/**
 * Plugin Name: Olama Debug
 */

add_action('admin_init', function () {
    global $submenu;
    if (isset($_GET['olama_debug_menu'])) {
        $output = print_r($submenu['olama-school'], true);
        file_put_contents(plugin_dir_path(__FILE__) . 'menu_debug.log', $output);
        echo "<pre>$output</pre>";
        exit;
    }
});
