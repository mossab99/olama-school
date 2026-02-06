<?php
/**
 * Shift Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Shifts
{
    /**
     * Get all locations
     */
    public static function get_locations($active_only = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_locations';
        $query = "SELECT * FROM $table";
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        return $wpdb->get_results($query);
    }

    /**
     * Save or update location
     */
    public static function save_location($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_locations';

        $id = isset($data['id']) ? intval($data['id']) : 0;
        $location_data = array(
            'location_name' => sanitize_text_field($data['location_name']),
            'area_floor' => sanitize_text_field($data['area_floor']),
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if ($id) {
            return $wpdb->update($table, $location_data, array('id' => $id));
        } else {
            return $wpdb->insert($table, $location_data);
        }
    }

    /**
     * Get all time slots
     */
    public static function get_time_slots($active_only = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_time_slots';
        $query = "SELECT * FROM $table";
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY start_time ASC";
        return $wpdb->get_results($query);
    }

    /**
     * Save or update time slot
     */
    public static function save_time_slot($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_time_slots';

        $id = isset($data['id']) ? intval($data['id']) : 0;
        $slot_data = array(
            'slot_label' => sanitize_text_field($data['slot_label']),
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'gender_focus' => sanitize_text_field($data['gender_focus']),
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if ($id) {
            return $wpdb->update($table, $slot_data, array('id' => $id));
        } else {
            return $wpdb->insert($table, $slot_data);
        }
    }

    /**
     * Get schedule with filters
     */
    public static function get_schedule($filters = array())
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_schedule';

        $where = array('1=1');
        if (!empty($filters['teacher_id']))
            $where[] = $wpdb->prepare("s.teacher_id = %d", $filters['teacher_id']);
        if (!empty($filters['semester_id']))
            $where[] = $wpdb->prepare("s.semester_id = %d", $filters['semester_id']);
        if (!empty($filters['academic_year_id']))
            $where[] = $wpdb->prepare("s.academic_year_id = %d", $filters['academic_year_id']);
        if (isset($filters['day_of_week']))
            $where[] = $wpdb->prepare("s.day_of_week = %d", $filters['day_of_week']);

        $where_sql = implode(' AND ', $where);

        return $wpdb->get_results("
            SELECT s.*, l.location_name, l.area_floor, t.slot_label, t.start_time, t.end_time, u.display_name as teacher_name
            FROM $table s
            JOIN {$wpdb->prefix}olama_shifts_locations l ON s.location_id = l.id
            JOIN {$wpdb->prefix}olama_shifts_time_slots t ON s.slot_id = t.id
            JOIN {$wpdb->users} u ON s.teacher_id = u.ID
            WHERE $where_sql
            ORDER BY s.day_of_week, t.start_time
        ");
    }

    /**
     * Check for shift conflicts
     */
    public static function check_conflict($teacher_id, $day_of_week, $slot_id, $semester_id, $exclude_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_schedule';

        $query = $wpdb->prepare("
            SELECT id FROM $table 
            WHERE teacher_id = %d 
            AND day_of_week = %d 
            AND slot_id = %d 
            AND semester_id = %d
        ", $teacher_id, $day_of_week, $slot_id, $semester_id);

        if ($exclude_id) {
            $query .= $wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return $wpdb->get_var($query);
    }

    /**
     * Save shift assignment
     */
    public static function save_assignment($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_schedule';

        $teacher_id = intval($data['teacher_id']);
        $day_of_week = intval($data['day_of_week']);
        $slot_id = intval($data['slot_id']);
        $semester_id = intval($data['semester_id']);
        $id = isset($data['id']) ? intval($data['id']) : 0;

        // Conflict check
        if (self::check_conflict($teacher_id, $day_of_week, $slot_id, $semester_id, $id)) {
            return new WP_Error('shift_conflict', __('Teacher is already assigned to another location during this shift.', 'olama-school'));
        }

        $assignment_data = array(
            'teacher_id' => $teacher_id,
            'location_id' => intval($data['location_id']),
            'day_of_week' => $day_of_week,
            'slot_id' => $slot_id,
            'semester_id' => $semester_id,
            'academic_year_id' => intval($data['academic_year_id']),
        );

        if ($id) {
            return $wpdb->update($table, $assignment_data, array('id' => $id));
        } else {
            return $wpdb->insert($table, $assignment_data);
        }
    }

    /**
     * Get weekly shifts for a specific teacher
     */
    public static function get_teacher_weekly_shifts($teacher_id)
    {
        $active_year = Olama_School_Academic::get_active_year();
        $active_sem = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;

        if (!$active_sem) {
            return array();
        }

        return self::get_schedule(array(
            'teacher_id' => $teacher_id,
            'semester_id' => $active_sem->id,
            'academic_year_id' => $active_year->id
        ));
    }

    /**
     * Bulk copy shifts from one semester to another
     */
    public static function bulk_copy($from_semester_id, $to_semester_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_schedule';

        // Clear target semester first? Or append? Usually bulk copy replaces.
        $wpdb->delete($table, array('semester_id' => $to_semester_id));

        $shifts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE semester_id = %d",
            $from_semester_id
        ), ARRAY_A);

        if (!$shifts)
            return 0;

        $count = 0;
        foreach ($shifts as $shift) {
            unset($shift['id']);
            $shift['semester_id'] = $to_semester_id;
            if ($wpdb->insert($table, $shift)) {
                $count++;
            }
        }

        return $count;
    }
}
