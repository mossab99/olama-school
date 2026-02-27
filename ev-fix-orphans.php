<?php
/**
 * Fix Orphaned Evaluations — Multi-Strategy Recovery Tool
 *
 * Strategy 1 — student_uid match       (preferred, needs uid in ev_records)
 * Strategy 2 — Name match              (matches via student_name stored in ev_records)
 * Strategy 3 — Manual enrollment map   (uses year+teacher context to suggest candidates)
 *
 * PHASES:
 *  default / diagnostic → Stats + sample orphans
 *  ?phase=backfill      → Fill student_uid + student_name from live students (if student_id match exists)
 *  ?phase=fix           → Re-link orphans via student_uid
 *  ?phase=namematch     → Preview & commit name-based matches (unambiguous only)
 *  ?phase=namefix       → Commit name fixes (?confirm=1)
 *  ?phase=context       → Show year+teacher context groups + enrolled students (manual mapping helper)
 *  ?phase=manualfix     → Apply a manual student_id → new_student_id mapping from POST data
 *  ?phase=report        → Show remaining unresolvable orphans
 */

require_once dirname(__FILE__) . '/../../../wp-load.php';

global $wpdb;

$phase = isset($_GET['phase']) ? sanitize_key($_GET['phase']) : 'diagnostic';
$confirm = isset($_GET['confirm']) ? intval($_GET['confirm']) : 0;
$prefix = $wpdb->prefix;

// ── Helpers ───────────────────────────────────────────────────────────────────

function ev_orphan_count($wpdb, $prefix)
{
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r
		 LEFT JOIN {$prefix}olama_students s ON r.student_id = s.id
		 WHERE s.id IS NULL"
    );
}

