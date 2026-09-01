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
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_context')) {
            return olama_core()->academic_context()->current_year();
        }
        return null;
    }

    /**
     * Get all academic years
     */
    public static function get_years()
    {
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_calendar')) {
            return olama_core()->academic_calendar()->years();
        }
        return array();
    }

    /**
     * Add academic year
     */
    public static function add_year($data)
    {
        return self::core_owned_error();
    }

    /**
     * Activate an academic year
     */
    public static function activate_year($year_id)
    {
        return self::core_owned_error();
    }

    /**
     * Get single year
     */
    public static function get_year($year_id)
    {
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_calendar')) {
            return olama_core()->academic_calendar()->year($year_id);
        }
        return null;
    }

    /**
     * Get single semester
     */
    public static function get_semester($semester_id)
    {
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_calendar')) {
            return olama_core()->academic_calendar()->semester($semester_id);
        }
        return null;
    }

    /**
     * Update academic year
     */
    public static function update_year($year_id, $data)
    {
        return self::core_owned_error();
    }

    /**
     * Delete an academic year
     */
    public static function delete_year($year_id, $force = false)
    {
        return self::core_owned_error();
    }

    /**
     * Get semesters for a year
     */
    public static function get_semesters($year_id)
    {
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_calendar')) {
            return olama_core()->academic_calendar()->semesters($year_id);
        }
        return array();
    }

    /**
     * Add semester
     */
    public static function add_semester($data)
    {
        return self::core_owned_error();
    }

    /**
     * Delete semester
     */
    public static function delete_semester($semester_id, $force = false)
    {
        return self::core_owned_error();
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
        return self::core_owned_error();
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

        // Year definitions are read through Core; School owns only the event.
        $year = self::get_year($data['academic_year_id']);

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

        $year = self::get_year($event->academic_year_id);

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

        // Keep week numbering inside the selected academic year's boundaries.
        // The cache version prevents older semester-only week indexes from being reused.
        $cache_key = 'olama_academic_weeks_v2_' . $year_id . ($semester_id ? '_s' . $semester_id : '') . '_' . $format_key . ($full_info ? '_full' : '');
        $weeks = get_transient($cache_key);
        if ($weeks !== false) {
            return $weeks;
        }

        $semesters = array();
        if ($semester_id) {
            $sem = self::get_semester($semester_id);
            if ($sem) {
                $semesters[] = $sem;
            }
        } else {
            $semesters = self::get_semesters($year_id);
        }
        if (!$semesters) {
            return array();
        }

        $academic_year = self::get_year($year_id);
        $academic_year_start_ts = $academic_year && !empty($academic_year->start_date)
            ? strtotime($academic_year->start_date)
            : false;
        $academic_year_end_ts = $academic_year && !empty($academic_year->end_date)
            ? strtotime($academic_year->end_date)
            : false;

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

            // Core calendar data can contain semester dates outside the academic
            // year. Those dates must not shift the report's first week to week 10.
            if ($academic_year_start_ts !== false) {
                $start_ts = max($start_ts, $academic_year_start_ts);
            }
            if ($academic_year_end_ts !== false) {
                $end_ts = min($end_ts, $academic_year_end_ts);
            }
            if ($start_ts > $end_ts) {
                continue;
            }

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
        if (function_exists('olama_core') && method_exists(olama_core(), 'academic_context')) {
            $context = olama_core()->academic_context()->current();
            if (!$context || ($year_id && (int) $context->academic_year_id !== (int) $year_id)) {
                return null;
            }
            return olama_core()->academic_context()->current_semester();
        }
        return null;
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
        return self::core_owned_error();
    }

    private static function core_owned_error()
    {
        return new WP_Error(
            'academic_calendar_owned_by_core',
            __('Academic years and semesters can only be managed through Olama Core.', 'olama-school')
        );
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
