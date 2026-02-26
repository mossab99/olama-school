<?php
/**
 * Fix Orphaned Evaluations — Two-Phase Tool
 * 
 * PHASE 1: Import old students from backup into a temp table
 * PHASE 2: Build mapping and fix ev_records
 * 
 * DELETE AFTER USE.
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

$phase = isset($_GET['phase']) ? intval($_GET['phase']) : 0;
$prefix = $wpdb->prefix;

echo "<div style='max-width:900px; margin:20px auto; font-family:sans-serif;'>";
echo "<h1>Fix Orphaned Evaluations</h1>";

// Ensure student_uid column exists
$uid_col = $wpdb->get_results("SHOW COLUMNS FROM {$prefix}olama_ev_records LIKE 'student_uid'");
if (empty($uid_col)) {
    $wpdb->query("ALTER TABLE {$prefix}olama_ev_records ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id");
    echo "<p style='color:green;'>✅ Added student_uid column to ev_records.</p>";
}

// Check if temp table exists
$temp_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prefix}olama_students_backup'");

if ($phase === 2 && $temp_exists) {
    // ===== PHASE 2: Build mapping and fix =====
    echo "<h2>Phase 2: Fixing...</h2>";

    $old_students_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students_backup");
    echo "<p>Found <strong>$old_students_count</strong> students in backup table.</p>";

    // Step 1: Map old student_id → student_uid using the backup table
    $mapped = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r 
        INNER JOIN {$prefix}olama_students_backup s_old ON r.student_id = s_old.id 
        SET r.student_uid = s_old.student_uid 
        WHERE r.student_uid IS NULL"
    );
    echo "<p>✅ Mapped <strong>$mapped</strong> evaluation records to their student_uid (ID Number).</p>";

    // Step 2: Re-link to new students using student_uid
    $relinked = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r 
        INNER JOIN {$prefix}olama_students s_new ON r.student_uid = s_new.student_uid 
        SET r.student_id = s_new.id 
        WHERE r.student_id NOT IN (SELECT id FROM {$prefix}olama_students)"
    );
    echo "<p>✅ Re-linked <strong>$relinked</strong> evaluation records to new student IDs.</p>";

    // Step 3: Show results
    $still_orphaned = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r 
         LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id 
         WHERE s.id IS NULL"
    );
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records");
    $fixed = $total - $still_orphaned;

    echo "<hr>";
    echo "<h3>Results:</h3>";
    echo "<p>✅ Fixed records: <strong>$fixed / $total</strong></p>";
    echo "<p>⚠️ Still orphaned: <strong>$still_orphaned</strong> (students not found in current system)</p>";

    if ($still_orphaned > 0) {
        $remaining = $wpdb->get_results(
            "SELECT r.student_id, r.student_uid, COUNT(*) as record_count
             FROM {$prefix}olama_ev_records r 
             LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id 
             WHERE s.id IS NULL
             GROUP BY r.student_id, r.student_uid
             ORDER BY record_count DESC
             LIMIT 20"
        );
        echo "<table border='1' cellpadding='5'><tr><th>Old student_id</th><th>student_uid</th><th># Records</th></tr>";
        foreach ($remaining as $row) {
            echo "<tr><td>{$row->student_id}</td><td>" . ($row->student_uid ?: 'NULL') . "</td><td>{$row->record_count}</td></tr>";
        }
        echo "</table>";
        echo "<p><em>These students exist in old evaluations but are not in the current student roster.</em></p>";
    }

    // Cleanup option
    echo "<hr>";
    echo "<p><a href='?phase=3' style='color:red;' onclick=\"return confirm('Drop the backup table?')\">🗑️ Drop backup table and cleanup</a></p>";

} elseif ($phase === 3) {
    // ===== CLEANUP =====
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}olama_students_backup");
    echo "<p style='color:green; font-size:18px;'>✅ Backup table dropped. You can now delete this file.</p>";

} else {
    // ===== PHASE 1: Instructions =====
    echo "<h2>Phase 1: Import Old Students</h2>";

    // Show current state
    $total_records = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records");
    $orphaned = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r 
         LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id 
         WHERE s.id IS NULL"
    );
    echo "<p><strong>$orphaned / $total_records</strong> evaluation records are orphaned (student not found).</p>";

    if ($temp_exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students_backup");
        echo "<div style='background:#d4edda; padding:15px; border-radius:8px; border:1px solid #c3e6cb;'>";
        echo "<p>✅ <strong>Backup table found with $count students!</strong></p>";
        echo "<p><a href='?phase=2' style='background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-size:16px;'>▶️ Run Phase 2: Fix Orphaned Data</a></p>";
        echo "</div>";
    } else {
        echo "<div style='background:#fff3cd; padding:15px; border-radius:8px; border:1px solid #ffc107;'>";
        echo "<h3>Instructions:</h3>";
        echo "<p>You need to import the <strong>old students table</strong> from your database backup.</p>";
        echo "<ol style='line-height:2;'>";
        echo "<li>Open <strong>phpMyAdmin</strong> on this server</li>";
        echo "<li>Go to the <strong>SQL tab</strong></li>";
        echo "<li>Run this SQL to create the temp table:</li>";
        echo "</ol>";

        echo "<textarea style='width:100%; height:150px; font-family:monospace; font-size:13px;'>";
        echo "CREATE TABLE {$prefix}olama_students_backup LIKE {$prefix}olama_students;\n\n";
        echo "-- Then import the old students data from your backup file.\n";
        echo "-- In your backup SQL file, find the INSERT statements for '{$prefix}olama_students'\n";
        echo "-- and change the table name to '{$prefix}olama_students_backup'\n";
        echo "-- Then run those INSERT statements.\n";
        echo "</textarea>";

        echo "<p>After importing, <a href='?phase=1'><strong>reload this page</strong></a>.</p>";
        echo "</div>";
    }
}

echo "</div>";
