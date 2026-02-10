<?php
/**
 * Bus Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Bus
{
    /**
     * Get all buses
     */
    public static function get_buses()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_transport_buses";

        return $wpdb->get_results("SELECT * FROM $table ORDER BY bus_number ASC");
    }

    /**
     * Get a single bus by ID
     */
    public static function get_bus($id)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_transport_buses";

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Save bus data (Insert or Update)
     */
    public static function save_bus($data)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_transport_buses";

        $id = isset($data['id']) ? intval($data['id']) : 0;

        $bus_data = array(
            'bus_number' => sanitize_text_field($data['bus_number']),
            'plate_number' => sanitize_text_field($data['plate_number']),
            'passenger_capacity' => intval($data['passenger_capacity']),
            'driver_user_id' => !empty($data['driver_user_id']) ? intval($data['driver_user_id']) : null,
            'companion_user_id' => !empty($data['companion_user_id']) ? intval($data['companion_user_id']) : null,
            'license_expiry_date' => !empty($data['license_expiry_date']) ? Olama_School_Helpers::sanitize_date($data['license_expiry_date']) : null,
            'engine_capacity' => sanitize_text_field($data['engine_capacity'] ?? ''),
            'fuel_type' => sanitize_text_field($data['fuel_type'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'active'),
        );

        // Validation
        if (empty($bus_data['bus_number']) || empty($bus_data['plate_number'])) {
            return new WP_Error('missing_data', __('Bus number and Plate number are required.', 'olama-school'));
        }

        if ($bus_data['passenger_capacity'] <= 0) {
            return new WP_Error('invalid_capacity', __('Passenger capacity must be greater than zero.', 'olama-school'));
        }

        if ($id > 0) {
            $updated = $wpdb->update($table, $bus_data, array('id' => $id));
            return $updated !== false ? $id : new WP_Error('db_error', __('Failed to update bus.', 'olama-school'));
        } else {
            // Check for duplicate plate number
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE plate_number = %s", $bus_data['plate_number']));
            if ($exists) {
                return new WP_Error('duplicate_plate', __('Plate number already exists.', 'olama-school'));
            }

            $inserted = $wpdb->insert($table, $bus_data);
            return $inserted ? $wpdb->insert_id : new WP_Error('db_error', __('Failed to save bus.', 'olama-school'));
        }
    }

    /**
     * Delete a bus
     */
    public static function delete_bus($id)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_transport_buses";

        return $wpdb->delete($table, array('id' => $id));
    }

    /**
     * Get available drivers (Users with specific roles or just list all for now)
     * In a real scenario, we might want to filter by a specific role like 'driver'
     */
    public static function get_available_drivers()
    {
        // For simplicity, returning all users who could be drivers. 
        // In this system, teachers/assistants might also be drivers or there's a specific role.
        return get_users(array('role__in' => array('administrator', 'editor', 'author', 'teacher', 'assistant')));
    }

    /**
     * Get available companions
     */
    public static function get_available_companions()
    {
        return get_users(array('role__in' => array('administrator', 'editor', 'author', 'teacher', 'assistant')));
    }

    /**
     * Assign students to a bus
     * 
     * @param int $bus_id Bus ID
     * @param array $student_ids Array of student IDs
     * @param int $academic_year_id Academic year ID
     * @return array|WP_Error Array with success/failure counts or WP_Error
     */
    public static function assign_students_to_bus($bus_id, $student_ids, $academic_year_id)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_student_bus_assignments";

        // Validate bus exists
        $bus = self::get_bus($bus_id);
        if (!$bus) {
            return new WP_Error('invalid_bus', __('Bus not found.', 'olama-school'));
        }

        // Check capacity
        $capacity_info = self::get_bus_capacity_info($bus_id, $academic_year_id);
        $available_seats = $capacity_info['available'];

        if (count($student_ids) > $available_seats) {
            return new WP_Error('capacity_exceeded', sprintf(
                __('Cannot assign %d students. Only %d seats available.', 'olama-school'),
                count($student_ids),
                $available_seats
            ));
        }

        $success_count = 0;
        $error_count = 0;
        $errors = array();
        $current_user_id = get_current_user_id();

        foreach ($student_ids as $student_id) {
            $student_id = intval($student_id);

            // Check if student already has a bus assignment for this year
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE student_id = %d AND academic_year_id = %d",
                $student_id,
                $academic_year_id
            ));

            if ($existing) {
                // Update existing assignment
                $result = $wpdb->update(
                    $table,
                    array(
                        'bus_id' => $bus_id,
                        'assigned_at' => current_time('mysql'),
                        'assigned_by' => $current_user_id
                    ),
                    array('id' => $existing)
                );
            } else {
                // Create new assignment
                $result = $wpdb->insert(
                    $table,
                    array(
                        'student_id' => $student_id,
                        'bus_id' => $bus_id,
                        'academic_year_id' => $academic_year_id,
                        'assigned_at' => current_time('mysql'),
                        'assigned_by' => $current_user_id
                    )
                );
            }

            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = $student_id;
            }
        }

        return array(
            'success' => $success_count,
            'errors' => $error_count,
            'error_ids' => $errors
        );
    }

    /**
     * Unassign a student from their bus
     * 
     * @param int $student_id Student ID
     * @param int $academic_year_id Academic year ID
     * @return bool True on success, false on failure
     */
    public static function unassign_student($student_id, $academic_year_id)
    {
        global $wpdb;
        $table = "{$wpdb->prefix}olama_student_bus_assignments";

        $result = $wpdb->delete(
            $table,
            array(
                'student_id' => $student_id,
                'academic_year_id' => $academic_year_id
            )
        );

        return $result !== false;
    }

    /**
     * Get all students assigned to a bus
     * 
     * @param int $bus_id Bus ID
     * @param int $academic_year_id Academic year ID
     * @return array Array of student objects with assignment info
     */
    public static function get_bus_students($bus_id, $academic_year_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT s.*, a.id as assignment_id, a.pickup_location, a.dropoff_location, 
                   a.notes, a.assigned_at, sec.section_name, g.grade_name
            FROM {$wpdb->prefix}olama_student_bus_assignments a
            JOIN {$wpdb->prefix}olama_students s ON a.student_id = s.id
            LEFT JOIN {$wpdb->prefix}olama_student_enrollment e ON s.id = e.student_id AND e.academic_year_id = %d
            LEFT JOIN {$wpdb->prefix}olama_sections sec ON e.section_id = sec.id
            LEFT JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            WHERE a.bus_id = %d AND a.academic_year_id = %d
            ORDER BY s.student_name ASC
        ", $academic_year_id, $bus_id, $academic_year_id));
    }

    /**
     * Get the bus assigned to a student
     * 
     * @param int $student_id Student ID
     * @param int $academic_year_id Academic year ID
     * @return object|null Bus assignment object or null
     */
    public static function get_student_bus($student_id, $academic_year_id)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("
            SELECT a.*, b.bus_number, b.plate_number, b.passenger_capacity
            FROM {$wpdb->prefix}olama_student_bus_assignments a
            JOIN {$wpdb->prefix}olama_transport_buses b ON a.bus_id = b.id
            WHERE a.student_id = %d AND a.academic_year_id = %d
        ", $student_id, $academic_year_id));
    }

    /**
     * Get bus capacity information
     * 
     * @param int $bus_id Bus ID
     * @param int $academic_year_id Academic year ID
     * @return array Capacity info with total, assigned, and available counts
     */
    public static function get_bus_capacity_info($bus_id, $academic_year_id)
    {
        global $wpdb;

        $bus = self::get_bus($bus_id);
        if (!$bus) {
            return array('total' => 0, 'assigned' => 0, 'available' => 0);
        }

        $assigned_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}olama_student_bus_assignments 
            WHERE bus_id = %d AND academic_year_id = %d
        ", $bus_id, $academic_year_id));

        $total = intval($bus->passenger_capacity);
        $assigned = intval($assigned_count);
        $available = max(0, $total - $assigned);

        return array(
            'total' => $total,
            'assigned' => $assigned,
            'available' => $available,
            'percentage' => $total > 0 ? round(($assigned / $total) * 100) : 0
        );
    }
}
