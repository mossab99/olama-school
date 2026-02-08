<?php
require_once('../../../wp-load.php');
if (current_user_can('manage_options')) {
    header('Content-Type: text/plain');
    print_r(array_keys(wp_roles()->roles));
} else {
    echo "Unauthorized";
}
