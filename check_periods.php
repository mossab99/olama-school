<?php
/**
 * DB Debug: Check Periods
 */
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'olama_shifts_periods';

$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
$records = $wpdb->get_results("SELECT * FROM $table");

echo "Total Periods: $count\n\n";
foreach ($records as $r) {
    echo "ID: {$r->id} | Year: {$r->academic_year_id} | Sem: {$r->semester_id} | Type: {$r->shift_type} | Active: {$r->is_active}\n";
}
