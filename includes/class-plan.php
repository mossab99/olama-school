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
            "SELECT p.*, s.subject_name, s.color_code, u.unit_number, u.unit_name, l.lesson_number, l.lesson_title, l.start_date as lesson_start_date, l.end_date as lesson_end_date, users.display_name as teacher_name 
            FROM {$wpdb->prefix}olama_plans p 
            LEFT JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id 
            LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id 
            LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id 
            LEFT JOIN {$wpdb->users} users ON p.teacher_id = users.ID 
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

        // Auto-assign period number if not specified (0)
        // This allows multiple subjects per day by giving each a unique period
        if ($period_number === 0 && !$plan_id) {
            // Find the highest period number for this section and date
            $max_period = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(period_number) FROM $table WHERE section_id = %d AND plan_date = %s",
                $section_id,
                $plan_date
            ));
            // Assign next available period number (starting from 1)
            $period_number = $max_period ? intval($max_period) + 1 : 1;
        }

        $subject_id = intval($data['subject_id'] ?? 0);
        $unit_id = intval($data['unit_id'] ?? 0);
        $lesson_id = intval($data['lesson_id'] ?? 0);

        if (!$subject_id) {
            return new WP_Error('missing_subject', Olama_School_Helpers::translate('Please select a subject.'));
        }
        if (!$unit_id) {
            return new WP_Error('missing_unit', Olama_School_Helpers::translate('Please select a unit.'));
        }
        if (!$lesson_id) {
            return new WP_Error('missing_lesson', Olama_School_Helpers::translate('Please select a lesson.'));
        }

        $plan_type = isset($data['plan_type']) && $data['plan_type'] === 'review' ? 'review' : 'homework';

        // 1. Subject Uniqueness Check (New) - One subject per day regardless of type
        if (!$plan_id) {
            $subject_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE section_id = %d AND plan_date = %s AND subject_id = %d",
                $section_id,
                $plan_date,
                $subject_id
            ));
            if ($subject_exists) {
                $subject_obj = Olama_School_Subject::get_subject($subject_id);
                $subject_name = $subject_obj ? $subject_obj->subject_name : '';
                return new WP_Error('duplicate_subject', sprintf(Olama_School_Helpers::translate('Subject %s already has a plan for today.'), $subject_name));
            }
        }

        // Limit Validation (only for homework plans)
        if (!$plan_id && $plan_type === 'homework') {
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
                // Check Weekly Limit (Only count homework plans)
                $total_plans = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE section_id = %d AND plan_type = 'homework' AND plan_date BETWEEN %s AND %s",
                    $section_id,
                    $week_start,
                    $week_end
                ));
                if ($total_plans >= $grade_limit) {
                    $grade_name = $grade ? $grade->grade_name : '';
                    return new WP_Error('limit_reached', sprintf(Olama_School_Helpers::translate('Grade %s has a maximum of %d homework plans per week.'), $grade_name, $grade_limit));
                }

                // Check Daily Limit (Only count homework plans)
                $day_map = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu'];
                $day_key = intval(date('w', strtotime($plan_date)));

                if (isset($day_map[$day_key])) {
                    $day_col = 'max_' . $day_map[$day_key];
                    $daily_limit = property_exists($grade, $day_col) ? intval($grade->$day_col) : 0;

                    if ($daily_limit > 0) {
                        $daily_plans = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table WHERE section_id = %d AND plan_type = 'homework' AND plan_date = %s",
                            $section_id,
                            $plan_date
                        ));
                        if ($daily_plans >= $daily_limit) {
                            $day_name = Olama_School_Helpers::translate(date('l', strtotime($plan_date)));
                            return new WP_Error('limit_reached', sprintf(Olama_School_Helpers::translate('A maximum of %d homework plans are allowed on %s.'), $daily_limit, $day_name));
                        }
                    }
                }
            }

            // Get Subject Limit
            $subject = Olama_School_Subject::get_subject($subject_id);
            $subject_limit = $subject ? intval($subject->max_weekly_plans) : 0;

            if ($subject_limit > 0) {
                // Check Subject Weekly Limit (Only count homework plans)
                $subject_plans = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE section_id = %d AND subject_id = %d AND plan_type = 'homework' AND plan_date BETWEEN %s AND %s",
                    $section_id,
                    $subject_id,
                    $week_start,
                    $week_end
                ));
                if ($subject_plans >= $subject_limit) {
                    $subject_name = $subject ? $subject->subject_name : '';
                    return new WP_Error('limit_reached', sprintf(Olama_School_Helpers::translate('Subject %s has a maximum of %d homework plans per week.'), $subject_name, $subject_limit));
                }
            }
        }

        // If no plan_id, check if a plan already exists for this exact combination
        // The database has a unique constraint on (section_id, plan_date, period_number)
        // But we also want to check by subject to allow updating existing plans for the same subject
        if (!$plan_id) {
            // First check: exact match by section, date, subject, and period
            // This allows updating an existing plan for the same subject
            $existing_plan_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE section_id = %d AND plan_date = %s AND subject_id = %d AND period_number = %d",
                $section_id,
                $plan_date,
                $subject_id,
                $period_number
            ));

            if ($existing_plan_id) {
                $plan_id = $existing_plan_id;
            }
        }



        // If lesson_id is provided but unit_id is missing, find the unit_id
        if ($lesson_id && !$unit_id) {
            $lesson = Olama_School_Lesson::get_lesson($lesson_id);
            if ($lesson) {
                $unit_id = $lesson->unit_id;
            }
        }

        $semester_id = isset($data['semester_id']) ? intval($data['semester_id']) : 0;
        $academic_year_id = isset($data['academic_year_id']) ? intval($data['academic_year_id']) : 0;

        if ($semester_id > 0 && !$academic_year_id) {
            $semester = $wpdb->get_row($wpdb->prepare(
                "SELECT academic_year_id FROM {$wpdb->prefix}olama_semesters WHERE id = %d",
                $semester_id
            ));
            if ($semester) {
                $academic_year_id = $semester->academic_year_id;
            }
        }

        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        if (!$semester_id && $plan_date) {
            // Fallback: Try to derive semester from date if not provided
            $semester = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d AND %s BETWEEN start_date AND end_date LIMIT 1",
                $academic_year_id,
                $plan_date
            ));
            $semester_id = $semester ? $semester->id : 0;
        }

        $plan_data = array(
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
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
            'plan_type' => $plan_type,
        );

        if ($plan_id > 0) {
            $result = $wpdb->update($table, $plan_data, array('id' => $plan_id));
            if ($result === false) {
                return new WP_Error('db_update_error', __('Database error updating plan: ', 'olama-school') . $wpdb->last_error);
            }
        } else {
            $result = $wpdb->insert($table, $plan_data);
            if ($result === false) {
                // Check if this is a duplicate entry error (time slot already occupied)
                if (strpos($wpdb->last_error, 'Duplicate entry') !== false && strpos($wpdb->last_error, 'uk_plan_slot') !== false) {
                    // Find which subject is already in this slot
                    $existing_subject = $wpdb->get_var($wpdb->prepare(
                        "SELECT s.subject_name FROM $table p 
                         LEFT JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
                         WHERE p.section_id = %d AND p.plan_date = %s AND p.period_number = %d",
                        $section_id,
                        $plan_date,
                        $period_number
                    ));

                    if ($existing_subject) {
                        return new WP_Error('slot_occupied', sprintf(
                            __('This time slot is already occupied by "%s". Please delete that plan first or choose a different period.', 'olama-school'),
                            $existing_subject
                        ));
                    }
                }

                return new WP_Error('db_insert_error', __('Database error creating plan: ', 'olama-school') . $wpdb->last_error);
            }
            $plan_id = $wpdb->insert_id;
            if (!$plan_id) {
                return new WP_Error('no_insert_id', __('Plan was not saved - no ID returned', 'olama-school'));
            }
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