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
     * Delete an academic year
     */
    public static function delete_year($year_id)
    {
        global $wpdb;
        // First delete semesters belonging to this year
        $wpdb->delete("{$wpdb->prefix}olama_semesters", array('academic_year_id' => $year_id));
        return $wpdb->delete("{$wpdb->prefix}olama_academic_years", array('id' => $year_id));
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
    public static function delete_semester($semester_id)
    {
        global $wpdb;
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
    public static function get_academic_weeks($year_id = null, $semester_id = null)
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

        $cache_key = 'olama_academic_weeks_' . $year_id . ($semester_id ? '_s' . $semester_id : '') . '_' . $format_key;
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

        $weeks = array();
        foreach ($semesters as $semester) {
            $start_ts = strtotime($semester->start_date);
            $end_ts = strtotime($semester->end_date);

            // Find the Sunday of the week containing the start date
            $day_of_week = date('w', $start_ts);
            $current_sunday = $start_ts - ($day_of_week * 86400);

            while ($current_sunday <= $end_ts) {
                $week_start = date('Y-m-d', $current_sunday);
                $week_end = date('Y-m-d', $current_sunday + (4 * 86400)); // Thursday

                // Check overlap with semester
                $week_end_ts = $current_sunday + (4 * 86400);
                if ($week_end_ts >= $start_ts && $current_sunday <= $end_ts) {
                    $weeks[$week_start] = sprintf(
                        '(%s - %s)',
                        Olama_School_Helpers::format_date($current_sunday),
                        Olama_School_Helpers::format_date($current_sunday + (4 * 86400))
                    );
                }

                $current_sunday += (7 * 86400); // Next Sunday
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
}
