<?php
/**
 * Family Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Family
{
    /**
     * Get all families
     */
    public static function get_families()
    {
        global $wpdb;
        $families = $wpdb->get_results("SELECT f.*, 
                                        (SELECT COUNT(*) FROM {$wpdb->prefix}olama_students s WHERE s.family_id = f.family_uid) as student_count
                                        FROM {$wpdb->prefix}olama_families f 
                                        ORDER BY f.family_name ASC");
        return $families;
    }

    /**
     * Get a single family by ID or UID
     */
    public static function get_family($id_or_uid)
    {
        global $wpdb;
        $field = is_numeric($id_or_uid) ? 'id' : 'family_uid';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_families WHERE $field = %s",
            $id_or_uid
        ));
    }

    /**
     * Save/Update family
     */
    public static function save_family($data)
    {
        global $wpdb;

        $family_data = array(
            'family_uid' => sanitize_text_field($data['family_uid']),
            'family_name' => sanitize_text_field($data['family_name']),
            'mother_mobile' => sanitize_text_field($data['mother_mobile'] ?? ''),
            'father_mobile' => sanitize_text_field($data['father_mobile'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? '')
        );

        if (!empty($data['id'])) {
            $result = $wpdb->update(
                "{$wpdb->prefix}olama_families",
                $family_data,
                array('id' => intval($data['id']))
            );
            return $result !== false ? intval($data['id']) : false;
        } else {
            // Check for duplicate UID
            $exists = self::get_family($family_data['family_uid']);
            if ($exists) {
                return new WP_Error('duplicate_family_uid', __('Family ID already exists.', 'olama-school'));
            }

            $result = $wpdb->insert("{$wpdb->prefix}olama_families", $family_data);
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Delete family
     */
    public static function delete_family($id)
    {
        global $wpdb;

        // Check for linked students
        $family = self::get_family($id);
        if ($family) {
            $student_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_students WHERE family_id = %s",
                $family->family_uid
            ));

            if ($student_count > 0) {
                return new WP_Error('linked_students', __('Cannot delete family with linked students.', 'olama-school'));
            }

            return $wpdb->delete("{$wpdb->prefix}olama_families", array('id' => $id));
        }

        return false;
    }

    /**
     * Delete ALL families
     */
    public static function delete_all_families()
    {
        global $wpdb;

        // Check for ANY students first as a safety measure
        $student_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_students");
        if ($student_count > 0) {
            return new WP_Error('linked_students_exist', __('Cannot delete all families while students exist. Delete all students first.', 'olama-school'));
        }

        return $wpdb->query("DELETE FROM {$wpdb->prefix}olama_families");
    }

    /**
     * Get students belonging to a family
     */
    public static function get_family_students($family_uid)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_students WHERE family_id = %s",
            $family_uid
        ));
    }
}
