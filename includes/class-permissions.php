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
     * Record the capability declaration version. Grants are owned by Olama Users.
     */
    public static function init()
    {
        if (get_option('olama_school_caps_version') === OLAMA_SCHOOL_VERSION) {
            return;
        }

        update_option('olama_school_caps_version', OLAMA_SCHOOL_VERSION);
    }

    /**
     * Get all granular capabilities grouped by menu/submenu
     */
    public static function get_all_capabilities()
    {
        return array(
            'dashboard' => array(
                'label' => __('Dashboard', 'olama-school'),
                'caps' => array(
                    'olama_view_dashboard' => __('View Dashboard', 'olama-school'),
                )
            ),
            'reports' => array(
                'label' => __('Reports', 'olama-school'),
                'caps' => array(
                    'olama_access_reports' => __('Access Reports', 'olama-school'),
                    'olama_view_reports_summary' => __('Plan Completion Report', 'olama-school'),
                    'olama_view_reports_homework' => __('Homework Summary Report', 'olama-school'),
                )
            ),
            'plans' => array(
                'label' => __('Weekly Plan Management', 'olama-school'),
                'caps' => array(
                    'olama_access_plans_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_plans_list' => __('View Plan List', 'olama-school'),
                    'olama_create_plans' => __('Plan Creation', 'olama-school'),
                    'olama_manage_plans_comparison' => __('Plan Comparison', 'olama-school'),
                    'olama_manage_plans_schedule' => __('Weekly Schedule', 'olama-school'),
                    'olama_manage_plans_data' => __('Data Management', 'olama-school'),
                    'olama_manage_plans_load' => __('Plan Load', 'olama-school'),
                    'olama_view_plans_load' => __('View Plan Load', 'olama-school'),
                    'olama_manage_plans_coverage' => __('Curriculum Coverage', 'olama-school'),
                    'olama_manage_own_plans' => __('Edit Own Plans', 'olama-school'),
                    'olama_approve_plans' => __('Approve/Request Edits', 'olama-school'),
                )
            ),
            'academic' => array(
                'label' => __('Academic Management', 'olama-school'),
                'caps' => array(
                    'olama_access_academic_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_academic_calendar' => __('Academic Calendar', 'olama-school'),
                    'olama_manage_academic_grades' => __('Grades & Sections', 'olama-school'),
                    'olama_manage_academic_subjects' => __('Subjects', 'olama-school'),
                    'olama_manage_academic_assignment' => __('Assign Teachers', 'olama-school'),
                    'olama_manage_academic_stationary' => __('Stationary', 'olama-school'),
                    'olama_manage_academic_office_hours' => __('Office Hours', 'olama-school'),
                )
            ),
            'curriculum' => array(
                'label' => __('Curriculum Management', 'olama-school'),
                'caps' => array(
                    'olama_access_curriculum_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_curriculum_list' => __('Manage Curriculum', 'olama-school'),
                    'olama_manage_curriculum_timeline' => __('Timeline Management', 'olama-school'),
                    'olama_view_curriculum_timeline' => __('View Timeline', 'olama-school'),
                    'olama_manage_curriculum_upload' => __('Bulk Upload', 'olama-school'),
                    'olama_manage_curriculum_analysis' => __('Curriculum Analysis', 'olama-school'),
                )
            ),
            'settings' => array(
                'label' => __('Settings', 'olama-school'),
                'caps' => array(
                    'olama_access_settings_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_settings_general' => __('General Settings', 'olama-school'),
                    'olama_manage_settings_shortcode' => __('Shortcode Generator', 'olama-school'),
                )
            ),
        );
    }

    /**
     * Check if a user has a specific capability
     */
    public static function can($capability, $user_id = null)
    {
        if (class_exists('Olama_Core_Permissions')) {
            return Olama_Core_Permissions::can($capability, $user_id);
        }

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id)
            return false;

        return user_can($user_id, $capability);
    }

}
