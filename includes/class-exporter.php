<?php
/**
 * Olama School Exporter Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Exporter
{
    /**
     * Export all plans to CSV
     */
    public static function export_plans_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_action')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!Olama_School_Permissions::can('olama_manage_plans_data')) {
            wp_die(__('You do not have permission to export data.', 'olama-school'));
        }

        $filename = 'olama-weekly-plans-' . date('Y-m-d') . '.csv';

        // Fetch plans data
        $plans = $wpdb->get_results("
            SELECT p.*, g.grade_name, s.section_name, sub.subject_name, u.display_name as teacher_name
            FROM {$wpdb->prefix}olama_plans p
            LEFT JOIN {$wpdb->prefix}olama_sections s ON p.section_id = s.id
            LEFT JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            LEFT JOIN {$wpdb->prefix}olama_subjects sub ON p.subject_id = sub.id
            LEFT JOIN {$wpdb->users} u ON p.teacher_id = u.ID
            ORDER BY p.plan_date DESC, p.period_number ASC
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Column headers
        fputcsv($output, array(
            __('Plan ID', 'olama-school'),
            __('Date', 'olama-school'),
            __('Period', 'olama-school'),
            __('Grade', 'olama-school'),
            __('Section', 'olama-school'),
            __('Subject', 'olama-school'),
            __('Teacher', 'olama-school'),
            __('Custom Topic', 'olama-school'),
            __('Status', 'olama-school'),
            __('Created At', 'olama-school')
        ));

        if ($plans) {
            foreach ($plans as $plan) {
                fputcsv($output, array(
                    $plan->id,
                    $plan->plan_date,
                    $plan->period_number,
                    $plan->grade_name,
                    $plan->section_name,
                    $plan->subject_name,
                    $plan->teacher_name,
                    $plan->custom_topic,
                    $plan->status,
                    $plan->created_at
                ));
            }
        }

        fclose($output);

        // Log export activity
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('data_export', sprintf('CSV export triggered for %d plans', count($plans)));
        }

        exit;
    }

    /**
     * Export curriculum (units and lessons) to CSV
     */
    public static function export_curriculum_csv($semester_id, $grade_id, $subject_id)
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_curriculum')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!Olama_School_Permissions::can('olama_manage_curriculum_list')) {
            wp_die(__('You do not have permission to export curriculum data.', 'olama-school'));
        }

        $semester_id = intval($semester_id);
        $grade_id = intval($grade_id);
        $subject_id = intval($subject_id);

        if (!$semester_id || !$grade_id || !$subject_id) {
            wp_die(__('Invalid parameters for export.', 'olama-school'));
        }

        // Fetch names for filename
        $semester_name = $wpdb->get_var($wpdb->prepare("SELECT semester_name FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
        $grade_name = $wpdb->get_var($wpdb->prepare("SELECT grade_name FROM {$wpdb->prefix}olama_grades WHERE id = %d", $grade_id));
        $subject_name = $wpdb->get_var($wpdb->prepare("SELECT subject_name FROM {$wpdb->prefix}olama_subjects WHERE id = %d", $subject_id));

        $filename = 'curriculum-' . sanitize_title($grade_name) . '-' . sanitize_title($subject_name) . '-' . date('Y-m-d') . '.csv';

        // Fetch Units and their Lessons
        $data = $wpdb->get_results($wpdb->prepare("
            SELECT u.unit_number, u.unit_name, u.objectives, l.lesson_number, l.lesson_title, l.video_url
            FROM {$wpdb->prefix}olama_curriculum_units u
            LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON u.id = l.unit_id
            WHERE u.semester_id = %d AND u.grade_id = %d AND u.subject_id = %d
            ORDER BY CAST(u.unit_number AS UNSIGNED) ASC, u.unit_number ASC, CAST(l.lesson_number AS UNSIGNED) ASC, l.lesson_number ASC
        ", $semester_id, $grade_id, $subject_id));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers
        fputcsv($output, array(
            __('Unit #', 'olama-school'),
            __('Unit Name', 'olama-school'),
            __('Objectives', 'olama-school'),
            __('Lesson #', 'olama-school'),
            __('Lesson Title', 'olama-school'),
            __('Video URL', 'olama-school')
        ));

        if ($data) {
            foreach ($data as $row) {
                fputcsv($output, array(
                    $row->unit_number,
                    $row->unit_name,
                    $row->objectives,
                    $row->lesson_number,
                    $row->lesson_title,
                    $row->video_url
                ));
            }
        }

        fclose($output);

        // Log export activity
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('curriculum_export', sprintf('Curriculum export triggered for Grade: %s, Subject: %s', $grade_name, $subject_name));
        }

        exit;
    }

    /**
     * Export all subjects to CSV
     */
    public static function export_subjects_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_subjects')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!Olama_School_Permissions::can('olama_manage_academic_subjects')) {
            wp_die(__('You do not have permission to export subjects.', 'olama-school'));
        }

        $filename = 'olama-subjects-' . date('Y-m-d') . '.csv';

        // Fetch subjects joined with grades
        $subjects = $wpdb->get_results("
            SELECT s.*, g.grade_name
            FROM {$wpdb->prefix}olama_subjects s
            LEFT JOIN {$wpdb->prefix}olama_grades g ON s.grade_id = g.id
            ORDER BY g.grade_name ASC, s.subject_name ASC
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Column headers
        fputcsv($output, array(
            __('Subject Name', 'olama-school'),
            __('Subject Code', 'olama-school'),
            __('Grade Name', 'olama-school'),
            __('Color Code', 'olama-school')
        ));

        if ($subjects) {
            foreach ($subjects as $subject) {
                fputcsv($output, array(
                    $subject->subject_name,
                    $subject->subject_code,
                    $subject->grade_name,
                    $subject->color_code
                ));
            }
        }

        fclose($output);

        // Log activity
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('subjects_export', sprintf('CSV export triggered for %d subjects', count($subjects)));
        }

        exit;
    }

    /**
     * Export all grades and their sections to CSV
     */
    public static function export_grades_sections_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_grades')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!Olama_School_Permissions::can('olama_manage_academic_grades')) {
            wp_die(__('You do not have permission to export grade data.', 'olama-school'));
        }

        $filename = 'olama-grades-sections-' . date('Y-m-d') . '.csv';

        // Fetch grades and sections
        $data = $wpdb->get_results("
            SELECT g.grade_name, g.grade_level, g.periods_count, s.section_name, s.room_number
            FROM {$wpdb->prefix}olama_grades g
            LEFT JOIN {$wpdb->prefix}olama_sections s ON g.id = s.grade_id
            ORDER BY CAST(g.grade_level AS UNSIGNED) ASC, g.grade_name ASC, s.section_name ASC
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Column headers
        fputcsv($output, array(
            __('Grade Name', 'olama-school'),
            __('Grade Level', 'olama-school'),
            __('Periods/Day', 'olama-school'),
            __('Section Name', 'olama-school'),
            __('Room Number', 'olama-school')
        ));

        if ($data) {
            foreach ($data as $row) {
                fputcsv($output, array(
                    $row->grade_name,
                    $row->grade_level,
                    $row->periods_count,
                    $row->section_name ?? '',
                    $row->room_number ?? ''
                ));
            }
        }

        fclose($output);

        // Log activity
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('grades_export', sprintf('CSV export triggered for grades and sections'));
        }

        exit;
    }

    /**
     * Export all families and their students to CSV
     */
    public static function export_families_csv()
    {
        global $wpdb;

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_families')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        if (!Olama_School_Permissions::can('olama_manage_users_families')) {
            wp_die(__('You do not have permission to export families.', 'olama-school'));
        }

        $filename = 'olama-families-' . date('Y-m-d') . '.csv';

        // Fetch families joined with students for a comprehensive export
        $data = $wpdb->get_results("
            SELECT f.*, s.student_name, s.student_uid, s.dob, s.national_id, s.gender
            FROM {$wpdb->prefix}olama_families f
            LEFT JOIN {$wpdb->prefix}olama_students s ON f.family_uid = s.family_id
            ORDER BY f.family_name ASC, s.student_name ASC
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, array(
            __('Family ID', 'olama-school'),
            __('Family Name', 'olama-school'),
            __('Father Mobile', 'olama-school'),
            __('Mother Mobile', 'olama-school'),
            __('Address', 'olama-school'),
            __('Student ID', 'olama-school'),
            __('Student Name', 'olama-school'),
            __('DOB', 'olama-school'),
            __('National ID', 'olama-school'),
            __('Sex', 'olama-school')
        ));

        if ($data) {
            foreach ($data as $row) {
                fputcsv($output, array(
                    $row->family_uid,
                    $row->family_name,
                    $row->father_mobile,
                    $row->mother_mobile,
                    $row->address,
                    $row->student_uid ?? '',
                    $row->student_name ?? '',
                    $row->dob ?? '',
                    $row->national_id ?? '',
                    $row->gender ?? ''
                ));
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export Student Enrollment Registry to CSV
     */
    public static function export_students_enrollment_csv()
    {
        global $wpdb;

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_export_students')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        if (!Olama_School_Permissions::can('olama_manage_users_students')) {
            wp_die(__('You do not have permission to export students.', 'olama-school'));
        }

        $filename = 'olama-students-enrollment-' . date('Y-m-d') . '.csv';

        // Fetch ALL students (Registry) with their LATEST enrollment info if any
        $query = "SELECT s.*, e.section_id, e.academic_year_id, e.status as enrollment_status, 
                  g.grade_name, sec.section_name, ay.year_name as academic_year_name,
                  f.family_name, f.family_uid as f_uid
                  FROM {$wpdb->prefix}olama_students s 
                  LEFT JOIN {$wpdb->prefix}olama_families f ON s.family_id = f.family_uid
                  LEFT JOIN (
                      SELECT e1.* FROM {$wpdb->prefix}olama_student_enrollment e1
                      WHERE e1.id = (SELECT MAX(id) FROM {$wpdb->prefix}olama_student_enrollment e2 WHERE e2.student_uid = e1.student_uid)
                  ) e ON s.student_uid = e.student_uid 
                  LEFT JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id 
                  LEFT JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id 
                  LEFT JOIN {$wpdb->prefix}olama_academic_years ay ON e.academic_year_id = ay.id
                  ORDER BY s.student_name ASC";

        $data = $wpdb->get_results($query);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, array(
            __('Name', 'olama-school'),
            __('ID Number', 'olama-school'),
            __('Family ID', 'olama-school'),
            __('Year', 'olama-school'),
            __('Grade', 'olama-school'),
            __('Section', 'olama-school'),
            __('Status', 'olama-school')
        ));

        if ($data) {
            foreach ($data as $row) {
                fputcsv($output, array(
                    $row->student_name,
                    $row->student_uid,
                    $row->family_id ?? '-',
                    $row->academic_year_name ?? '-',
                    $row->grade_name ?? '-',
                    $row->section_name ?? '-',
                    $row->section_id ? __('Enrolled', 'olama-school') : __('Not Enrolled', 'olama-school')
                ));
            }
        }

        fclose($output);
        exit;
    }
}