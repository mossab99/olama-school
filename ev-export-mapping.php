<?php
/**
 * Diagnostic + Fix: Analyzes ev_records vs students and generates fix SQL.
 * DELETE AFTER USE.
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

echo "<h2>Evaluation Data Diagnostic & Fix</h2>";

// Check if student_uid column exists
$uid_col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records LIKE 'student_uid'");
echo "<p><strong>student_uid column exists:</strong> " . (empty($uid_col) ? 'NO' : 'YES') . "</p>";

if (empty($uid_col)) {
    echo "<p>Adding student_uid column...</p>";
    $wpdb->query(
        "ALTER TABLE {$wpdb->prefix}olama_ev_records 
        ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id"
    );
    echo "<p style='color:green;'>Column added!</p>";
}

// Step 1: Show ev_records student_id range vs students.id range
$ev_min_max = $wpdb->get_row("SELECT MIN(student_id) as min_id, MAX(student_id) as max_id, COUNT(DISTINCT student_id) as unique_students FROM {$wpdb->prefix}olama_ev_records");
$stu_min_max = $wpdb->get_row("SELECT MIN(id) as min_id, MAX(id) as max_id, COUNT(*) as total FROM {$wpdb->prefix}olama_students");

echo "<h3>ID Ranges:</h3>";
echo "<p><strong>ev_records student_id range:</strong> {$ev_min_max->min_id} to {$ev_min_max->max_id} ({$ev_min_max->unique_students} unique students)</p>";
echo "<p><strong>olama_students id range:</strong> {$stu_min_max->min_id} to {$stu_min_max->max_id} ({$stu_min_max->total} total students)</p>";

// Step 2: How many ev_records match current students?
$matching = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records r 
     INNER JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id"
);
$total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records");
$orphaned = $total_records - $matching;

echo "<p><strong>Matching records (student_id exists):</strong> $matching</p>";
echo "<p><strong>Orphaned records (student_id NOT found):</strong> $orphaned</p>";
echo "<p><strong>Total records:</strong> $total_records</p>";

// Step 3: Try the backfill for matching records
$backfilled = $wpdb->query(
    "UPDATE {$wpdb->prefix}olama_ev_records r 
    INNER JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
    SET r.student_uid = s.student_uid 
    WHERE r.student_uid IS NULL"
);
echo "<p><strong>Backfilled student_uid for $backfilled matching records.</strong></p>";

// Step 4: Show sample orphaned records
if ($orphaned > 0) {
    echo "<h3>Sample orphaned ev_records (student_id doesn't exist in students table):</h3>";
    $samples = $wpdb->get_results(
        "SELECT r.id, r.student_id, r.student_uid, r.template_id, r.academic_year_id, r.semester_id, r.created_at
         FROM {$wpdb->prefix}olama_ev_records r 
         LEFT JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
         WHERE s.id IS NULL
         LIMIT 15"
    );
    echo "<table border='1' cellpadding='5'><tr><th>record_id</th><th>student_id (old PK)</th><th>student_uid</th><th>template_id</th><th>year</th><th>semester</th><th>created</th></tr>";
    foreach ($samples as $row) {
        echo "<tr><td>{$row->id}</td><td>{$row->student_id}</td><td>" . ($row->student_uid ?: 'NULL') . "</td><td>{$row->template_id}</td><td>{$row->academic_year_id}</td><td>{$row->semester_id}</td><td>{$row->created_at}</td></tr>";
    }
    echo "</table>";
}

// Step 5: Show sample current students
echo "<h3>Sample current students (first 10):</h3>";
$students_sample = $wpdb->get_results("SELECT id, student_name, student_uid FROM {$wpdb->prefix}olama_students ORDER BY id LIMIT 10");
echo "<table border='1' cellpadding='5'><tr><th>id (PK)</th><th>student_name</th><th>student_uid (ID Number)</th></tr>";
foreach ($students_sample as $s) {
    echo "<tr><td>{$s->id}</td><td>{$s->student_name}</td><td>{$s->student_uid}</td></tr>";
}
echo "</table>";

// Step 6: Generate mapping SQL if we have backfilled records
$mapped = $wpdb->get_results(
    "SELECT DISTINCT student_id, student_uid 
     FROM {$wpdb->prefix}olama_ev_records 
     WHERE student_uid IS NOT NULL
     ORDER BY student_id"
);

if (count($mapped) > 0) {
    echo "<h3>✅ Mapping generated! Copy this SQL for PRODUCTION:</h3>";
    echo "<textarea id='sql-output' style='width:100%; height:400px; font-family:monospace; font-size:12px;'>";
    echo "-- Generated from LOCAL server mapping\n";
    echo "-- Run this SQL on PRODUCTION to fix orphaned evaluation records\n\n";

    foreach ($mapped as $m) {
        $uid = esc_sql($m->student_uid);
        echo "UPDATE {$wpdb->prefix}olama_ev_records SET student_uid = '{$uid}' WHERE student_id = {$m->student_id} AND (student_uid IS NULL OR student_uid = '');\n";
    }

    echo "\n-- Re-link orphaned records to new students\n";
    echo "UPDATE {$wpdb->prefix}olama_ev_records r \n";
    echo "INNER JOIN {$wpdb->prefix}olama_students s ON r.student_uid = s.student_uid \n";
    echo "SET r.student_id = s.id \n";
    echo "WHERE r.student_uid IS NOT NULL \n";
    echo "AND r.student_id NOT IN (SELECT id FROM {$wpdb->prefix}olama_students);\n";

    echo "</textarea>";
    echo "<button onclick=\"document.getElementById('sql-output').select(); document.execCommand('copy'); alert('Copied!');\" style='margin-top:10px; padding: 10px 20px; font-size: 14px;'>📋 Copy SQL</button>";
} else {
    echo "<p style='color:red;'><strong>No mappings could be generated.</strong> The student IDs in ev_records don't match any current students.</p>";
    echo "<p>This means the backup was restored but the student IDs changed. We need an alternative approach.</p>";
}
