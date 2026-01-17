<?php
/**
 * Curriculum Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Curriculum
{

    /**
     * Get curriculum by subject and grade
     */
    public static function get_curriculum($subject_id, $grade_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum WHERE subject_id = %d AND grade_id = %d ORDER BY unit_number ASC, lesson_number ASC",
            $subject_id,
            $grade_id
        ));
    }

    /**
     * Get units for a subject and grade
     */
    public static function get_units($subject_id, $grade_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT unit_number, unit_name FROM {$wpdb->prefix}olama_curriculum WHERE subject_id = %d AND grade_id = %d ORDER BY unit_number ASC",
            $subject_id,
            $grade_id
        ));
    }

    /**
     * Get lessons for a unit
     */
    public static function get_lessons($subject_id, $grade_id, $unit_number)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_curriculum WHERE subject_id = %d AND grade_id = %d AND unit_number = %s ORDER BY lesson_number ASC",
            $subject_id,
            $grade_id,
            $unit_number
        ));
    }

    /**
     * Add curriculum item
     */
    public static function add_curriculum_item($data)
    {
        global $wpdb;
        return $wpdb->insert(
            "{$wpdb->prefix}olama_curriculum",
            array(
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'semester_id' => $data['semester_id'],
                'unit_number' => $data['unit_number'],
                'unit_name' => $data['unit_name'],
                'lesson_number' => $data['lesson_number'],
                'lesson_title' => $data['lesson_title'],
                'objectives' => $data['objectives'] ?? '',
                'pages' => $data['pages'] ?? '',
                'duration' => $data['duration'] ?? 1,
                'resources' => $data['resources'] ?? '',
            )
        );
    }

    /**
     * Get curriculum statistics for a grade and semester
     */
    public static function get_curriculum_stats($semester_id, $grade_id)
    {
        global $wpdb;
        $units_table = "{$wpdb->prefix}olama_curriculum_units";
        $lessons_table = "{$wpdb->prefix}olama_curriculum_lessons";

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.subject_id, 
                    COUNT(DISTINCT u.id) as unit_count, 
                    COUNT(l.id) as lesson_count 
             FROM $units_table u
             LEFT JOIN $lessons_table l ON u.id = l.unit_id
             WHERE u.semester_id = %d AND u.grade_id = %d 
             GROUP BY u.subject_id",
            $semester_id,
            $grade_id
        ));
    }

    /**
     * Get lesson counts for a subject name across all grades
     */
    public static function get_subject_lessons_across_grades($subject_name)
    {
        global $wpdb;
        $units_table = "{$wpdb->prefix}olama_curriculum_units";
        $lessons_table = "{$wpdb->prefix}olama_curriculum_lessons";
        $grades_table = "{$wpdb->prefix}olama_grades";
        $subjects_table = "{$wpdb->prefix}olama_subjects";

        // First find all subject IDs that have this name
        $subject_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $subjects_table WHERE subject_name = %s",
            $subject_name
        ));

        if (empty($subject_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($subject_ids), '%d'));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.grade_name, COUNT(l.id) as lesson_count 
             FROM $units_table u
             JOIN $lessons_table l ON u.id = l.unit_id
             JOIN $grades_table g ON u.grade_id = g.id
             WHERE u.subject_id IN ($placeholders)
             GROUP BY u.grade_id
             ORDER BY g.grade_level ASC",
            ...$subject_ids
        ));
    }
}