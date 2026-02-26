<?php
/**
 * Temporary: Force the student_uid migration — DELETE AFTER USE
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

echo "<h2>Forcing student_uid migration</h2>";

// Check if student_uid column exists
$uid_col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records LIKE 'student_uid'");

if (empty($uid_col)) {
    echo "<p>Adding student_uid column...</p>";
    $wpdb->query(
        "ALTER TABLE {$wpdb->prefix}olama_ev_records 
        ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id"
    );
    echo "<p style='color:green;'>Column added!</p>";
} else {
    echo "<p>student_uid column already exists.</p>";
}

// Backfill student_uid from olama_students
$backfilled = $wpdb->query(
    "UPDATE {$wpdb->prefix}olama_ev_records r 
    INNER JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
    SET r.student_uid = s.student_uid 
    WHERE r.student_uid IS NULL"
);
echo "<p><strong>Backfilled student_uid for $backfilled records.</strong></p>";

// Diagnostics
$total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records");
$with_uid = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records WHERE student_uid IS NOT NULL");
$without_uid = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records WHERE student_uid IS NULL");
$orphaned = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records r 
     LEFT JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
     WHERE s.id IS NULL"
);

echo "<h3>Results:</h3>";
echo "<p>Total records: $total_records</p>";
echo "<p>Records with student_uid: $with_uid</p>";
echo "<p>Records without student_uid (orphaned, can't backfill): $without_uid</p>";
echo "<p>Orphaned records (student_id not found): $orphaned</p>";

if ($without_uid > 0) {
    echo "<h3>Records that could NOT be backfilled (old student no longer exists):</h3>";
    $unlinked = $wpdb->get_results(
        "SELECT r.id, r.student_id, r.template_id, r.academic_year_id, r.semester_id, r.created_at
         FROM {$wpdb->prefix}olama_ev_records r 
         LEFT JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
         WHERE s.id IS NULL
         LIMIT 20"
    );
    if ($unlinked) {
        echo "<table border='1' cellpadding='5'><tr><th>Record ID</th><th>student_id (old)</th><th>template_id</th><th>year_id</th><th>semester_id</th><th>created_at</th></tr>";
        foreach ($unlinked as $row) {
            echo "<tr><td>{$row->id}</td><td>{$row->student_id}</td><td>{$row->template_id}</td><td>{$row->academic_year_id}</td><td>{$row->semester_id}</td><td>{$row->created_at}</td></tr>";
        }
        echo "</table>";
    }
}

echo "<p style='color:green; font-weight:bold;'>Migration complete. You can delete this file now.</p>";
