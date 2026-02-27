<?php
/**
 * Evaluation Manager (Admin UI Logic)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Manager
{
    public function render_page()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();
        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
        $selected_year = Olama_School_Academic::get_year($selected_year_id);
        $selected_year_name = $selected_year ? $selected_year->year_name : '';

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($active_semester ? $active_semester->id : (!empty($semesters) ? $semesters[0]->id : 0));
        $selected_semester = Olama_School_Academic::get_semester($selected_semester_id);
        $selected_semester_name = $selected_semester ? $selected_semester->semester_name : '';

        $templates = Olama_School_EV_Template::get_templates($selected_grade_id, $selected_year_id, $selected_semester_id);
        $selected_template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;

        $current_template = null;
        $curriculum = array();
        if ($selected_template_id) {
            $current_template = Olama_School_EV_Template::get_template($selected_template_id);
            $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($selected_template_id);
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/ev-manager.php';
    }

    /**
     * Handle Curriculum Actions (POST)
     */
    public function handle_actions()
    {
        if (!isset($_POST['olama_ev_action']) || !Olama_School_Permissions::can('olama_manage_evaluation_mgmt')) {
            return;
        }

        check_admin_referer('olama_ev_curriculum_action', 'olama_ev_curriculum_action');

        $action = sanitize_text_field($_POST['olama_ev_action']);
        $result = false;

        switch ($action) {
            case 'save_template':
                $result = Olama_School_EV_Template::save_template($_POST);
                break;
            case 'delete_template':
                $result = Olama_School_EV_Template::delete_template($_POST['id']);
                break;
            case 'save_domain':
                $result = Olama_School_EV_Curriculum::save_domain($_POST);
                break;
            case 'delete_domain':
                $result = Olama_School_EV_Curriculum::delete_domain($_POST['id']);
                break;
            case 'save_category':
                $result = Olama_School_EV_Curriculum::save_category($_POST);
                break;
            case 'delete_category':
                $result = Olama_School_EV_Curriculum::delete_category($_POST['id']);
                break;
            case 'save_indicator':
                $result = Olama_School_EV_Curriculum::save_indicator($_POST);
                break;
            case 'delete_indicator':
                $result = Olama_School_EV_Curriculum::delete_indicator($_POST['id']);
                break;
            case 'fix_old_data':
                global $wpdb;
                $active_year = Olama_School_Academic::get_active_year();
                $active_semester = Olama_School_Academic::get_active_semester();

                if ($active_year && $active_semester) {
                    $result = $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}olama_ev_templates 
                        SET academic_year_id = CASE WHEN (academic_year_id = 0 OR academic_year_id IS NULL) THEN %d ELSE academic_year_id END,
                            semester_id = CASE WHEN (semester_id = 0 OR semester_id IS NULL) THEN %d ELSE semester_id END
                        WHERE academic_year_id = 0 OR semester_id = 0 OR academic_year_id IS NULL OR semester_id IS NULL",
                        $active_year->id,
                        $active_semester->id
                    ));
                }
                break;
            case 'import_backup_data':
                global $wpdb;
                $input = isset($_POST['import_sql']) ? stripslashes(trim($_POST['import_sql'])) : '';
                $import_count = 0;
                $backup_table = $wpdb->prefix . 'olama_students_backup';

                if (!empty($input)) {
                    // Check if input is JSON
                    $json_data = json_decode($input, true);

                    if (json_last_error() === JSON_ERROR_NONE && (is_array($json_data))) {
                        // Handle JSON Import
                        $wpdb->query("TRUNCATE TABLE {$backup_table}");

                        // Detect specific export format (from user's file)
                        // This handles both [{...}, {...}] and {"tables": {"olama_students": [...]}}
                        $rows = array();
                        if (isset($json_data['tables']['olama_students'])) {
                            $rows = $json_data['tables']['olama_students'];
                        } elseif (isset($json_data[0])) {
                            $rows = $json_data;
                        } else {
                            $rows = array($json_data);
                        }

                        foreach ($rows as $row) {
                            $res = $wpdb->insert($backup_table, $row);
                            if ($res !== false) {
                                $import_count++;
                            }
                        }
                    } else {
                        // Handle SQL Import (Safe INSERT Extract)
                        // Only extract INSERT statements targeting the students table
                        preg_match_all('/INSERT INTO\s+[`"]?(\w*olama_students)[`"]?.*?;/is', $input, $matches);

                        if (!empty($matches[0])) {
                            $wpdb->query("TRUNCATE TABLE {$backup_table}");
                            foreach ($matches[0] as $stmt) {
                                // Double check it only writes to our backup table
                                $rewritten = preg_replace('/INSERT INTO\s+[`"]?(\w*olama_students)[`"]?/i', "INSERT INTO `{$backup_table}`", $stmt);
                                $res = $wpdb->query($rewritten);
                                if ($res !== false) {
                                    $import_count += $wpdb->rows_affected;
                                }
                            }
                        }
                    }
                }

                // 4. Immediately run the mapping/linking logic if we imported something
                $mapped_count = 0;
                $relinked_count = 0;
                if ($import_count > 0) {
                    $backup_table = $wpdb->prefix . 'olama_students_backup';

                    // Map IDs
                    $mapped_count = (int) $wpdb->query(
                        "UPDATE {$wpdb->prefix}olama_ev_records r 
                         INNER JOIN {$backup_table} s_old ON r.student_id = s_old.id 
                         SET r.student_uid = TRIM(s_old.student_uid)"
                    );

                    // Re-link
                    $relinked_count = (int) $wpdb->query(
                        "UPDATE {$wpdb->prefix}olama_ev_records r 
                         INNER JOIN {$wpdb->prefix}olama_students s ON TRIM(LOWER(r.student_uid)) = TRIM(LOWER(s.student_uid)) 
                         LEFT JOIN {$wpdb->prefix}olama_students s_check ON r.student_id = s_check.id
                         SET r.student_id = s.id 
                         WHERE s_check.id IS NULL AND r.student_uid IS NOT NULL AND TRIM(r.student_uid) != ''"
                    );
                }

                $result = array(
                    'status' => 'success',
                    'imported' => $import_count,
                    'mapped' => $mapped_count,
                    'relinked' => $relinked_count
                );
                break;
            case 'fix_orphaned_data':
                global $wpdb;

                // Step 0: Ensure student_uid column exists in evaluations
                $col_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records LIKE 'student_uid'");
                if (empty($col_exists)) {
                    $wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id");
                }

                // Step 1: Check/Create Backup Table
                $backup_table = $wpdb->prefix . 'olama_students_backup';
                $backup_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $backup_table));
                $backup_created = false;

                if (!$backup_exists) {
                    // Auto-create table based on current students schema
                    $wpdb->query("CREATE TABLE {$backup_table} LIKE {$wpdb->prefix}olama_students");
                    $backup_created = true;
                    $backup_exists = true;
                }

                // Step 2: Check if backup is empty
                $backup_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$backup_table}");
                if ($backup_count === 0) {
                    $result = array(
                        'status' => 'backup_empty',
                        'backup_created' => ($backup_created ? 1 : 0)
                    );
                    break;
                }

                // Step 3: Initial Count of Orphans
                $total_orphans = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}olama_ev_records r 
                     LEFT JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
                     WHERE s.id IS NULL"
                );

                // Step 4: Map IDs from Backup
                $mapped_count = (int) $wpdb->query(
                    "UPDATE {$wpdb->prefix}olama_ev_records r 
                     INNER JOIN {$backup_table} s_old ON r.student_id = s_old.id 
                     SET r.student_uid = TRIM(s_old.student_uid)"
                );

                // Step 5: Backfill from existing students for valid records missing UID
                $wpdb->query(
                    "UPDATE {$wpdb->prefix}olama_ev_records r 
                     INNER JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
                     SET r.student_uid = s.student_uid 
                     WHERE (r.student_uid IS NULL OR r.student_uid = '')"
                );

                // Step 6: Re-link orphaned records using student_uid
                $relinked_count = (int) $wpdb->query(
                    "UPDATE {$wpdb->prefix}olama_ev_records r 
                     INNER JOIN {$wpdb->prefix}olama_students s ON TRIM(LOWER(r.student_uid)) = TRIM(LOWER(s.student_uid)) 
                     LEFT JOIN {$wpdb->prefix}olama_students s_check ON r.student_id = s_check.id
                     SET r.student_id = s.id 
                     WHERE s_check.id IS NULL AND r.student_uid IS NOT NULL AND TRIM(r.student_uid) != ''"
                );

                $result = array(
                    'status' => 'success',
                    'total_orphans' => $total_orphans,
                    'mapped' => $mapped_count,
                    'relinked' => $relinked_count
                );
                break;
        }

        if ($result !== false) {
            $url = wp_get_referer() ?: admin_url('admin.php?page=olama-school-evaluation&tab=evaluation_mgmt');
            $url = remove_query_arg(array('message', 'id'), $url);

            if ($action === 'fix_old_data' || $action === 'fix_orphaned_data') {
                $msg = ($action === 'fix_orphaned_data') ? 'orphaned_fix_complete' : 'fix_complete';
                $url = admin_url('admin.php?page=olama-school-evaluation&tab=evaluation_mgmt&message=' . $msg);
                if (is_array($result)) {
                    $url = add_query_arg($result, $url);
                }
            } else {
                // Ensure we stay on the management tab
                if (strpos($url, 'tab=evaluation_mgmt') === false) {
                    $url = add_query_arg('tab', 'evaluation_mgmt', $url);
                }

                if ($action === 'save_template' && is_numeric($result)) {
                    $url = add_query_arg('template_id', $result, $url);
                } elseif (!empty($_POST['template_id'])) {
                    $url = add_query_arg('template_id', intval($_POST['template_id']), $url);
                }

                if ($action === 'delete_template') {
                    $url = remove_query_arg('template_id', $url);
                }

                $url = add_query_arg('message', 'ev_success', $url);
            }
            wp_redirect($url);
            exit;
        }
    }
}
