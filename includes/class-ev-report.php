<?php
/**
 * School Evaluation Report Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_EV_Report
{
    public static function render_report($evaluation_id)
    {
        global $wpdb;
        $evaluation = $wpdb->get_row($wpdb->prepare(
            "SELECT e.*, s.student_name, s.student_uid, y.year_name, sem.semester_name, g.grade_name, sec.grade_id
             FROM {$wpdb->prefix}olama_ev_records e
             JOIN {$wpdb->prefix}olama_students s ON e.student_id = s.id
             JOIN {$wpdb->prefix}olama_student_enrollment en ON s.id = en.student_id AND e.academic_year_id = en.academic_year_id
             JOIN {$wpdb->prefix}olama_sections sec ON en.section_id = sec.id
             JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
             JOIN {$wpdb->prefix}olama_academic_years y ON e.academic_year_id = y.id
             JOIN {$wpdb->prefix}olama_semesters sem ON e.semester_id = sem.id
             WHERE e.id = %d",
            $evaluation_id
        ));

        if (!$evaluation) {
            wp_die('Evaluation not found.');
        }

        $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($evaluation->template_id);
        $scores = Olama_School_EV_Record::get_scores($evaluation_id);

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/ev-report-print.php';
    }
}
