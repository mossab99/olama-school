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
            'gender' => sanitize_text_field($data['gender'] ?? 'mixed'),
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
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if ($id) {
            return $wpdb->update($table, $slot_data, array('id' => $id));
        } else {
            return $wpdb->insert($table, $slot_data);
        }
    }

    /**
     * Get Shift Periods
     */
    public static function get_periods()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_periods';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    }

    /**
     * Save or update Period
     */
    public static function save_period($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_shifts_periods';

        $id = isset($data['id']) ? intval($data['id']) : 0;
        $academic_year_id = intval($data['academic_year_id']);
        $semester_id = intval($data['semester_id']);
        $shift_type = sanitize_text_field($data['shift_type']);

        // Prevent duplicates
        if (!$id) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE academic_year_id = %d AND semester_id = %d AND shift_type = %s",
                $academic_year_id,
                $semester_id,
                $shift_type
            ));
            if ($exists) {
                return $exists; // Return existing ID if it already exists
            }
        }

        $period_data = array(
            'academic_year_id' => $academic_year_id,
            'semester_id' => $semester_id,
            'shift_type' => $shift_type,
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1,
        );

        if ($id) {
            return $wpdb->update($table, $period_data, array('id' => $id));
        } else {
            return $wpdb->insert($table, $period_data);
        }
    }

    /**
     * Get schedule with filters (Optimized for new architecture)
     */
    public static function get_schedule($period_id)
    {
        global $wpdb;
        $period_id = intval($period_id);

        if (!$period_id)
            return array();

        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.id as shift_id, s.day_of_week, 
                l.location_name, l.area_floor, l.gender as location_gender,
                t.slot_label, t.start_time, t.end_time,
                GROUP_CONCAT(u.display_name SEPARATOR ', ') as teacher_names,
                GROUP_CONCAT(u.ID SEPARATOR ',') as teacher_ids
            FROM {$wpdb->prefix}olama_shifts s
            JOIN {$wpdb->prefix}olama_shifts_locations l ON s.location_id = l.id
            JOIN {$wpdb->prefix}olama_shifts_time_slots t ON s.slot_id = t.id
            LEFT JOIN {$wpdb->prefix}olama_shifts_assignments sa ON sa.shift_id = s.id
            LEFT JOIN {$wpdb->users} u ON sa.teacher_id = u.ID
            WHERE s.period_id = %d
            GROUP BY s.id
            ORDER BY s.day_of_week, t.start_time
        ", $period_id));
    }

    /**
     * Save shift and assignments
     */
    public static function save_shift_and_assignments($data)
    {
        global $wpdb;
        $shifts_table = $wpdb->prefix . 'olama_shifts';
        $assignments_table = $wpdb->prefix . 'olama_shifts_assignments';

        $period_id = intval($data['period_id']);
        $day_of_week = intval($data['day_of_week']);
        $slot_id = intval($data['slot_id']);
        $location_id = intval($data['location_id']);
        $teacher_ids = isset($data['teacher_ids']) ? array_map('intval', (array) $data['teacher_ids']) : array();

        // Check for location overlap (same period, same day, same slot, same location)
        $existing_shift_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $shifts_table 
            WHERE period_id = %d AND day_of_week = %d AND slot_id = %d AND location_id = %d
        ", $period_id, $day_of_week, $slot_id, $location_id));

        $wpdb->query('START TRANSACTION');

        try {
            if ($existing_shift_id) {
                $shift_id = $existing_shift_id;
            } else {
                $wpdb->insert($shifts_table, array(
                    'period_id' => $period_id,
                    'day_of_week' => $day_of_week,
                    'slot_id' => $slot_id,
                    'location_id' => $location_id
                ));
                $shift_id = $wpdb->insert_id;
            }

            // Conflict detection for teachers
            foreach ($teacher_ids as $teacher_id) {
                $conflict = $wpdb->get_var($wpdb->prepare("
                    SELECT s.id 
                    FROM {$wpdb->prefix}olama_shifts s
                    JOIN {$wpdb->prefix}olama_shifts_assignments sa ON sa.shift_id = s.id
                    WHERE sa.teacher_id = %d 
                    AND s.period_id = %d 
                    AND s.day_of_week = %d 
                    AND s.slot_id = %d
                    AND s.id != %d
                ", $teacher_id, $period_id, $day_of_week, $slot_id, $shift_id));

                if ($conflict) {
                    $teacher_user = get_userdata($teacher_id);
                    $teacher_name = $teacher_user ? $teacher_user->display_name : '#' . $teacher_id;
                    throw new Exception(sprintf(__('Teacher %s is already assigned to another location during this shift.', 'olama-school'), $teacher_name));
                }
            }

            // Update assignments: Clear and re-add
            $wpdb->delete($assignments_table, array('shift_id' => $shift_id));
            foreach ($teacher_ids as $teacher_id) {
                $wpdb->insert($assignments_table, array(
                    'shift_id' => $shift_id,
                    'teacher_id' => $teacher_id,
                    'role' => 'primary'
                ));
            }

            $wpdb->query('COMMIT');
            return $shift_id;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('shift_save_error', $e->getMessage());
        }
    }

    /**
     * Get weekly shifts for a specific teacher (Legacy compatibility / Mobile)
     */
    public static function get_teacher_weekly_shifts($teacher_id)
    {
        global $wpdb;
        // Find active periods
        $periods = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}olama_shifts_periods WHERE is_active = 1");
        if (empty($periods))
            return array();

        $placeholders = implode(',', array_fill(0, count($periods), '%d'));

        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.id as shift_id, s.day_of_week, 
                l.location_name, l.area_floor,
                t.slot_label, t.start_time, t.end_time,
                p.shift_type
            FROM {$wpdb->prefix}olama_shifts s
            JOIN {$wpdb->prefix}olama_shifts_locations l ON s.location_id = l.id
            JOIN {$wpdb->prefix}olama_shifts_time_slots t ON s.slot_id = t.id
            JOIN {$wpdb->prefix}olama_shifts_periods p ON s.period_id = p.id
            JOIN {$wpdb->prefix}olama_shifts_assignments sa ON sa.shift_id = s.id
            WHERE sa.teacher_id = %d AND s.period_id IN ($placeholders)
            ORDER BY s.day_of_week, t.start_time
        ", array_merge(array($teacher_id), $periods)));
    }

    /**
     * Delete a shift and its assignments
     */
    public static function delete_shift($shift_id)
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'olama_shifts_assignments', array('shift_id' => intval($shift_id)));
        return $wpdb->delete($wpdb->prefix . 'olama_shifts', array('id' => intval($shift_id)));
    }
}
