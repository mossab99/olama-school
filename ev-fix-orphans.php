<?php
/**
 * Fix Orphaned Evaluations — Two-Phase Diagnostic Tool
 * 
 * PHASE 1: Import old students from backup into a temp table
 * PHASE 2: Diagnostic & Fix
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

$phase = isset($_GET['phase']) ? intval($_GET['phase']) : 0;
$prefix = $wpdb->prefix;

echo "<div style='max-width:1000px; margin:20px auto; font-family:sans-serif; line-height:1.6;'>";
echo "<h1 style='color:#2c3e50;'>🛠️ Fix Orphaned Evaluations (Pro)</h1>";

// --- Ensure student_uid column exists ---
$uid_col = $wpdb->get_results("SHOW COLUMNS FROM {$prefix}olama_ev_records LIKE 'student_uid'");
if (empty($uid_col)) {
    $wpdb->query("ALTER TABLE {$prefix}olama_ev_records ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id");
    echo "<p style='color:green; font-weight:bold;'>✅ Added missing student_uid column to ev_records.</p>";
}

// Check for backup table
$temp_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prefix}olama_students_backup'");

if ($phase === 2 && $temp_exists) {
    echo "<h2>🔍 Phase 2: Diagnostics & Fixing</h2>";

    // --- 1. System Diagnostics ---
    echo "<div style='background:#f8f9fa; border:1px solid #ddd; padding:15px; border-radius:8px; margin-bottom:20px;'>";
    echo "<h3>📊 System Info</h3>";
    $db_version = $wpdb->get_var("SELECT VERSION()");
    $sql_mode = $wpdb->get_var("SELECT @@sql_mode");
    echo "<ul>";
    echo "<li><strong>DB Version:</strong> $db_version</li>";
    echo "<li><strong>SQL Mode:</strong> <code style='font-size:12px;'>$sql_mode</code></li>";

    $counts = [
        'Evaluation Records' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records"),
        'Students (Current)' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students"),
        'Students (Backup)' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students_backup"),
        'Orphaned Records' => $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records r LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id WHERE s.id IS NULL")
    ];

    foreach ($counts as $label => $count) {
        echo "<li><strong>$label:</strong> " . number_format($count) . "</li>";
    }
    echo "</ul>";
    echo "</div>";

    // --- 2. Step 1: Map old student_id to student_uid ---
    echo "<h3>Step 1: Mapping ID → UID</h3>";
    $wpdb->suppress_errors(false);

    // Diagnostic check before update
    $relinkable_sample = $wpdb->get_results(
        "SELECT r.student_id, s_old.student_uid 
         FROM {$prefix}olama_ev_records r 
         INNER JOIN {$prefix}olama_students_backup s_old ON r.student_id = s_old.id 
         WHERE (r.student_uid IS NULL OR r.student_uid = '')
         LIMIT 5"
    );

    if (!empty($relinkable_sample)) {
        echo "<p style='color:blue;'>ℹ️ Sample matches found in backup:</p><pre style='background:#eee; padding:10px;'>";
        print_r($relinkable_sample);
        echo "</pre>";
    } else {
        echo "<p style='color:orange;'>⚠️ No matching IDs found between ev_records and backup table for remaining orphans.</p>";
    }

    $mapped = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r 
         INNER JOIN {$prefix}olama_students_backup s_old ON r.student_id = s_old.id 
         SET r.student_uid = TRIM(s_old.student_uid) 
         WHERE r.student_id = s_old.id AND (r.student_uid IS NULL OR r.student_uid = '')"
    );

    if ($wpdb->last_error) {
        echo "<p style='color:red;'>❌ MySQL Error (Step 1): " . esc_html($wpdb->last_error) . "</p>";
    } else {
        echo "<p style='color:green;'>✅ Successfully mapped <strong>" . (int) $mapped . "</strong> records to their unique ID Number.</p>";
    }

    // --- 3. Step 2: Re-link to Current Students ---
    echo "<h3>Step 2: Linking UID → New ID</h3>";

    // Check Collation Mismatch Potential
    $coll_r = $wpdb->get_row("SHOW FULL COLUMNS FROM {$prefix}olama_ev_records LIKE 'student_uid'");
    $coll_s = $wpdb->get_row("SHOW FULL COLUMNS FROM {$prefix}olama_students LIKE 'student_uid'");

    if ($coll_r->Collation !== $coll_s->Collation) {
        echo "<p style='color:red;'>⚠️ <strong>Collation Mismatch Detected!</strong> ev_records is <code>{$coll_r->Collation}</code> while students is <code>{$coll_s->Collation}</code>. Forcing compatibility...</p>";
    }

    $relinked = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r 
         INNER JOIN {$prefix}olama_students s_new ON TRIM(LOWER(r.student_uid)) = TRIM(LOWER(s_new.student_uid)) 
         LEFT JOIN {$prefix}olama_students s_check ON r.student_id = s_check.id
         SET r.student_id = s_new.id 
         WHERE s_check.id IS NULL AND r.student_uid IS NOT NULL AND TRIM(r.student_uid) != ''"
    );

    if ($wpdb->last_error) {
        echo "<p style='color:red;'>❌ MySQL Error (Step 2): " . esc_html($wpdb->last_error) . "</p>";
    } else {
        echo "<p style='color:green;'>✅ Successfully re-linked <strong>" . (int) $relinked . "</strong> records to current students.</p>";
    }

    // --- 4. Final Verification ---
    $remaining_orphaned = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r 
         LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id 
         WHERE s.id IS NULL"
    );

    echo "<hr>";
    echo "<h3>🏁 Final Status</h3>";
    if ($remaining_orphaned == 0) {
        echo "<div style='background:#d4edda; color:#155724; padding:20px; border-radius:8px;'>";
        echo "🎉 <strong>Great Success!</strong> All evaluation records are now correctly linked to active students.";
        echo "</div>";
    } else {
        echo "<div style='background:#fff3cd; color:#856404; padding:20px; border-radius:8px;'>";
        echo "⚠️ <strong>$remaining_orphaned</strong> records are still orphaned. These student UIDs likely don't exist in the current system.";
        echo "</div>";

        // Show sample of what's left
        $leftovers = $wpdb->get_results(
            "SELECT r.student_id, r.student_uid, COUNT(*) as count 
             FROM {$prefix}olama_ev_records r 
             LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id 
             WHERE s.id IS NULL 
             GROUP BY r.student_id, r.student_uid 
             LIMIT 10"
        );
        echo "<h4>Samples of remaining orphans:</h4>";
        echo "<table border='1' style='border-collapse:collapse; width:100%;'><tr><th>Old ID</th><th>UID Found?</th><th>Record Count</th></tr>";
        foreach ($leftovers as $l) {
            echo "<tr><td>{$l->student_id}</td><td>" . ($l->student_uid ?: 'NO UID MAPPED') . "</td><td>{$l->count}</td></tr>";
        }
        echo "</table>";
    }

    echo "<p><br><a href='?phase=3' style='color:#dc3545; text-decoration:none;' onclick=\"return confirm('Drop backup table?')\">🗑️ Stop & Cleanup (Delete backup table)</a></p>";

} elseif ($phase === 3) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}olama_students_backup");
    echo "<p style='color:green; font-size:1.2em;'>✅ Backup table removed. You can safely delete this script file now.</p>";
} else {
    // Phase 1 Instruction
    echo "<h2>Phase 1: Database Check</h2>";
    $orphaned = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records r LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id WHERE s.id IS NULL");

    if ($orphaned == 0) {
        echo "<p style='color:green; font-size:1.2em;'>✅ No orphaned records found. Everything looks good!</p>";
    } else {
        echo "<p style='color:#e67e22;'>⚠️ Found <strong>$orphaned</strong> orphaned evaluation records.</p>";

        if ($temp_exists) {
            $bk_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students_backup");
            echo "<div style='background:#e3f2fd; padding:20px; border-left:5px solid #2196f3;'>";
            echo "<strong>Backup table detected!</strong> ($bk_count students)<br><br>";
            echo "<a href='?phase=2' style='background:#2196f3; color:white; padding:10px 25px; border-radius:4px; text-decoration:none; font-weight:bold;'>🚀 Start Phase 2: Run Diagnostic & Fix</a>";
            echo "</div>";
        } else {
            echo "<div style='background:#fff5f5; border:1px solid #ffcccc; padding:20px; border-radius:8px;'>";
            echo "<h3>Action Required:</h3>";
            echo "<p>You MUST import the <strong>old students table</strong> into <code>{$prefix}olama_students_backup</code> first.</p>";
            echo "<pre style='background:#222; color:#0f0; padding:15px; overflow-x:auto;'>";
            echo "CREATE TABLE {$prefix}olama_students_backup LIKE {$prefix}olama_students;\n";
            echo "-- Open your SQL backup file and run the INSERT INTO `{$prefix}olama_students` statements\n";
            echo "-- but change the table name to `{$prefix}olama_students_backup` in the SQL.";
            echo "</pre>";
            echo "<p><a href='?phase=1'>Reload this page</a> once the table is ready.</p>";
            echo "</div>";
        }
    }
}

echo "</div>";
?>