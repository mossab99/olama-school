<?php
require_once __DIR__ . '/../../../wp-load.php';
global $wpdb;
$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'First Semester' WHERE semester_name = '1st Semester'");
$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'Second Semester' WHERE semester_name = '2nd Semester'");
echo "Migration Complete";
unlink(__FILE__);
