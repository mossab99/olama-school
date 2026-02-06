<?php
require_once('C:/Users/Mossab/Local Sites/olama3/app/public/wp-load.php');
global $wpdb;

$tables = [
    $wpdb->prefix . 'olama_shifts_locations',
    $wpdb->prefix . 'olama_shifts_time_slots',
    $wpdb->prefix . 'olama_shifts_schedule'
];

header('Content-Type: text/plain');

foreach ($tables as $table) {
    echo "Checking table: $table\n";
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo " - Table exists.\n";
        $columns = $wpdb->get_results("DESCRIBE $table");
        foreach ($columns as $column) {
            echo "   - {$column->Field} ({$column->Type})\n";
        }
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo " - Row count: $count\n\n";
    } else {
        echo " - Table MISSING!\n\n";
    }
}
