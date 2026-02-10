<?php
require_once('../../../wp-load.php');
global $wpdb;

echo "Plugin Version: " . get_option('olama_school_version') . "\n";
echo "Caps Version: " . get_option('olama_school_caps_version') . "\n";
echo "Defined Constant Version: " . OLAMA_SCHOOL_VERSION . "\n";

$admin = get_role('administrator');
echo "\nAdministrator Capabilities:\n";
if ($admin) {
    echo "olama_access_transport_mgmt: " . (isset($admin->capabilities['olama_access_transport_mgmt']) ? "Yes" : "No") . "\n";
    echo "olama_manage_transport_buses: " . (isset($admin->capabilities['olama_manage_transport_buses']) ? "Yes" : "No") . "\n";
} else {
    echo "Administrator role not found!\n";
}

// Force re-init if requested
if (isset($_GET['force_init'])) {
    echo "\nForcing Permissions::init()...\n";
    require_once('includes/class-permissions.php');
    delete_option('olama_school_caps_version');
    Olama_School_Permissions::init();
    echo "Done.\n";

    $admin = get_role('administrator');
    echo "New olama_access_transport_mgmt status: " . (isset($admin->capabilities['olama_access_transport_mgmt']) ? "Yes" : "No") . "\n";
}
