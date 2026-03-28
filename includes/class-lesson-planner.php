<?php
/**
 * Lesson Planner Management Class (V2)
 * Handles CRUD operations for daily lesson plans (دفتر تحضير الدروس)
 * with stage-based architecture and compliance scoring.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Lesson_Planner
{
    /**
     * Get lesson plans with optional filters
     */
    public static function get_plans($filters = array())
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_lesson_plans";
        $where = array('1=1');
        $params = array();

        if (!empty($filters['academic_year_id'])) {
            $where[] = 'lp.academic_year_id = %d';
            $params[] = intval($filters['academic_year_id']);
        }
        if (!empty($filters['semester_id'])) {
            $where[] = 'lp.semester_id = %d';
            $params[] = intval($filters['semester_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $where[] = 'lp.teacher_id = %d';
            $params[] = intval($filters['teacher_id']);
        }
        if (!empty($filters['grade_id'])) {
            $where[] = 'lp.grade_id = %d';
            $params[] = intval($filters['grade_id']);
        }
        if (!empty($filters['section_id'])) {
            $where[] = 'lp.section_id = %d';
            $params[] = intval($filters['section_id']);
        }
        if (!empty($filters['subject_id'])) {
            $where[] = 'lp.subject_id = %d';
            $params[] = intval($filters['subject_id']);
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT lp.*, 
                    s.subject_name, 
                    g.grade_name, 
                    sec.section_name, 
                    u.display_name AS teacher_name,
                    cu.unit_name,
                    cl.lesson_title AS curriculum_lesson_title
                FROM $table lp
                LEFT JOIN {$wpdb->prefix}olama_subjects s ON lp.subject_id = s.id
                LEFT JOIN {$wpdb->prefix}olama_grades g ON lp.grade_id = g.id
                LEFT JOIN {$wpdb->prefix}olama_sections sec ON lp.section_id = sec.id
                LEFT JOIN {$wpdb->users} u ON lp.teacher_id = u.ID
                LEFT JOIN {$wpdb->prefix}olama_curriculum_units cu ON lp.unit_id = cu.id
                LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons cl ON lp.lesson_id = cl.id
                WHERE $where_clause
                ORDER BY lp.start_date DESC, lp.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get a single lesson plan by ID
     */
    public static function get_plan($id, $teacher_id = 0)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_lesson_plans";

        $where = "WHERE lp.id = %d";
        $params = array(intval($id));

        if ($teacher_id > 0) {
            $where .= " AND lp.teacher_id = %d";
            $params[] = intval($teacher_id);
        }

        $query = $wpdb->prepare(
            "SELECT lp.*, 
                    s.subject_name, 
                    g.grade_name, 
                    sec.section_name, 
                    u.display_name AS teacher_name,
                    cu.unit_name,
                    cl.lesson_title AS curriculum_lesson_title
                FROM $table lp
                LEFT JOIN {$wpdb->prefix}olama_subjects s ON lp.subject_id = s.id
                LEFT JOIN {$wpdb->prefix}olama_grades g ON lp.grade_id = g.id
                LEFT JOIN {$wpdb->prefix}olama_sections sec ON lp.section_id = sec.id
                LEFT JOIN {$wpdb->users} u ON lp.teacher_id = u.ID
                LEFT JOIN {$wpdb->prefix}olama_curriculum_units cu ON lp.unit_id = cu.id
                LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons cl ON lp.lesson_id = cl.id
            $where",
            ...$params
        );

        return $wpdb->get_row($query);
    }

    /**
     * Save (insert or update) a lesson plan
     */
    public static function save_plan($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_lesson_plans";

        // Calculate compliance score
        $compliance = self::calculate_compliance($data);

        $plan_data = array(
            'academic_year_id' => intval($data['academic_year_id']),
            'semester_id' => intval($data['semester_id']),
            'teacher_id' => intval($data['teacher_id']),
            'subject_id' => intval($data['subject_id']),
            'grade_id' => intval($data['grade_id']),
            'section_id' => intval($data['section_id']),
            'unit_id' => !empty($data['unit_id']) ? intval($data['unit_id']) : 0,
            'lesson_id' => !empty($data['lesson_id']) ? intval($data['lesson_id']) : 0,
            'lesson_title' => sanitize_text_field($data['lesson_title'] ?? ''),
            'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : '',
            'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : '',
            'number_of_classes' => intval($data['number_of_classes'] ?? 1),
            'period_duration' => intval($data['period_duration'] ?? 45),
            'learning_outcomes' => !empty($data['learning_outcomes']) ? wp_json_encode($data['learning_outcomes'], JSON_UNESCAPED_UNICODE) : '',
            'prior_learning' => sanitize_textarea_field($data['prior_learning'] ?? ''),
            'stages' => !empty($data['stages']) ? wp_json_encode($data['stages'], JSON_UNESCAPED_UNICODE) : '',
            'teaching_strategies_used' => !empty($data['teaching_strategies_used']) ? wp_json_encode($data['teaching_strategies_used']) : '',
            'assessment_strategies_used' => !empty($data['assessment_strategies_used']) ? wp_json_encode($data['assessment_strategies_used']) : '',
            'assessment_tools_used' => !empty($data['assessment_tools_used']) ? wp_json_encode($data['assessment_tools_used']) : '',
            'resources' => sanitize_textarea_field($data['resources'] ?? ''),
            'self_reflection' => sanitize_textarea_field($data['self_reflection'] ?? ''),
            'homework' => sanitize_textarea_field($data['homework'] ?? ''),
            'compliance_score' => intval($compliance),
            'status' => sanitize_text_field($data['status'] ?? 'draft'),
        );

        $format = array(
            '%d', // academic_year_id
            '%d', // semester_id
            '%d', // teacher_id
            '%d', // subject_id
            '%d', // grade_id
            '%d', // section_id
            '%d', // unit_id
            '%d', // lesson_id
            '%s', // lesson_title
            '%s', // start_date
            '%s', // end_date
            '%d', // number_of_classes
            '%d', // period_duration
            '%s', // learning_outcomes
            '%s', // prior_learning
            '%s', // stages
            '%s', // teaching_strategies_used
            '%s', // assessment_strategies_used
            '%s', // assessment_tools_used
            '%s', // resources
            '%s', // self_reflection
            '%s', // homework
            '%d', // compliance_score
            '%s', // status
        );

        if (!empty($data['id'])) {
            $result = $wpdb->update($table, $plan_data, array('id' => intval($data['id'])), $format, array('%d'));
            return array(
                'id' => intval($data['id']),
                'result' => $result,
                'compliance_score' => $compliance,
                'error' => $wpdb->last_error,
            );
        } else {
            $result = $wpdb->insert($table, $plan_data, $format);
            return array(
                'id' => $wpdb->insert_id,
                'result' => $result,
                'compliance_score' => $compliance,
                'error' => $wpdb->last_error,
            );
        }
    }

    /**
     * Delete a lesson plan by ID
     */
    public static function delete_plan($id, $teacher_id = 0)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_lesson_plans";
        $where = array('id' => intval($id));
        if ($teacher_id > 0) {
            $where['teacher_id'] = intval($teacher_id);
        }
        return $wpdb->delete($table, $where);
    }

    /**
     * Get plans for a specific teacher in a given year/semester
     */
    public static function get_teacher_plans($teacher_id, $academic_year_id, $semester_id)
    {
        return self::get_plans(array(
            'teacher_id' => $teacher_id,
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
        ));
    }

    /**
     * Calculate compliance score (0-100) based on pedagogical validation rules
     */
    public static function calculate_compliance($data)
    {
        $weights = Olama_Lesson_Planner_Config::get_compliance_weights();
        $score = 0;

        $stages_config = Olama_Lesson_Planner_Config::get_stages();
        $stage_keys = array_keys($stages_config);
        $num_stages = count($stage_keys);
        
        $stages = $data['stages'] ?? array();
        $num_classes = intval($data['number_of_classes'] ?? 1);

        // 1. Outcomes with verb + content (15pts)
        $outcomes = $data['learning_outcomes'] ?? array();
        if (!empty($outcomes)) {
            $valid_outcomes = 0;
            foreach ($outcomes as $outcome) {
                if (is_array($outcome)) {
                    if (!empty($outcome['verb']) && !empty($outcome['content'])) {
                        $valid_outcomes++;
                    }
                }
            }
            if ($valid_outcomes > 0) {
                $score += $weights['outcomes_with_verb'];
            }
        }

        // 2. All stages have teacher_action (20pts)
        $stages_with_teacher = 0;
        foreach ($stage_keys as $key) {
            if (!empty($stages[$key]['teacher_action'])) {
                $stages_with_teacher++;
            }
        }
        if ($stages_with_teacher === $num_stages) {
            $score += $weights['stages_teacher_action'];
        } elseif ($stages_with_teacher > 0) {
            $score += intval($weights['stages_teacher_action'] * ($stages_with_teacher / $num_stages));
        }

        // 3. All stages have learner_action (15pts)
        $stages_with_learner = 0;
        foreach ($stage_keys as $key) {
            if (!empty($stages[$key]['learner_action'])) {
                $stages_with_learner++;
            }
        }
        if ($stages_with_learner === $num_stages) {
            $score += $weights['stages_learner_action'];
        } elseif ($stages_with_learner > 0) {
            $score += intval($weights['stages_learner_action'] * ($stages_with_learner / $num_stages));
        }

        // 4. Time distribution = classes × 45 (10pts)
        $total_time = 0;
        foreach ($stage_keys as $key) {
            $total_time += intval($stages[$key]['time_minutes'] ?? 0);
        }
        $expected_time = $num_classes * 45;
        if ($expected_time > 0 && $total_time === $expected_time) {
            $score += $weights['time_distribution'];
        } elseif ($total_time > 0 && $expected_time > 0) {
            $ratio = min($total_time, $expected_time) / max($total_time, $expected_time);
            $score += intval($weights['time_distribution'] * $ratio);
        }

        // 5. Teaching strategy per stage (10pts)
        $stages_with_strategy = 0;
        foreach ($stage_keys as $key) {
            if (!empty($stages[$key]['teaching_strategy'])) {
                $stages_with_strategy++;
            }
        }
        if ($num_stages > 0) {
            $score += intval($weights['teaching_strategy'] * ($stages_with_strategy / $num_stages));
        }

        // 6. Assessment strategy per stage (10pts)
        $stages_with_assessment = 0;
        foreach ($stage_keys as $key) {
            if (!empty($stages[$key]['assessment_strategy'])) {
                $stages_with_assessment++;
            }
        }
        if ($num_stages > 0) {
            $score += intval($weights['assessment_strategy'] * ($stages_with_assessment / $num_stages));
        }

        // 7. Assessment tool per stage (5pts)
        $stages_with_tool = 0;
        foreach ($stage_keys as $key) {
            if (!empty($stages[$key]['assessment_tool'])) {
                $stages_with_tool++;
            }
        }
        if ($num_stages > 0) {
            $score += intval($weights['assessment_tool'] * ($stages_with_tool / $num_stages));
        }

        // 8. Resources not empty (5pts)
        if (!empty($data['resources'])) {
            $score += $weights['resources'];
        }

        // 9. Self-reflection not empty (5pts)
        if (!empty($data['self_reflection'])) {
            $score += $weights['self_reflection'];
        }

        // 10. Homework not empty (5pts)
        if (!empty($data['homework'])) {
            $score += $weights['homework'];
        }

        return min(100, $score);
    }
}
