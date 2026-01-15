<?php
/**
 * Weekly Plan Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Plan
{

    /**
     * Get plans by section and date range
     */
    public static function get_plans($section_id, $start_date, $end_date)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, s.subject_name, s.color_code, u.unit_number, u.unit_name, l.lesson_number, l.lesson_title, l.start_date as lesson_start_date, l.end_date as lesson_end_date 
            FROM {$wpdb->prefix}olama_plans p 
            LEFT JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id 
            LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id 
            LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id 
            WHERE p.section_id = %d AND p.plan_date BETWEEN %s AND %s 
            ORDER BY p.plan_date ASC, p.period_number ASC",
            $section_id,
            $start_date,
            $end_date
        ));
    }

    /**
     * Add/Update weekly plan
     */
    public static function save_plan($data)
    {
        global $wpdb;

        $table = "{$wpdb->prefix}olama_plans";
        $plan_id = isset($data['plan_id']) ? intval($data['plan_id']) : 0;

        $section_id = intval($data['section_id']);
        $plan_date = $data['plan_date'];
        $period_number = intval($data['period_number']);

        $subject_id = intval($data['subject_id']);

        // Limit Validation
        if (!$plan_id) {
            // Find week range (Sunday to Thursday)
            $ts = strtotime($plan_date);
            $day_of_week = date('w', $ts);
            $week_start = date('Y-m-d', $ts - ($day_of_week * 86400));
            $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));

            // Get Grade Limit
            $section = Olama_School_Section::get_section($section_id);
            $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;
            $grade_limit = $grade ? intval($grade->max_weekly_plans) : 0;

            if ($grade_limit > 0) {
                // Check Weekly Limit
                $total_plans = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE section_id = %d AND plan_date BETWEEN %s AND %s",
                    $section_id,
                    $week_start,
                    $week_end
                ));
                if ($total_plans >= $grade_limit) {
                    return new WP_Error('limit_reached', sprintf(__('Weekly limit reached for this grade (%d plans).', 'olama-school'), $grade_limit));
                }

                // Check Daily Limit
                $day_map = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu'];
                $day_key = intval(date('w', strtotime($plan_date)));

                if (isset($day_map[$day_key])) {
                    $day_col = 'max_' . $day_map[$day_key];
                    $daily_limit = property_exists($grade, $day_col) ? intval($grade->$day_col) : 0;

                    if ($daily_limit > 0) {
                        $daily_plans = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table WHERE section_id = %d AND plan_date = %s",
                            $section_id,
                            $plan_date
                        ));
                        if ($daily_plans >= $daily_limit) {
                            return new WP_Error('limit_reached', sprintf(__('Daily limit reached for this day (%d plans).', 'olama-school'), $daily_limit));
                        }
                    }
                }
            }

            // Get Subject Limit
            $subject = Olama_School_Subject::get_subject($subject_id);
            $subject_limit = $subject ? intval($subject->max_weekly_plans) : 0;

            if ($subject_limit > 0) {
                $subject_plans = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE section_id = %d AND subject_id = %d AND plan_date BETWEEN %s AND %s",
                    $section_id,
                    $subject_id,
                    $week_start,
                    $week_end
                ));
                if ($subject_plans >= $subject_limit) {
                    return new WP_Error('limit_reached', sprintf(__('Weekly limit reached for this subject (%d plans).', 'olama-school'), $subject_limit));
                }
            }
        }

        // If no plan_id, check if a plan already exists for this section, date, and subject
        // We exclude period_number if it's 0 (meaning not specified) to allow general day-plans per subject
        if (!$plan_id) {
            $existing_plan_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE section_id = %d AND plan_date = %s AND subject_id = %d",
                $section_id,
                $plan_date,
                $subject_id
            ));
            if ($existing_plan_id) {
                $plan_id = $existing_plan_id;
            }
        }

        $unit_id = !empty($data['unit_id']) ? intval($data['unit_id']) : null;
        $lesson_id = !empty($data['lesson_id']) ? intval($data['lesson_id']) : null;

        // If lesson_id is provided but unit_id is missing, find the unit_id
        if ($lesson_id && !$unit_id) {
            $lesson = Olama_School_Lesson::get_lesson($lesson_id);
            if ($lesson) {
                $unit_id = $lesson->unit_id;
            }
        }

        $plan_data = array(
            'section_id' => $section_id,
            'subject_id' => intval($data['subject_id']),
            'teacher_id' => intval($data['teacher_id']),
            'plan_date' => $plan_date,
            'period_number' => $period_number,
            'unit_id' => $unit_id,
            'lesson_id' => $lesson_id,
            'curriculum_id' => !empty($data['curriculum_id']) ? intval($data['curriculum_id']) : null,
            'custom_topic' => $data['custom_topic'] ?? '',
            'homework_sb' => $data['homework_sb'] ?? '',
            'homework_eb' => $data['homework_eb'] ?? '',
            'homework_nb' => $data['homework_nb'] ?? '',
            'homework_ws' => $data['homework_ws'] ?? '',
            'teacher_notes' => $data['teacher_notes'] ?? '',
            'rating' => isset($data['rating']) ? intval($data['rating']) : 0,
            'status' => $data['status'] ?? 'draft',
        );

        if ($plan_id > 0) {
            $wpdb->update($table, $plan_data, array('id' => $plan_id));
        } else {
            $wpdb->insert($table, $plan_data);
            $plan_id = $wpdb->insert_id;
        }

        // Handle linked questions
        if ($plan_id) {
            $wpdb->delete("{$wpdb->prefix}olama_plan_questions", array('plan_id' => $plan_id));
            if (!empty($data['question_ids']) && is_array($data['question_ids'])) {
                foreach ($data['question_ids'] as $q_id) {
                    $wpdb->insert("{$wpdb->prefix}olama_plan_questions", array(
                        'plan_id' => $plan_id,
                        'question_id' => intval($q_id)
                    ));
                }
            }
        }

        return $plan_id;
    }

    public static function delete_plan($id)
    {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}olama_plan_questions", array('plan_id' => intval($id)));
        return $wpdb->delete("{$wpdb->prefix}olama_plans", array('id' => intval($id)));
    }

    public static function get_plan_questions($plan_id)
    {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT question_id FROM {$wpdb->prefix}olama_plan_questions WHERE plan_id = %d",
            $plan_id
        ));
    }
}
