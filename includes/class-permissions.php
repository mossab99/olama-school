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
    /**
     * Set up default roles and capabilities
     * Only runs if capabilities haven't been added yet
     */
    public static function init()
    {
        // Skip if capabilities already initialized and exams caps are added
        $initialized = get_option('olama_school_caps_version') === OLAMA_SCHOOL_VERSION;
        $exams_added = get_option('olama_school_exams_caps_added');
        $roles_exist = get_role('supervisor') && get_role('teacher') && get_role('assistant') && get_role('accountant');

        if ($initialized && $exams_added && $roles_exist) {
            return;
        }

        // Run capability setup only once or if explicit sync flag missing
        self::add_capabilities();
        update_option('olama_school_caps_version', OLAMA_SCHOOL_VERSION);
        update_option('olama_school_exams_caps_added', true);
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
            'exams' => array(
                'label' => __('Exam Management', 'olama-school'),
                'caps' => array(
                    'olama_access_exams_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_exams_schedule' => __('Exam Schedule', 'olama-school'),
                    'olama_fill_exam_details' => __('Fill Exam Details', 'olama-school'),
                    'olama_upload_exam_files' => __('Upload Exam Files', 'olama-school'),
                    'olama_manage_question_bank' => __('Question Bank (Exam Engine)', 'olama-school'),
                    'olama_create_exams' => __('Create / Edit Exams (Exam Engine)', 'olama-school'),
                    'olama_grade_exams' => __('Grade Essays (Exam Engine)', 'olama-school'),
                    'olama_view_exam_results' => __('View Results (Exam Engine)', 'olama-school'),
                )
            ),
            'evaluation' => array(
                'label' => __('Evaluation', 'olama-school'),
                'caps' => array(
                    'olama_access_evaluation' => __('Access Evaluation', 'olama-school'),
                    'olama_manage_evaluation_students' => __('Student Evaluation', 'olama-school'),
                    'olama_manage_evaluation_progress' => __('Evaluation Progress', 'olama-school'),
                    'olama_manage_evaluation_mgmt' => __('Evaluation Management', 'olama-school'),
                    'olama_manage_lesson_planner' => __('Lesson Planner', 'olama-school'),
                )
            ),
            'users' => array(
                'label' => __('Users & Permissions', 'olama-school'),
                'caps' => array(
                    'olama_access_users_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_users_families' => __('Manage Families', 'olama-school'),
                    'olama_manage_users_students' => __('Manage Students / Enrollment', 'olama-school'),
                    'olama_manage_users_teachers' => __('Manage Teachers', 'olama-school'),
                    'olama_manage_users_permissions' => __('Manage Permissions', 'olama-school'),
                    'olama_manage_users_logs' => __('View Activity Logs', 'olama-school'),
                )
            ),
            'media' => array(
                'label' => __('Media Library', 'olama-school'),
                'caps' => array(
                    'olama_access_media_library' => __('Access Media Library', 'olama-school'),
                    'olama_media_upload_video' => __('Upload Video', 'olama-school'),
                    'olama_media_drive_settings' => __('Drive Settings', 'olama-school'),
                    'olama_media_view_logs' => __('Upload Log', 'olama-school'),
                    'olama_media_approve_video' => __('Approve Video', 'olama-school'),
                )
            ),
            'settings' => array(
                'label' => __('Settings', 'olama-school'),
                'caps' => array(
                    'olama_access_settings_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_settings_general' => __('General Settings', 'olama-school'),
                )
            ),
            'followup' => array(
                'label' => __('Follow Up', 'olama-school'),
                'caps' => array(
                    'olama_access_followup' => __('Access Management', 'olama-school'),
                    'olama_manage_attendance' => __('Student Attendance', 'olama-school'),
                    'olama_manage_shifts' => __('Employee Shifts', 'olama-school'),
                    'olama_manage_cleaning' => __('Toilet Cleaning Follow-up', 'olama-school'),
                    'olama_configure_cleaning' => __('Configure Cleaning Settings', 'olama-school'),
                )
            ),
            'transportation' => array(
                'label' => __('Transportation', 'olama-school'),
                'caps' => array(
                    'olama_access_transport_mgmt' => __('Access Management', 'olama-school'),
                    'olama_manage_transport_buses' => __('Manage Buses', 'olama-school'),
                )
            ),
            'supervision' => array(
                'label' => __('Academic Supervision', 'olama-school'),
                'caps' => array(
                    'olama_access_supervision' => __('Access Supervision', 'olama-school'),
                    'olama_manage_supervision_plan' => __('Plan Visit', 'olama-school'),
                    'olama_view_supervision_reports' => __('Reports', 'olama-school'),
                    'olama_view_supervision_analytics' => __('Analytics', 'olama-school'),
                )
            ),
            'kg' => array(
                'label' => __('KG الروضة', 'olama-school'),
                'caps' => array(
                    'olama_access_kg' => __('Access KG Module', 'olama-school'),
                    'olama_manage_kg_photo_session' => __('Photo Session', 'olama-school'),
                    'olama_manage_kg_graduation_session' => __('Graduation Session', 'olama-school'),
                )
            ),
            'exam_halls' => array(
                'label' => __('Exam Hall Distribution', 'olama-school'),
                'caps' => array(
                    'olama_access_exam_halls'      => __('Access Exam Hall Module', 'olama-school'),
                    'olama_manage_exam_halls'      => __('Manage Halls & Distribution (Admin)', 'olama-school'),
                    'olama_manage_hall_attendance' => __('Record Attendance & Behavior Notes', 'olama-school'),
                )
            ),
            'registration' => array(
                'label' => __('Registration & Billing', 'olama-school'),
                'caps' => array(
                    'olama_access_registration' => __('Access Registration', 'olama-school'),
                    'olama_manage_registration_families' => __('Manage Families', 'olama-school'),
                    'olama_manage_registration_students' => __('Manage Students', 'olama-school'),
                    'olama_manage_registration_fees' => __('Fee Templates', 'olama-school'),
                    'olama_manage_registration_invoices' => __('Invoices', 'olama-school'),
                    'olama_manage_registration_payments' => __('Payments', 'olama-school'),
                    'olama_manage_registration_reports' => __('Billing Reports', 'olama-school'),
                )
            ),
            'stores' => array(
                'label' => __('Stores & Inventory', 'olama-school'),
                'caps' => array(
                    'os_view_items'           => __( 'View Item Registry', 'olama-stores' ),
                    'os_manage_items'         => __( 'Add / Edit Items', 'olama-stores' ),
                    'os_delete_items'         => __( 'Delete Items', 'olama-stores' ),
                    'os_view_stock'           => __( 'View Stock Levels', 'olama-stores' ),
                    'os_receive_stock'        => __( 'Record Stock Receipt', 'olama-stores' ),
                    'os_adjust_stock'         => __( 'Manual Stock Adjustment', 'olama-stores' ),
                    'os_process_assignments'  => __( 'Issue & Return Items', 'olama-stores' ),
                    'os_view_assignments'     => __( 'View Assignments', 'olama-stores' ),
                    'os_run_inventory_count'  => __( 'Run Inventory Count', 'olama-stores' ),
                    'os_manage_transfers'     => __( 'Manage Warehouse Transfers', 'olama-stores' ),
                    'os_view_reports'         => __( 'View Reports', 'olama-stores' ),
                    'os_manage_settings'      => __( 'Manage Stores Settings', 'olama-stores' ),
                    'os_manage_warehouses'    => __( 'Manage Warehouses', 'olama-stores' ),
                    'os_view_audit_log'       => __( 'View Audit Log', 'olama-stores' ),
                    'os_manage_order_estimation' => __('Order Estimation', 'olama-stores'),
                )
            ),
        );
    }

    /**
     * Add custom capabilities to roles
     */
    public static function add_capabilities()
    {
        // Ensure custom roles exist
        if (!get_role('teacher')) {
            add_role('teacher', __('Teacher', 'olama-school'), get_role('author')->capabilities);
        }
        if (!get_role('supervisor')) {
            add_role('supervisor', __('Supervisor', 'olama-school'), get_role('editor')->capabilities);
        }
        if (!get_role('assistant')) {
            add_role('assistant', __('Assistant', 'olama-school'), get_role('author')->capabilities);
        }
        if (!get_role('accountant')) {
            add_role('accountant', __('Accountant', 'olama-school'), get_role('author')->capabilities);
        }

        $all_groups = self::get_all_capabilities();
        $roles = array('administrator', 'editor', 'supervisor', 'author', 'teacher', 'assistant', 'accountant', 'os_warehouse_manager', 'os_warehouse_staff', 'os_viewer');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            foreach ($all_groups as $group) {
                foreach ($group['caps'] as $cap => $label) {
                    // Admins get everything
                    if ($role_name === 'administrator') {
                        $role->add_cap($cap);
                    } else {
                        // For other roles, we only add legacy defaults if it's the first time
                        // or they had the old general cap.
                        if ($role_name === 'editor' || $role_name === 'supervisor') {
                            $role->add_cap($cap); // Editors and Supervisors get most things by default
                        } elseif ($role_name === 'author' || $role_name === 'teacher' || $role_name === 'assistant') {
                            // Map teachers/authors to restricted set
                            $teacher_caps = array(
                                'olama_view_dashboard',
                                'olama_access_plans_mgmt',
                                'olama_manage_plans_list',
                                'olama_create_plans',
                                'olama_manage_own_plans',
                                'olama_access_academic_mgmt',
                                'olama_manage_academic_office_hours',
                                'olama_access_curriculum_mgmt',
                                'olama_manage_curriculum_timeline',
                                'olama_view_curriculum_timeline',
                                'olama_manage_curriculum_analysis',
                                'olama_access_evaluation',
                                'olama_manage_evaluation_students',
                                'olama_manage_evaluation_progress',
                                'olama_access_exams_mgmt',
                                'olama_fill_exam_details',
                                'olama_upload_exam_files',
                                'olama_manage_question_bank',
                                'olama_create_exams',
                                'olama_grade_exams',
                                'olama_view_exam_results',
                                'olama_access_reports',
                                'olama_view_reports_summary',
                                'olama_view_plans_load',
                                'olama_access_followup',
                                'olama_manage_attendance',
                                'olama_manage_cleaning',
                                'olama_configure_cleaning',
                                'olama_manage_lesson_planner',
                                'olama_access_media_library',
                                'olama_media_upload_video',
                                // Exam Hall – teachers can view & record attendance/notes
                                'olama_access_exam_halls',
                                'olama_manage_hall_attendance',
                            );
                            if (in_array($cap, $teacher_caps)) {
                                $role->add_cap($cap);
                            }
                        }
                    }
                }
            }

            // Keep legacy caps for transition compatibility
            $role->add_cap('olama_view_plans');
            $role->add_cap('olama_view_reports');
            if ($role_name === 'administrator' || $role_name === 'editor' || $role_name === 'supervisor') {
                $role->add_cap('olama_access_settings_mgmt');
                $role->add_cap('olama_manage_settings_general');
                $role->add_cap('olama_manage_settings');
                $role->add_cap('olama_manage_academic_structure');
                $role->add_cap('olama_manage_curriculum');
                $role->add_cap('olama_import_export_data');
                $role->add_cap('olama_view_logs');
                $role->add_cap('olama_approve_plans');
                $role->add_cap('olama_manage_plans');
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

        if (!$user_id)
            return false;

        // Super admins with manage_options always have all capabilities
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Supervisors have full access to all olama modules
        $user = get_userdata($user_id);
        if ($user && in_array('supervisor', (array) $user->roles) && strpos($capability, 'olama_') === 0) {
            return true;
        }

        return user_can($user_id, $capability);
    }

    /**
     * Remove custom capabilities (for deactivation)
     */
    public static function remove_capabilities()
    {
        $all_groups = self::get_all_capabilities();
        $roles = array('administrator', 'editor', 'supervisor', 'author', 'teacher', 'assistant', 'accountant', 'os_warehouse_manager', 'os_warehouse_staff', 'os_viewer');

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($all_groups as $group) {
                    foreach ($group['caps'] as $cap => $label) {
                        $role->remove_cap($cap);
                    }
                }

                // Legacy caps
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
                $role->remove_cap('olama_manage_plans');
            }
        }
    }
}