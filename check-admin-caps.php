<?php
require_once('../../../wp-load.php');
$role = get_role('administrator');
$output = "Administrator Capabilities:\n";
if ($role) {
    foreach ($role->capabilities as $cap => $value) {
        if (strpos($cap, 'olama_') === 0) {
            $output .= "$cap: $value\n";
        }
    }
} else {
    $output .= "Administrator role not found.";
}
file_put_contents('admin-caps.txt', $output);
echo "Done.";
