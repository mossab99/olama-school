<?php
require_once('../../../wp-load.php');
global $wpdb;

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

echo "<h1>Fixing Olama Transportation Capabilities</h1>";

$role = get_role('administrator');
if ($role) {
    echo "Adding olama_access_transport_mgmt...<br>";
    $role->add_cap('olama_access_transport_mgmt');
    echo "Adding olama_manage_transport_buses...<br>";
    $role->add_cap('olama_manage_transport_buses');
    echo "Success! Please refresh your admin dashboard.";
} else {
    echo "Error: Administrator role not found.";
}

// Also reset the version option to ensure init runs next time
update_option('olama_school_caps_version', 'fixed');
