<?php
/**
 * Academic Structure Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Academic
{

    /**
     * Get active academic year
     */
    public static function get_active_year()
    {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}olama_academic_years WHERE is_active = 1 LIMIT 1");
    }

    /**
     * Get all academic years
     */
    public static function get_years()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_academic_years ORDER BY start_date DESC");
    }

    /**
     * Add academic year
     */
    public static function add_year($data)
    {
        global $wpdb;

        // If this is the first year, make it active by default
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_academic_years");
        $is_active = ($count == 0) ? 1 : ($data['is_active'] ?? 0);

        // If we are setting this one to active, deactivate others
        if ($is_active) {
            $wpdb->update("{$wpdb->prefix}olama_academic_years", array('is_active' => 0), array('is_active' => 1));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_academic_years",
            array(
                'year_name' => $data['year_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            )
        );
    }

    /**
     * Activate an academic year
     */
    public static function activate_year($year_id)
    {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}olama_academic_years", array('is_active' => 0), array('is_active' => 1));
        return $wpdb->update("{$wpdb->prefix}olama_academic_years", array('is_active' => 1), array('id' => $year_id));
    }

    /**
     * Get single year
     */
    public static function get_year($year_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_academic_years WHERE id = %d", $year_id));
    }

    /**
     * Get single semester
     */
    public static function get_semester($semester_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
    }

    /**
     * Update academic year
     */
    public static function update_year($year_id, $data)
    {
        global $wpdb;

        $is_active = isset($data['is_active']) ? $data['is_active'] : 0;

        // If we are setting this one to active, deactivate others
        if ($is_active) {
            $wpdb->update("{$wpdb->prefix}olama_academic_years", array('is_active' => 0), array('is_active' => 1));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_academic_years",
            array(
                'year_name' => $data['year_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            ),
            array('id' => $year_id)
        );
    }

    /**
     * Delete an academic year
     */
    public static function delete_year($year_id, $force = false)
    {
        global $wpdb;

        if (!$force) {
            // Check for dependencies in any of this year's semesters
            $semesters = self::get_semesters($year_id);
            foreach ($semesters as $sem) {
                $plans_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_plans WHERE semester_id = %d", $sem->id));
                if ($plans_count > 0) {
                    return new WP_Error('dependency_error', sprintf(__('Cannot delete year because semester "%s" has %d weekly plans.', 'olama-school'), $sem->semester_name, $plans_count));
                }

                $schedule_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_schedule WHERE semester_id = %d", $sem->id));
                if ($schedule_count > 0) {
                    return new WP_Error('dependency_error', sprintf(__('Cannot delete year because semester "%s" has %d schedule items.', 'olama-school'), $sem->semester_name, $schedule_count));
                }

                $units_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d", $sem->id));
                if ($units_count > 0) {
                    return new WP_Error('dependency_error', sprintf(__('Cannot delete year because semester "%s" has %d curriculum units.', 'olama-school'), $sem->semester_name, $units_count));
                }

                $exams_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_exams WHERE semester_id = %d", $sem->id));
                if ($exams_count > 0) {
                    return new WP_Error('dependency_error', sprintf(__('Cannot delete year because semester "%s" has %d exams.', 'olama-school'), $sem->semester_name, $exams_count));
                }
            }

            // Also check for direct year dependencies (events, sections, teacher assignments)
            $events_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_academic_events WHERE academic_year_id = %d", $year_id));
            if ($events_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete year because it has %d academic events.', 'olama-school'), $events_count));
            }

            $sections_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections WHERE academic_year_id = %d", $year_id));
            if ($sections_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete year because it has %d sections.', 'olama-school'), $sections_count));
            }
        } else {
            // Force delete
            $semesters = self::get_semesters($year_id);
            foreach ($semesters as $sem) {
                self::delete_semester($sem->id, true);
            }

            // Clean up direct dependencies
            $wpdb->delete("{$wpdb->prefix}olama_academic_events", array('academic_year_id' => $year_id));
            $wpdb->delete("{$wpdb->prefix}olama_teacher_assignments", array('academic_year_id' => $year_id));
            $wpdb->delete("{$wpdb->prefix}olama_sections", array('academic_year_id' => $year_id));

            // Delete all semesters for this year (just in case any were missed)
            $wpdb->delete("{$wpdb->prefix}olama_semesters", array('academic_year_id' => $year_id));
        }

        // Final deletion
        $deleted = $wpdb->delete("{$wpdb->prefix}olama_academic_years", array('id' => $year_id));

        if ($deleted) {
            delete_transient('olama_active_year');
            delete_transient('olama_academic_years');
        }

        return $deleted;
    }

    /**
     * Get semesters for a year
     */
    public static function get_semesters($year_id)
    {
        $cache_key = 'olama_semesters_' . $year_id;
        $semesters = get_transient($cache_key);
        if ($semesters !== false) {
            return $semesters;
        }

        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d ORDER BY start_date ASC",
            $year_id
        ));

        set_transient($cache_key, $results, DAY_IN_SECONDS);
        return $results;
    }

    /**
     * Add semester
     */
    public static function add_semester($data)
    {
        global $wpdb;

        $validation = self::validate_semester_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // If this is the first semester for this year, make it active by default
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d", $data['academic_year_id']));
        $is_active = ($count == 0) ? 1 : ($data['is_active'] ?? 0);

        // If we are setting this one to active, deactivate others for this year
        if ($is_active) {
            $wpdb->update("{$wpdb->prefix}olama_semesters", array('is_active' => 0), array('academic_year_id' => $data['academic_year_id']));
        }

        $inserted = $wpdb->insert(
            "{$wpdb->prefix}olama_semesters",
            array(
                'academic_year_id' => $data['academic_year_id'],
                'semester_name' => $data['semester_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            )
        );

        if ($inserted) {
            delete_transient('olama_semesters_' . $data['academic_year_id']);
            delete_transient('olama_academic_weeks_' . $data['academic_year_id']);
        }
        return $inserted;
    }

    /**
     * Delete semester
     */
    public static function delete_semester($semester_id, $force = false)
    {
        global $wpdb;

        if (!$force) {
            // Check for dependencies
            $plans_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_plans WHERE semester_id = %d", $semester_id));
            if ($plans_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete semester because it has %d weekly plans.', 'olama-school'), $plans_count));
            }

            $schedule_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_schedule WHERE semester_id = %d", $semester_id));
            if ($schedule_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete semester because it has %d schedule items.', 'olama-school'), $schedule_count));
            }

            $units_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d", $semester_id));
            if ($units_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete semester because it has %d curriculum units.', 'olama-school'), $units_count));
            }

            $exams_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}olama_exams WHERE semester_id = %d", $semester_id));
            if ($exams_count > 0) {
                return new WP_Error('dependency_error', sprintf(__('Cannot delete semester because it has %d exams.', 'olama-school'), $exams_count));
            }
        } else {
            // Force delete: Clean up all tables
            // 1. Delete plan questions
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}olama_plan_questions WHERE plan_id IN (SELECT id FROM {$wpdb->prefix}olama_plans WHERE semester_id = %d)",
                $semester_id
            ));
            // 2. Delete plans
            $wpdb->delete("{$wpdb->prefix}olama_plans", array('semester_id' => $semester_id));

            // 3. Delete schedule items
            $wpdb->delete("{$wpdb->prefix}olama_schedule", array('semester_id' => $semester_id));

            // 4. Delete curriculum questions
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}olama_curriculum_questions WHERE lesson_id IN (
                    SELECT id FROM {$wpdb->prefix}olama_curriculum_lessons WHERE unit_id IN (
                        SELECT id FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d
                    )
                )",
                $semester_id
            ));
            // 5. Delete curriculum lessons
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}olama_curriculum_lessons WHERE unit_id IN (
                    SELECT id FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d
                )",
                $semester_id
            ));
            // 6. Delete curriculum units
            $wpdb->delete("{$wpdb->prefix}olama_curriculum_units", array('semester_id' => $semester_id));

            // 7. Delete exams
            $wpdb->delete("{$wpdb->prefix}olama_exams", array('semester_id' => $semester_id));
        }

        $semester = $wpdb->get_row($wpdb->prepare("SELECT academic_year_id FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
        if ($semester) {
            $year_id = $semester->academic_year_id;
            $deleted = $wpdb->delete("{$wpdb->prefix}olama_semesters", array('id' => $semester_id));
            if ($deleted) {
                delete_transient('olama_semesters_' . $year_id);
                delete_transient('olama_academic_weeks_' . $year_id);
            }
            return $deleted;
        }
        return false;
    }

    /**
     * Get single semester exam
     */
    public static function get_semester_exam($exam_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semester_exams WHERE id = %d", $exam_id));
    }

    /**
     * Update semester
     */
    public static function update_semester($semester_id, $data)
    {
        global $wpdb;

        $semester = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
        if (!$semester) {
            return new WP_Error('invalid_semester', __('Invalid Semester.', 'olama-school'));
        }

        $data['academic_year_id'] = $semester->academic_year_id;
        $validation = self::validate_semester_data($data, $semester_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $is_active = isset($data['is_active']) ? $data['is_active'] : (!empty($semester->is_active) ? 1 : 0);

        // If we are setting this one to active, deactivate others for this year
        if ($is_active && empty($semester->is_active)) {
            self::activate_semester($semester_id);
        }

        $updated = $wpdb->update(
            "{$wpdb->prefix}olama_semesters",
            array(
                'semester_name' => $data['semester_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            ),
            array('id' => $semester_id)
        );

        if ($updated !== false) {
            delete_transient('olama_semesters_' . $semester->academic_year_id);
            delete_transient('olama_academic_weeks_' . $semester->academic_year_id);
        }
        return $updated;
    }

    /**
     * Validate semester data
     */
    private static function validate_semester_data($data, $exclude_id = 0)
    {
        global $wpdb;

        // 1. Basic dates
        if ($data['start_date'] > $data['end_date']) {
            return new WP_Error('invalid_dates', __('Start date cannot be after end date.', 'olama-school'));
        }

        // 2. Academic year boundaries
        $year = $wpdb->get_row($wpdb->prepare("SELECT start_date, end_date FROM {$wpdb->prefix}olama_academic_years WHERE id = %d", $data['academic_year_id']));
        if (!$year) {
            return new WP_Error('invalid_year', __('Invalid Academic Year.', 'olama-school'));
        }

        if ($data['start_date'] < $year->start_date || $data['end_date'] > $year->end_date) {
            return new WP_Error('out_of_bounds', __('Semester dates must be within the academic year range.', 'olama-school'));
        }

        // 3. Duplicate name check
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d AND semester_name = %s AND id != %d",
            $data['academic_year_id'],
            $data['semester_name'],
            $exclude_id
        );
        if ($wpdb->get_var($query) > 0) {
            return new WP_Error('duplicate_name', __('A semester with this name already exists in this academic year.', 'olama-school'));
        }

        // 4. Overlap check
        $overlap_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}olama_semesters 
            WHERE academic_year_id = %d AND id != %d 
            AND (
                (start_date <= %s AND end_date >= %s) OR
                (start_date <= %s AND end_date >= %s) OR
                (start_date >= %s AND end_date <= %s)
            )",
            $data['academic_year_id'],
            $exclude_id,
            $data['start_date'],
            $data['start_date'],
            $data['end_date'],
            $data['end_date'],
            $data['start_date'],
            $data['end_date']
        );

        if ($wpdb->get_var($overlap_query) > 0) {
            return new WP_Error('date_overlap', __('Semester dates overlap with another existing semester.', 'olama-school'));
        }

        return true;
    }

    /**
     * Get events for a year
     */
    public static function get_events($year_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_academic_events WHERE academic_year_id = %d ORDER BY start_date ASC",
            $year_id
        ));
    }

    /**
     * Add event
     */
    public static function add_event($data)
    {
        global $wpdb;

        // Validation: Date range
        $year = $wpdb->get_row($wpdb->prepare("SELECT start_date, end_date FROM {$wpdb->prefix}olama_academic_years WHERE id = %d", $data['academic_year_id']));

        if (!$year) {
            return new WP_Error('invalid_year', __('Invalid Academic Year.', 'olama-school'));
        }

        if ($data['start_date'] < $year->start_date || $data['end_date'] > $year->end_date) {
            return new WP_Error('out_of_range', __('Event dates must be within the academic year range.', 'olama-school'));
        }

        if ($data['start_date'] > $data['end_date']) {
            return new WP_Error('invalid_dates', __('Start date cannot be after end date.', 'olama-school'));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_academic_events",
            array(
                'academic_year_id' => $data['academic_year_id'],
                'event_description' => $data['event_description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            )
        );
    }

    /**
     * Delete event
     */
    public static function delete_event($event_id)
    {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}olama_academic_events", array('id' => $event_id));
    }

    /**
     * Get single event
     */
    public static function get_event($event_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_academic_events WHERE id = %d",
            $event_id
        ));
    }

    /**
     * Update event
     */
    public static function update_event($event_id, $data)
    {
        global $wpdb;

        // Validation: Date range
        $event = self::get_event($event_id);
        if (!$event) {
            return new WP_Error('invalid_event', __('Invalid Event.', 'olama-school'));
        }

        $year = $wpdb->get_row($wpdb->prepare("SELECT start_date, end_date FROM {$wpdb->prefix}olama_academic_years WHERE id = %d", $event->academic_year_id));

        if (!$year) {
            return new WP_Error('invalid_year', __('Invalid Academic Year.', 'olama-school'));
        }

        if ($data['start_date'] < $year->start_date || $data['end_date'] > $year->end_date) {
            return new WP_Error('out_of_range', __('Event dates must be within the academic year range.', 'olama-school'));
        }

        if ($data['start_date'] > $data['end_date']) {
            return new WP_Error('invalid_dates', __('Start date cannot be after end date.', 'olama-school'));
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_academic_events",
            array(
                'event_description' => $data['event_description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ),
            array('id' => $event_id)
        );
    }

    /**
     * Get all academic weeks for the active year
     * Format: Sunday Date => "(Sunday:date - Thursday:date)"
     */
    public static function get_academic_weeks($year_id = null, $semester_id = null, $full_info = false)
    {
        if (!$year_id) {
            $active_year = self::get_active_year();
            if (!$active_year) {
                return array();
            }
            $year_id = $active_year->id;
        }

        // Include date format in cache key so weeks regenerate when format changes
        $settings = get_option('olama_school_settings', array());
        $date_format = isset($settings['date_format']) ? $settings['date_format'] : 'd-m-Y';
        $format_key = str_replace('-', '', $date_format); // dmY, mdY, or Ymd

        $cache_key = 'olama_academic_weeks_' . $year_id . ($semester_id ? '_s' . $semester_id : '') . '_' . $format_key . ($full_info ? '_full' : '');
        $weeks = get_transient($cache_key);
        if ($weeks !== false) {
            return $weeks;
        }

        $semesters = array();
        if ($semester_id) {
            global $wpdb;
            $sem = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
            if ($sem) {
                $semesters[] = $sem;
            }
        } else {
            $semesters = self::get_semesters($year_id);
        }
        if (!$semesters) {
            return array();
        }

        $settings = get_option('olama_school_settings', array());
        $start_day_setting = $settings['start_day'] ?? 'Sunday';
        $last_day_setting = $settings['last_day'] ?? 'Thursday';

        $all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $start_idx = array_search($start_day_setting, $all_days);
        $last_idx = array_search($last_day_setting, $all_days);
        if ($start_idx === false)
            $start_idx = 0;
        if ($last_idx === false)
            $last_idx = 4;
        $days_diff = ($last_idx - $start_idx + 7) % 7;

        $weeks = array();
        $week_num = 1;
        foreach ($semesters as $semester) {
            $start_ts = strtotime($semester->start_date);
            $end_ts = strtotime($semester->end_date);

            // Find the start day of the week containing the start date
            $day_of_week = (int) date('w', $start_ts);
            $current_week_start_ts = $start_ts - ((($day_of_week - $start_idx + 7) % 7) * 86400);

            while ($current_week_start_ts <= $end_ts) {
                $week_start = date('Y-m-d', $current_week_start_ts);
                $week_end_ts = $current_week_start_ts + ($days_diff * 86400);
                $week_end = date('Y-m-d', $week_end_ts);

                // Check overlap with semester
                if ($week_end_ts >= $start_ts && $current_week_start_ts <= $end_ts) {
                    $label = sprintf(
                        '(%s - %s)',
                        Olama_School_Helpers::format_date($current_week_start_ts),
                        Olama_School_Helpers::format_date($week_end_ts)
                    );

                    if ($full_info) {
                        $weeks[$week_start] = array(
                            'number' => $week_num++,
                            'start' => $week_start,
                            'end' => $week_end,
                            'label' => $label
                        );
                    } else {
                        $weeks[$week_start] = $label;
                    }
                }

                $current_week_start_ts += (7 * 86400); // Next week same day
            }
        }
        ksort($weeks);
        set_transient($cache_key, $weeks, DAY_IN_SECONDS);
        return $weeks;
    }

    /**
     * Get active semester
     */
    public static function get_active_semester($year_id = null)
    {
        global $wpdb;
        if (!$year_id) {
            $active_year = self::get_active_year();
            if (!$active_year)
                return null;
            $year_id = $active_year->id;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d AND is_active = 1 LIMIT 1",
            $year_id
        ));
    }

    /**
     * Get active exam for a semester
     */
    public static function get_active_exam($semester_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_semester_exams WHERE semester_id = %d AND is_active = 1 LIMIT 1",
            $semester_id
        ));
    }

    /**
     * Activate a semester
     */
    public static function activate_semester($semester_id)
    {
        global $wpdb;
        $semester = $wpdb->get_row($wpdb->prepare("SELECT academic_year_id FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
        if ($semester) {
            $wpdb->update("{$wpdb->prefix}olama_semesters", array('is_active' => 0), array('academic_year_id' => $semester->academic_year_id));
            $updated = $wpdb->update("{$wpdb->prefix}olama_semesters", array('is_active' => 1), array('id' => $semester_id));
            if ($updated) {
                delete_transient('olama_semesters_' . $semester->academic_year_id);
            }
            return $updated;
        }
        return false;
    }
    /**
     * Get exams for a semester
     */
    public static function get_semester_exams($semester_id, $grade_id = 0)
    {
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}olama_semester_exams WHERE semester_id = %d";
        $params = array($semester_id);

        if ($grade_id) {
            $query .= " AND (grade_id = %d OR grade_id IS NULL)";
            $params[] = $grade_id;
        }

        $query .= " ORDER BY start_date ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Add semester exam
     */
    public static function add_semester_exam($data)
    {
        global $wpdb;

        $is_active = $data['is_active'] ?? 0;
        if ($is_active) {
            $wpdb->update("{$wpdb->prefix}olama_semester_exams", array('is_active' => 0), array('semester_id' => $data['semester_id']));
        }

        return $wpdb->insert(
            "{$wpdb->prefix}olama_semester_exams",
            array(
                'semester_id' => $data['semester_id'],
                'grade_id' => !empty($data['grade_id']) ? intval($data['grade_id']) : null,
                'exam_name' => $data['exam_name'],
                'room_number' => $data['room_number'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            )
        );
    }

    /**
     * Update semester exam
     */
    public static function update_semester_exam($exam_id, $data)
    {
        global $wpdb;

        $is_active = $data['is_active'] ?? 0;
        if ($is_active) {
            $exam = $wpdb->get_row($wpdb->prepare("SELECT semester_id FROM {$wpdb->prefix}olama_semester_exams WHERE id = %d", $exam_id));
            if ($exam) {
                $wpdb->update("{$wpdb->prefix}olama_semester_exams", array('is_active' => 0), array('semester_id' => $exam->semester_id));
            }
        }

        return $wpdb->update(
            "{$wpdb->prefix}olama_semester_exams",
            array(
                'grade_id' => !empty($data['grade_id']) ? intval($data['grade_id']) : null,
                'exam_name' => $data['exam_name'],
                'room_number' => $data['room_number'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $is_active,
            ),
            array('id' => $exam_id)
        );
    }

    /**
     * Delete semester exam
     */
    public static function delete_semester_exam($exam_id)
    {
        global $wpdb;
        return $wpdb->delete("{$wpdb->prefix}olama_semester_exams", array('id' => $exam_id));
    }

    /**
     * Activate a semester exam
     */
    public static function activate_semester_exam($exam_id)
    {
        global $wpdb;
        $exam = $wpdb->get_row($wpdb->prepare("SELECT semester_id FROM {$wpdb->prefix}olama_semester_exams WHERE id = %d", $exam_id));
        if ($exam) {
            $wpdb->update("{$wpdb->prefix}olama_semester_exams", array('is_active' => 0), array('semester_id' => $exam->semester_id));
            return $wpdb->update("{$wpdb->prefix}olama_semester_exams", array('is_active' => 1), array('id' => $exam_id));
        }
        return false;
    }
}