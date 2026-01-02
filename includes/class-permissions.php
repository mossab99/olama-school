<?php
/**
 * Olama School Permissions Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Permissions
{
    /**
     * Set up default roles and capabilities
     * Only runs if capabilities haven't been added yet
     */
    public static function init()
    {
        // Skip if capabilities already initialized (check option)
        if (get_option('olama_school_caps_version') === OLAMA_SCHOOL_VERSION) {
            return;
        }

        // Run capability setup only once
        self::add_capabilities();
        update_option('olama_school_caps_version', OLAMA_SCHOOL_VERSION);
    }

    /**
     * Add custom capabilities to roles
     */
    public static function add_capabilities()
    {
        // Ensure teacher role exists
        if (!get_role('teacher')) {
            add_role('teacher', __('Teacher', 'olama-school'), get_role('author')->capabilities);
        }

        $roles = array('administrator', 'editor', 'author', 'teacher');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            // Common capabilities
            $role->add_cap('olama_view_plans');
            $role->add_cap('olama_view_reports');

            // Administrative capabilities
            if ($role_name === 'administrator' || $role_name === 'editor') {
                $role->add_cap('olama_manage_settings');
                $role->add_cap('olama_manage_academic_structure');
                $role->add_cap('olama_manage_curriculum');
                $role->add_cap('olama_import_export_data');
                $role->add_cap('olama_view_logs');
                $role->add_cap('olama_approve_plans');
            }

            // Teacher specific capabilities (Author and Teacher role mapping)
            if ($role_name === 'author' || $role_name === 'teacher') {
                $role->add_cap('olama_create_plans');
                $role->add_cap('olama_manage_own_plans');
            }
        }
    }

    /**
     * Check if a user has a specific capability
     */
    public static function can($capability, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return user_can($user_id, $capability);
    }

    /**
     * Remove custom capabilities (for deactivation)
     */
    public static function remove_capabilities()
    {
        $roles = array('administrator', 'editor', 'author', 'teacher');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->remove_cap('olama_view_plans');
                $role->remove_cap('olama_view_reports');
                $role->remove_cap('olama_manage_settings');
                $role->remove_cap('olama_manage_academic_structure');
                $role->remove_cap('olama_manage_curriculum');
                $role->remove_cap('olama_import_export_data');
                $role->remove_cap('olama_view_logs');
                $role->remove_cap('olama_approve_plans');
                $role->remove_cap('olama_create_plans');
                $role->remove_cap('olama_manage_own_plans');
            }
        }
    }
}
