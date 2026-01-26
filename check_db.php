<?php
require_once('../../../wp-load.php');
global $wpdb;

$tables = [
    $wpdb->prefix . 'olama_curriculum_units',
    $wpdb->prefix . 'olama_curriculum_lessons'
];

foreach ($tables as $table) {
    echo "Checking table: $table\n";
    $columns = $wpdb->get_results("DESCRIBE $table");
    if ($columns) {
        foreach ($columns as $column) {
            echo " - {$column->Field} ({$column->Type})\n";
        }
    } else {
        echo " - Table not found or error: " . $wpdb->last_error . "\n";
    }
}
