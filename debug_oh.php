<?php
require_once('../../../wp-load.php');
global $wpdb;

$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_teacher_office_hours WHERE academic_year_id = 0 OR semester_id = 0");
$active_year = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}olama_academic_years WHERE is_active = 1 LIMIT 1");
$active_semester = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d AND is_active = 1 LIMIT 1", $active_year->id));

echo "Legacy Records: " . $count . "\n";
echo "Active Year: " . ($active_year ? $active_year->year_name . " (ID: " . $active_year->id . ")" : "None") . "\n";
echo "Active Semester: " . ($active_semester ? $active_semester->semester_name . " (ID: " . $active_semester->id . ")" : "None") . "\n";