// ── Ensure columns exist ──────────────────────────────────────────────────────
foreach ([
    "student_uid  varchar(50)  DEFAULT NULL AFTER student_id",
    "student_name varchar(100) DEFAULT NULL AFTER student_uid",
] as $col_def) {
    $col_name = explode(' ', trim($col_def))[0];
    if (empty($wpdb->get_results("SHOW COLUMNS FROM {$prefix}olama_ev_records LIKE '$col_name'"))) {
        $wpdb->query("ALTER TABLE {$prefix}olama_ev_records ADD COLUMN {$col_def}");
    }
}
if (empty($wpdb->get_results("SHOW INDEX FROM {$prefix}olama_ev_records WHERE Key_name = 'student_uid'"))) {
    $wpdb->query("ALTER TABLE {$prefix}olama_ev_records ADD KEY student_uid (student_uid)");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fix Orphaned Evaluations</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f2f5;margin:0;padding:30px}
  .wrap{max-width:980px;margin:0 auto}
  h1{color:#1a2332;margin-bottom:4px}h2{color:#2c3e50;border-bottom:2px solid #e0e5eb;padding-bottom:8px}h3{color:#34495e;margin-bottom:8px}
  .card{background:#fff;border:1px solid #dde3ec;border-radius:10px;padding:22px 26px;margin:18px 0}
  .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin:14px 0}
  .stat{background:#f7f9fc;border:1px solid #e0e6ef;border-radius:8px;padding:14px 16px;text-align:center}
  .stat .num{font-size:2em;font-weight:700;color:#2563eb}.stat .lbl{font-size:.8em;color:#6b7280;margin-top:4px}
  .stat.warn .num{color:#d97706}.stat.danger .num{color:#dc2626}.stat.ok .num{color:#16a34a}
  .btn{display:inline-block;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.9em;cursor:pointer;border:none}
  .btn-primary{background:#2563eb;color:#fff}.btn-primary:hover{background:#1d4ed8}
  .btn-success{background:#16a34a;color:#fff}.btn-success:hover{background:#15803d}
  .btn-warning{background:#d97706;color:#fff}.btn-warning:hover{background:#b45309}
  .btn-danger{background:#dc2626;color:#fff}.btn-danger:hover{background:#b91c1c}
  .btn-secondary{background:#6b7280;color:#fff}.btn-secondary:hover{background:#4b5563}
  .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
  table{width:100%;border-collapse:collapse;font-size:.87em}
  th{background:#f1f5f9;color:#475569;padding:9px 11px;text-align:left;border-bottom:2px solid #dde3ec}
  td{padding:8px 11px;border-bottom:1px solid #f1f5f9;color:#374151;vertical-align:middle}
  tr:hover td{background:#f8fafc}
  .tag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.77em;font-weight:600}
  .tag-ok{background:#dcfce7;color:#15803d}.tag-miss{background:#fee2e2;color:#b91c1c}
  .tag-warn{background:#fef9c3;color:#92400e}.tag-blue{background:#dbeafe;color:#1d4ed8}
  .alert{padding:12px 16px;border-radius:8px;margin:10px 0;border-left:4px solid}
  .alert-info{background:#eff6ff;border-color:#3b82f6;color:#1e40af}
  .alert-warn{background:#fffbeb;border-color:#f59e0b;color:#92400e}
  .alert-ok{background:#f0fdf4;border-color:#22c55e;color:#15803d}
  .alert-error{background:#fef2f2;border-color:#ef4444;color:#b91c1c}
  .strategy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:12px;margin:14px 0}
  .strategy{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:15px 17px}
  .strategy.active{border-color:#2563eb;background:#eff6ff}
  .strategy h4{margin:0 0 5px;font-size:.97em;color:#1e293b}
  .strategy p{margin:0 0 10px;color:#64748b;font-size:.83em;line-height:1.5}
  .confirm-box{background:#fef9c3;border:2px solid #f59e0b;border-radius:8px;padding:14px 18px;margin:14px 0}
  .ctx-group{border:1px solid #e2e8f0;border-radius:8px;margin:14px 0;overflow:hidden}
  .ctx-header{background:#f1f5f9;padding:12px 16px;font-weight:600;color:#1e293b;border-bottom:1px solid #e2e8f0}
  .ctx-body{padding:14px 16px}
  select.map-sel{width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:.87em}
  .badge{display:inline-block;background:#2563eb;color:#fff;border-radius:20px;padding:2px 10px;font-size:.78em;font-weight:700;margin-left:6px}
</style>
</head>
<body>
<div class="wrap">
<h1>🛠️ Fix Orphaned Evaluations</h1>
<p style="color:#6b7280;margin-top:0;">Multi-strategy recovery: UID → Name → Manual enrollment mapping</p>

<?php

// ─── PHASE: BACKFILL ─────────────────────────────────────────────────────────
if ($phase === 'backfill') {

    echo "<h2>Step 1 — Backfill from Live Students</h2><div class='card'>";
    echo "<div class='alert alert-info'>ℹ️ Attempts to copy <code>student_uid</code> and <code>student_name</code> into ev_records where the old <code>student_id</code> still exists in the current students table.</div>";

    $bf_uid = $wpdb->query("UPDATE {$prefix}olama_ev_records r INNER JOIN {$prefix}olama_students s ON r.student_id = s.id SET r.student_uid = s.student_uid WHERE (r.student_uid IS NULL OR TRIM(r.student_uid) = '')");
    $bf_name = $wpdb->query("UPDATE {$prefix}olama_ev_records r INNER JOIN {$prefix}olama_students s ON r.student_id = s.id SET r.student_name = s.student_name WHERE (r.student_name IS NULL OR TRIM(r.student_name) = '')");

    echo "<div class='alert " . ($bf_uid > 0 || $bf_name > 0 ? 'alert-ok' : 'alert-warn') . "'>
		Backfilled <strong>{$bf_uid}</strong> student_uid value(s) and <strong>{$bf_name}</strong> student_name value(s).
		" . ($bf_uid == 0 && $bf_name == 0 ? "<br>⚠️ Zero backfilled — old student_id values don't match current students table IDs. Proceed to Name Match or Manual Map." : "") . "
	</div>";

    echo "<div class='actions'>
		<a href='?phase=namematch' class='btn btn-warning'>🔤 Name Match</a>
		<a href='?phase=context' class='btn btn-primary'>🗂 Manual Enrollment Map</a>
		<a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a>
	</div></div>";

    // ─── PHASE: UID FIX ──────────────────────────────────────────────────────────
} elseif ($phase === 'fix') {

    echo "<h2>Re-linking via student_uid</h2><div class='card'>";

    $coll_r = $wpdb->get_row("SHOW FULL COLUMNS FROM {$prefix}olama_ev_records LIKE 'student_uid'");
    $coll_s = $wpdb->get_row("SHOW FULL COLUMNS FROM {$prefix}olama_students LIKE 'student_uid'");
    $join = ($coll_r && $coll_s && $coll_r->Collation !== $coll_s->Collation)
        ? "TRIM(LOWER(r.student_uid)) = TRIM(LOWER(s.student_uid))"
        : "TRIM(r.student_uid) = TRIM(s.student_uid)";

    $fixed = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r
		 INNER JOIN {$prefix}olama_students s ON {$join}
		 LEFT  JOIN {$prefix}olama_students x ON r.student_id = x.id
		 SET r.student_id = s.id, r.student_name = s.student_name
		 WHERE x.id IS NULL AND r.student_uid IS NOT NULL AND TRIM(r.student_uid) != ''"
    );

    $remaining = ev_orphan_count($wpdb, $prefix);
    if ($wpdb->last_error) {
        echo "<div class='alert alert-error'>❌ " . esc_html($wpdb->last_error) . "</div>";
    } else {
        echo "<div class='alert alert-ok'>✅ Re-linked <strong>{$fixed}</strong> record(s) via student_uid. Remaining orphans: <strong>{$remaining}</strong></div>";
        if ($remaining > 0) {
            echo "<div class='actions'>
				<a href='?phase=namematch' class='btn btn-warning'>🔤 Try Name Match</a>
				<a href='?phase=context' class='btn btn-primary'>🗂 Manual Map</a>
			</div>";
        }
    }
    echo "<div class='actions'><a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a></div></div>";

    // ─── PHASE: NAME MATCH — preview ─────────────────────────────────────────────
} elseif ($phase === 'namematch') {

    echo "<h2>🔤 Strategy 2: Name Match — Preview</h2><div class='card'>";
    echo "<div class='alert alert-info'>ℹ️ Matches orphaned ev_records to students by the <code>student_name</code> stored in the record (from backfill or original data). Only <strong>exact, unambiguous</strong> single-name matches are applied.</div>";

    // Check if window function syntax (MySQL 8+) is available; fallback to subquery
    $db_version = $wpdb->get_var("SELECT VERSION()");
    $is_mysql8 = version_compare($db_version, '8.0', '>=');

    if ($is_mysql8) {
        $candidates = $wpdb->get_results(
            "SELECT r.id AS rec_id, r.student_id AS old_id, r.student_name,
			        s.id AS new_student_id, s.student_uid AS new_uid, s.student_name AS cur_name,
			        COUNT(s.id) OVER (PARTITION BY TRIM(LOWER(r.student_name))) AS match_count
			 FROM {$prefix}olama_ev_records r
			 LEFT  JOIN {$prefix}olama_students x ON r.student_id = x.id
			 INNER JOIN {$prefix}olama_students s ON TRIM(LOWER(r.student_name)) = TRIM(LOWER(s.student_name))
			 WHERE x.id IS NULL AND r.student_name IS NOT NULL AND TRIM(r.student_name) != ''
			 ORDER BY r.student_name LIMIT 500"
        );
    } else {
        // MySQL 5.7-compatible
        $candidates = $wpdb->get_results(
            "SELECT r.id AS rec_id, r.student_id AS old_id, r.student_name,
			        s.id AS new_student_id, s.student_uid AS new_uid, s.student_name AS cur_name,
			        (SELECT COUNT(*) FROM {$prefix}olama_students s3 WHERE TRIM(LOWER(s3.student_name)) = TRIM(LOWER(r.student_name))) AS match_count
			 FROM {$prefix}olama_ev_records r
			 LEFT  JOIN {$prefix}olama_students x ON r.student_id = x.id
			 INNER JOIN {$prefix}olama_students s ON TRIM(LOWER(r.student_name)) = TRIM(LOWER(s.student_name))
			 WHERE x.id IS NULL AND r.student_name IS NOT NULL AND TRIM(r.student_name) != ''
			 ORDER BY r.student_name LIMIT 500"
        );
    }

    $safe = array_values(array_filter($candidates, fn($c) => (int) $c->match_count === 1));
    $ambig = array_values(array_filter($candidates, fn($c) => (int) $c->match_count > 1));
    $no_name = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r
		 LEFT JOIN {$prefix}olama_students x ON r.student_id = x.id
		 WHERE x.id IS NULL AND (r.student_name IS NULL OR TRIM(r.student_name) = '')"
    );

    echo "<div class='stat-grid'>";
    echo "<div class='stat ok'><div class='num'>" . count($safe) . "</div><div class='lbl'>Safe to fix</div></div>";
    echo "<div class='stat warn'><div class='num'>" . count($ambig) . "</div><div class='lbl'>Ambiguous</div></div>";
    echo "<div class='stat danger'><div class='num'>{$no_name}</div><div class='lbl'>No name stored</div></div>";
    echo "</div>";

    if (empty($safe)) {
        echo "<div class='alert alert-warn'>⚠️ No unambiguous name matches. Either the names aren't stored in ev_records yet (run Backfill) or the student names changed between imports.</div>";
        if ($no_name > 0) {
            echo "<p>👉 Try <a href='?phase=context' class='btn btn-primary' style='display:inline;padding:6px 14px;'>🗂 Manual Enrollment Map</a> to assign students by context (year/teacher/section).</p>";
        }
    } else {
        echo "<h3>Safe Matches <span class='badge'>" . count($safe) . "</span></h3>
			<table>
			<thead><tr>
				<th>Rec ID</th><th>Name in Record</th><th>→</th>
				<th>Matched Student</th><th>New ID</th><th>UID</th>
			</tr></thead><tbody>";
        foreach ($safe as $c) {
            echo "<tr>
				<td><code>{$c->rec_id}</code></td>
				<td>" . esc_html($c->student_name) . "</td>
				<td style='text-align:center;color:#94a3b8'>→</td>
				<td>" . esc_html($c->cur_name) . "</td>
				<td><code>{$c->new_student_id}</code></td>
				<td><code>" . esc_html($c->new_uid) . "</code></td>
			</tr>";
        }
        echo "</tbody></table>";

        if (!empty($ambig)) {
            $ambig_names = array_unique(array_column($ambig, 'student_name'));
            echo "<div class='alert alert-warn'>⚠️ " . count($ambig) . " record(s) with ambiguous names (multiple students share the name) were excluded: "
                . implode(', ', array_map('esc_html', $ambig_names)) . "</div>";
        }

        echo "<div class='confirm-box'><strong>⚠️ Review the matches above.</strong> Name matching is exact but assumes names didn't change between old and new import.</div>";
        echo "<div class='actions'>
			<a href='?phase=namefix&confirm=1' class='btn btn-danger'>✅ Apply Name Fix (" . count($safe) . " records)</a>
			<a href='?phase=context' class='btn btn-primary'>🗂 Manual Map for the Rest</a>
			<a href='?phase=diagnostic' class='btn btn-secondary'>← Cancel</a>
		</div>";
    }
    echo "</div>";

    // ─── PHASE: NAME FIX — commit ─────────────────────────────────────────────────
} elseif ($phase === 'namefix' && $confirm === 1) {

    echo "<h2>🔤 Applying Name Fix</h2><div class='card'>";

    $fixed = $wpdb->query(
        "UPDATE {$prefix}olama_ev_records r
		 INNER JOIN (
		   SELECT r2.id AS rec_id, s2.id AS new_sid, s2.student_uid AS new_uid, s2.student_name AS new_name
		   FROM {$prefix}olama_ev_records r2
		   LEFT  JOIN {$prefix}olama_students x ON r2.student_id = x.id
		   INNER JOIN {$prefix}olama_students s2 ON TRIM(LOWER(r2.student_name)) = TRIM(LOWER(s2.student_name))
		   WHERE x.id IS NULL AND r2.student_name IS NOT NULL AND TRIM(r2.student_name) != ''
		   GROUP BY r2.id HAVING COUNT(s2.id) = 1
		 ) AS m ON r.id = m.rec_id
		 SET r.student_id = m.new_sid, r.student_uid = m.new_uid, r.student_name = m.new_name"
    );

    $remaining = ev_orphan_count($wpdb, $prefix);
    if ($wpdb->last_error) {
        echo "<div class='alert alert-error'>❌ " . esc_html($wpdb->last_error) . "</div>";
    } else {
        echo "<div class='alert alert-ok'>✅ Re-linked <strong>{$fixed}</strong> record(s) by name. Remaining orphans: <strong>{$remaining}</strong></div>";
        if ($remaining > 0) {
            echo "<div class='actions'><a href='?phase=context' class='btn btn-primary'>🗂 Manual Enrollment Map</a></div>";
        } else {
            echo "<div class='alert alert-ok' style='font-size:1.05em;'>🎉 All evaluation records are now correctly linked!</div>";
        }
    }
    echo "<div class='actions'><a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a></div></div>";

    // ─── PHASE: CONTEXT — enrollment-based manual mapping helper ─────────────────
} elseif ($phase === 'context') {

    echo "<h2>🗂 Strategy 3: Manual Enrollment Mapping</h2><div class='card'>";
    echo "<div class='alert alert-info'>
		ℹ️ Groups orphaned evaluations by <strong>Academic Year + Teacher</strong>.
		For each group, shows which students were <strong>actually enrolled</strong> in that teacher's sections during that year.
		Use the dropdowns to assign each old student_id to the correct current student, then click <strong>Apply Mapping</strong>.
	</div>";

    // Get distinct orphan groups: (academic_year_id, teacher_id, old student_id)
    $groups = $wpdb->get_results(
        "SELECT r.academic_year_id, r.teacher_id,
		        ay.year_name,
		        u.display_name AS teacher_name,
		        COUNT(DISTINCT r.student_id) AS orphan_student_count,
		        COUNT(r.id) AS orphan_ev_count,
		        GROUP_CONCAT(DISTINCT r.student_id ORDER BY r.student_id) AS old_student_ids
		 FROM {$prefix}olama_ev_records r
		 LEFT JOIN {$prefix}olama_students x ON r.student_id = x.id
		 LEFT JOIN {$prefix}olama_academic_years ay ON r.academic_year_id = ay.id
		 LEFT JOIN {$prefix}users u ON r.teacher_id = u.ID
		 WHERE x.id IS NULL
		 GROUP BY r.academic_year_id, r.teacher_id
		 ORDER BY ay.year_name, u.display_name"
    );

    if (empty($groups)) {
        echo "<div class='alert alert-ok'>🎉 No orphaned records! Nothing to map.</div>";
        echo "<div class='actions'><a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a></div></div>";
    } else {
        echo "<form method='POST' action='?phase=manualfix'>";

        foreach ($groups as $g) {
            $old_ids = explode(',', $g->old_student_ids);

            // Find students currently enrolled under this teacher's sections in this year
            $enrolled_students = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT s.id, s.student_name, s.student_uid,
				        sec.section_name, gr.grade_name
				 FROM {$prefix}olama_students s
				 JOIN {$prefix}olama_student_enrollment e ON s.id = e.student_id
				 JOIN {$prefix}olama_sections sec ON e.section_id = sec.id
				 JOIN {$prefix}olama_grades gr ON sec.grade_id = gr.id
				 JOIN {$prefix}olama_teacher_assignments ta
				      ON ta.section_id = e.section_id AND ta.academic_year_id = e.academic_year_id
				 WHERE e.academic_year_id = %d AND ta.teacher_id = %d
				 ORDER BY gr.grade_name, sec.section_name, s.student_name",
                $g->academic_year_id,
                $g->teacher_id
            ));

            // Also include ALL students as fallback
            $all_students = $wpdb->get_results(
                "SELECT id, student_name, student_uid FROM {$prefix}olama_students ORDER BY student_name"
            );

            $year_label = esc_html($g->year_name ?: "Year #{$g->academic_year_id}");
            $teacher_label = esc_html($g->teacher_name ?: "Teacher #{$g->teacher_id}");

            echo "<div class='ctx-group'>
				<div class='ctx-header'>
					{$year_label} — {$teacher_label}
					<span style='font-weight:400;color:#64748b;font-size:.88em;'>
						({$g->orphan_student_count} old student(s), {$g->orphan_ev_count} evaluation record(s))
					</span>
				</div>
				<div class='ctx-body'>";

            echo "<table>
				<thead><tr>
					<th>Old student_id (orphaned)</th>
					<th># Evaluations</th>
					<th>Map to Current Student</th>
				</tr></thead><tbody>";

            foreach ($old_ids as $old_id) {
                $old_id = (int) $old_id;
                $ev_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$prefix}olama_ev_records WHERE student_id = %d",
                    $old_id
                ));

                echo "<tr>
					<td><code>{$old_id}</code></td>
					<td>{$ev_count}</td>
					<td>
						<select class='map-sel' name='map[{$old_id}]'>
							<option value=''>— Skip (do not remap) —</option>";

                if (!empty($enrolled_students)) {
                    echo "<optgroup label='📚 Enrolled in this teacher\'s sections'>";
                    foreach ($enrolled_students as $st) {
                        echo "<option value='" . (int) $st->id . "'>"
                            . esc_html("{$st->student_name} [{$st->student_uid}] — {$st->grade_name} / {$st->section_name}")
                            . "</option>";
                    }
                    echo "</optgroup>";
                }

                echo "<optgroup label='— All students (fallback) —'>";
                foreach ($all_students as $st) {
                    echo "<option value='" . (int) $st->id . "'>"
                        . esc_html("{$st->student_name} [{$st->student_uid}]")
                        . "</option>";
                }
                echo "</optgroup></select>
					</td>
				</tr>";
            }

            echo "</tbody></table></div></div>";
        }

        echo "<div class='confirm-box'><strong>⚠️ Double-check your selections.</strong> The tool cannot auto-verify name accuracy for manual mappings.</div>";
        echo "<div class='actions'>
			<button type='submit' class='btn btn-danger'>✅ Apply Manual Mapping</button>
			<a href='?phase=diagnostic' class='btn btn-secondary'>← Cancel</a>
		</div>";
        echo "</form>";
    }
    echo "</div>";

    // ─── PHASE: MANUAL FIX — apply POST data ─────────────────────────────────────
} elseif ($phase === 'manualfix' && !empty($_POST['map'])) {

    echo "<h2>Applying Manual Mapping</h2><div class='card'>";

    $fixed = 0;
    $skipped = 0;
    $errors = [];

    foreach ($_POST['map'] as $old_id => $new_id) {
        $old_id = (int) $old_id;
        $new_id = (int) $new_id;

        if ($new_id <= 0) {
            $skipped++;
            continue;
        }

        // Confirm new_id is a valid student
        $valid = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}olama_students WHERE id = %d", $new_id));
        if (!$valid) {
            $errors[] = "Student ID {$new_id} not found.";
            continue;
        }

        // Get the new student's uid and name
        $new_student = $wpdb->get_row($wpdb->prepare(
            "SELECT student_uid, student_name FROM {$prefix}olama_students WHERE id = %d",
            $new_id
        ));

        $rows = $wpdb->query($wpdb->prepare(
            "UPDATE {$prefix}olama_ev_records
			 SET student_id   = %d,
			     student_uid  = %s,
			     student_name = %s
			 WHERE student_id = %d",
            $new_id,
            $new_student->student_uid,
            $new_student->student_name,
            $old_id
        ));

        if ($wpdb->last_error) {
            $errors[] = "Error remapping old ID {$old_id}: " . $wpdb->last_error;
        } else {
            $fixed += $rows;
        }
    }

    $remaining = ev_orphan_count($wpdb, $prefix);
    echo "<div class='alert alert-ok'>✅ Applied mapping: <strong>{$fixed}</strong> record(s) updated. Skipped: <strong>{$skipped}</strong>.</div>";
    if (!empty($errors)) {
        echo "<div class='alert alert-error'>Errors:<ul>" . implode('', array_map(fn($e) => "<li>" . esc_html($e) . "</li>", $errors)) . "</ul></div>";
    }
    echo "<p>Remaining orphans: <strong>{$remaining}</strong></p>";
    if ($remaining === 0) {
        echo "<div class='alert alert-ok' style='font-size:1.05em;'>🎉 All evaluation records are now correctly linked!</div>";
    } else {
        echo "<div class='actions'><a href='?phase=context' class='btn btn-primary'>🗂 Continue Mapping</a><a href='?phase=report' class='btn btn-secondary'>📋 View Remaining</a></div>";
    }
    echo "<div class='actions'><a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a></div></div>";

    // ─── PHASE: REPORT ────────────────────────────────────────────────────────────
} elseif ($phase === 'report') {

    echo "<h2>📋 Remaining Orphans</h2><div class='card'>";
    $rows = $wpdb->get_results(
        "SELECT r.student_id, r.student_uid, r.student_name,
		        COUNT(*) AS ev_count, MAX(r.created_at) AS last_seen,
		        ay.year_name, u.display_name AS teacher_name
		 FROM {$prefix}olama_ev_records r
		 LEFT JOIN {$prefix}olama_students x ON r.student_id = x.id
		 LEFT JOIN {$prefix}olama_academic_years ay ON r.academic_year_id = ay.id
		 LEFT JOIN {$prefix}users u ON r.teacher_id = u.ID
		 WHERE x.id IS NULL
		 GROUP BY r.student_id, r.student_uid, r.student_name, ay.year_name, u.display_name
		 ORDER BY ev_count DESC LIMIT 100"
    );

    if (empty($rows)) {
        echo "<div class='alert alert-ok'>🎉 No orphans remaining!</div>";
    } else {
        echo "<table>
			<thead><tr>
				<th>Old ID</th><th>student_uid</th><th>student_name</th>
				<th>Year</th><th>Teacher</th><th># Records</th><th>Last</th>
			</tr></thead><tbody>";
        foreach ($rows as $r) {
            echo "<tr>
				<td><code>{$r->student_id}</code></td>
				<td><code>" . esc_html($r->student_uid ?: '—') . "</code></td>
				<td>" . esc_html($r->student_name ?: '—') . "</td>
				<td>" . esc_html($r->year_name ?: '—') . "</td>
				<td>" . esc_html($r->teacher_name ?: '—') . "</td>
				<td><strong>{$r->ev_count}</strong></td>
				<td>" . esc_html($r->last_seen) . "</td>
			</tr>";
        }
        echo "</tbody></table>";
    }
    echo "<div class='actions'>
		<a href='?phase=context' class='btn btn-primary'>🗂 Manual Mapping</a>
		<a href='?phase=diagnostic' class='btn btn-secondary'>← Diagnostic</a>
	</div></div>";

    // ─── PHASE: DIAGNOSTIC ────────────────────────────────────────────────────────
} else {

    $total_s = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_students");
    $total_r = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records");
    $orphans = ev_orphan_count($wpdb, $prefix);
    $uid_rec = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prefix}olama_ev_records r
		 LEFT  JOIN {$prefix}olama_students x ON r.student_id = x.id
		 INNER JOIN {$prefix}olama_students s2 ON TRIM(LOWER(r.student_uid)) = TRIM(LOWER(s2.student_uid))
		 WHERE x.id IS NULL AND r.student_uid IS NOT NULL AND TRIM(r.student_uid) != ''"
    );
    $name_rec = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT r.id) FROM {$prefix}olama_ev_records r
		 LEFT  JOIN {$prefix}olama_students x ON r.student_id = x.id
		 INNER JOIN {$prefix}olama_students s2 ON TRIM(LOWER(r.student_name)) = TRIM(LOWER(s2.student_name))
		 WHERE x.id IS NULL AND r.student_name IS NOT NULL AND TRIM(r.student_name) != ''
		   AND (SELECT COUNT(*) FROM {$prefix}olama_students WHERE LOWER(student_name) = LOWER(r.student_name)) = 1"
    ) ?: 0;
    $no_uid = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}olama_ev_records WHERE student_uid IS NULL OR TRIM(student_uid) = ''");

    $oc = $orphans === 0 ? 'ok' : ($orphans < 20 ? 'warn' : 'danger');
    echo "<h2>📊 Diagnostic Report</h2><div class='card'>";
    echo "<div class='stat-grid'>";
    echo "<div class='stat'><div class='num'>{$total_s}</div><div class='lbl'>Students</div></div>";
    echo "<div class='stat'><div class='num'>{$total_r}</div><div class='lbl'>Evaluation Records</div></div>";
    echo "<div class='stat {$oc}'><div class='num'>{$orphans}</div><div class='lbl'>Orphaned</div></div>";
    echo "<div class='stat " . ($uid_rec > 0 ? 'ok' : 'warn') . "'><div class='num'>{$uid_rec}</div><div class='lbl'>UID Recoverable</div></div>";
    echo "<div class='stat " . ($name_rec > 0 ? 'ok' : 'warn') . "'><div class='num'>{$name_rec}</div><div class='lbl'>Name Recoverable</div></div>";
    echo "</div>";

    if ($orphans === 0) {
        echo "<div class='alert alert-ok' style='font-size:1.05em;'>✅ <strong>All clear!</strong> Every evaluation record is correctly linked.</div>";
    } else {
        echo "<h3>Recovery Strategies</h3><div class='strategy-grid'>";

        echo "<div class='strategy" . ($no_uid > 0 ? ' active' : '') . "'>
			<h4>1️⃣ Backfill UIDs &amp; Names</h4>
			<p>Pulls <code>student_uid</code> and <code>student_name</code> into ev_records where the <code>student_id</code> still exists in the current students table. Run this first.</p>
			<a href='?phase=backfill' class='btn btn-primary'>Run Backfill</a>
		</div>";

        echo "<div class='strategy" . ($uid_rec > 0 ? ' active' : '') . "'>
			<h4>2️⃣ Fix via student_uid</h4>
			<p>Re-links records by matching the school ID number (<code>student_uid</code>) stored in ev_records to the current students table.</p>
			" . ($uid_rec > 0 ? "<a href='?phase=fix' class='btn btn-success'>▶ Run UID Fix</a>" : "<span class='tag tag-miss'>No UID matches</span>") . "
		</div>";

        echo "<div class='strategy" . ($name_rec > 0 ? ' active' : '') . "'>
			<h4>3️⃣ Fix via Student Name</h4>
			<p>Matches by <code>student_name</code> stored in ev_records against current students. Only exact, unambiguous matches are applied (preview shown first).</p>
			" . ($name_rec > 0 ? "<a href='?phase=namematch' class='btn btn-warning'>🔤 Preview Name Match</a>" : "<span class='tag tag-miss'>No matches (run Backfill first, or names differ)</span>") . "
		</div>";

        echo "<div class='strategy active'>
			<h4>🗂 Manual Enrollment Map</h4>
			<p>Groups orphaned records by <strong>Academic Year + Teacher</strong> and shows enrolled students in their sections. Use dropdowns to manually assign old student_id → current student.</p>
			<a href='?phase=context' class='btn btn-primary'>Open Manual Map</a>
		</div>";

        echo "</div>"; // .strategy-grid

        // Sample orphans table
        $samples = $wpdb->get_results(
            "SELECT r.id, r.student_id, r.student_uid, r.student_name, r.created_at
			 FROM {$prefix}olama_ev_records r
			 LEFT JOIN {$prefix}olama_students x ON r.student_id = x.id
			 WHERE x.id IS NULL LIMIT 8"
        );
        echo "<h3>Sample Orphaned Records</h3>
			<table><thead><tr><th>Rec ID</th><th>student_id</th><th>student_uid</th><th>student_name</th><th>Created</th></tr></thead><tbody>";
        foreach ($samples as $r) {
            $uid_t = $r->student_uid ? "<code>" . esc_html($r->student_uid) . "</code>" : "<span class='tag tag-miss'>none</span>";
            $name_t = $r->student_name ? "<code>" . esc_html($r->student_name) . "</code>" : "<span class='tag tag-miss'>none</span>";
            echo "<tr><td>{$r->id}</td><td><code>{$r->student_id}</code></td><td>{$uid_t}</td><td>{$name_t}</td><td>" . esc_html($r->created_at) . "</td></tr>";
        }
        echo "</tbody></table>";
        echo "<div class='actions'><a href='?phase=report' class='btn btn-secondary'>📋 Full Orphan Report</a></div>";
    }
    echo "</div>";
}
?>
</div>
</body>
</html>