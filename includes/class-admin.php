<?php
/**
 * Admin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->maybe_update_db();
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_schedule_save'));
        add_action('admin_init', array($this, 'handle_plan_load_save'));

        // Curriculum AJAX Handlers
        add_action('wp_ajax_olama_save_curriculum_unit', array($this, 'ajax_save_curriculum_unit'));
        add_action('wp_ajax_olama_get_curriculum_units', array($this, 'ajax_get_curriculum_units'));
        add_action('wp_ajax_olama_delete_curriculum_unit', array($this, 'ajax_delete_curriculum_unit'));

        add_action('wp_ajax_olama_save_curriculum_lesson', array($this, 'ajax_save_curriculum_lesson'));
        add_action('wp_ajax_olama_get_curriculum_lessons', array($this, 'ajax_get_curriculum_lessons'));
        add_action('wp_ajax_olama_delete_curriculum_lesson', array($this, 'ajax_delete_curriculum_lesson'));

        add_action('wp_ajax_olama_save_curriculum_question', array($this, 'ajax_save_curriculum_question'));
        add_action('wp_ajax_olama_get_curriculum_questions', array($this, 'ajax_get_curriculum_questions'));
        add_action('wp_ajax_olama_delete_curriculum_question', array($this, 'ajax_delete_curriculum_question'));
        add_action('wp_ajax_olama_get_scheduled_subjects', array($this, 'ajax_get_scheduled_subjects'));
        add_action('wp_ajax_olama_get_subjects_by_grade', array($this, 'ajax_get_subjects_by_grade'));
        add_action('wp_ajax_olama_delete_plan', array($this, 'ajax_delete_plan'));

        // Timeline AJAX Handlers
        add_action('wp_ajax_olama_get_timeline_data', array($this, 'ajax_get_timeline_data'));
        add_action('wp_ajax_olama_save_timeline_dates', array($this, 'ajax_save_timeline_dates'));
        add_action('wp_ajax_olama_bulk_approve_plans', array($this, 'ajax_bulk_approve_plans'));

        // Teacher Assignment AJAX Handlers
        add_action('wp_ajax_olama_get_teacher_assignments', array($this, 'ajax_get_teacher_assignments'));
        add_action('wp_ajax_olama_get_teacher_summary', array($this, 'ajax_get_teacher_summary'));
        add_action('wp_ajax_olama_get_sections_by_grade', array($this, 'ajax_get_sections_by_grade'));
        add_action('wp_ajax_olama_toggle_teacher_assignment', array($this, 'ajax_toggle_teacher_assignment'));

        // Whitelabel Footer
        add_filter('admin_footer_text', array($this, 'whitelabel_footer'));
        add_filter('update_footer', array($this, 'whitelabel_footer'), 11);
    }

    /**
     * Whitelabel footer text
     */
    public function whitelabel_footer($text)
    {
        if (isset($_GET['page']) && strpos($_GET['page'], 'olama-school') !== false) {
            return '';
        }
        return $text;
    }

    /**
     * Check if DB needs update
     */
    private function maybe_update_db()
    {
        $installed_ver = get_option('olama_school_db_version');
        if ($installed_ver !== OLAMA_SCHOOL_VERSION) {
            $olama_db = new Olama_School_DB();
            $olama_db->create_tables();
            update_option('olama_school_db_version', OLAMA_SCHOOL_VERSION);
        }
    }

    /**
     * Handle CSV Export
     */
    public function handle_export()
    {
        if (isset($_POST['olama_export']) && $_POST['olama_export'] === 'true') {
            Olama_School_Exporter::export_plans_csv();
        }

        // Handle Curriculum Export
        if (isset($_POST['olama_export_curriculum']) && $_POST['olama_export_curriculum'] === 'true') {
            Olama_School_Exporter::export_curriculum_csv(
                $_POST['semester_id'] ?? 0,
                $_POST['grade_id'] ?? 0,
                $_POST['subject_id'] ?? 0
            );
        }

        if (isset($_FILES['olama_import_file'])) {
            $type = isset($_POST['olama_import_type']) ? $_POST['olama_import_type'] : 'plans';
            if ($type === 'students') {
                Olama_School_Importer::import_students_csv();
            } elseif ($type === 'curriculum') {
                Olama_School_Importer::import_curriculum_csv(
                    $_POST['semester_id'] ?? 0,
                    $_POST['grade_id'] ?? 0,
                    $_POST['subject_id'] ?? 0
                );
            } else {
                Olama_School_Importer::import_plans_csv();
            }
        }
    }

    /**
     * Handle Plan Load Settings Save
     */
    public function handle_plan_load_save()
    {
        if (isset($_POST['olama_save_plan_load']) && check_admin_referer('olama_save_plan_load', 'olama_plan_load_nonce')) {
            $grade_limits = $_POST['grade_limit'] ?? [];
            $subject_limits = $_POST['subject_limit'] ?? [];
            $errors = [];

            // 1. Update Grade Limits and fetch for constraint check
            $current_grade_limits = [];
            foreach ($grade_limits as $grade_id => $limit) {
                $grade_id = intval($grade_id);
                $limit = intval($limit);

                // Fetch existing grade to preserve other fields (like periods_count)
                $existing_grade = Olama_School_Grade::get_grade($grade_id);
                if ($existing_grade) {
                    Olama_School_Grade::update_grade($grade_id, array(
                        'grade_name' => $existing_grade->grade_name,
                        'grade_level' => $existing_grade->grade_level,
                        'periods_count' => $existing_grade->periods_count,
                        'max_weekly_plans' => $limit
                    ));
                    $current_grade_limits[$grade_id] = $limit;
                }
            }

            // 2. Validate and Update Subject Limits with individual & sum constraints
            $grade_subject_sums = [];
            foreach ($subject_limits as $subject_id => $limit) {
                $subject_id = intval($subject_id);
                $limit = intval($limit);
                $subject = Olama_School_Subject::get_subject($subject_id);

                if ($subject) {
                    $grade_id = $subject->grade_id;
                    $grade_limit = $current_grade_limits[$grade_id] ?? 0;

                    if ($grade_limit > 0) {
                        // Individual check
                        if ($limit > $grade_limit) {
                            $errors[] = sprintf(__('Subject "%s" limit (%d) was reduced to match Grade limit (%d).', 'olama-school'), $subject->subject_name, $limit, $grade_limit);
                            $limit = $grade_limit;
                        }

                        // Sum check (running total)
                        $grade_subject_sums[$grade_id] = ($grade_subject_sums[$grade_id] ?? 0) + $limit;
                        if ($grade_subject_sums[$grade_id] > $grade_limit) {
                            $excess = $grade_subject_sums[$grade_id] - $grade_limit;
                            $adjusted_limit = max(0, $limit - $excess);
                            $errors[] = sprintf(__('Total limits for grade exceeded capacity. Adjusted "%s" to %d.', 'olama-school'), $subject->subject_name, $adjusted_limit);
                            $limit = $adjusted_limit;
                            $grade_subject_sums[$grade_id] = $grade_limit; // Cap the sum
                        }
                    }

                    Olama_School_Subject::update_subject($subject_id, array(
                        'subject_name' => $subject->subject_name,
                        'subject_code' => $subject->subject_code,
                        'grade_id' => $subject->grade_id,
                        'color_code' => $subject->color_code,
                        'max_weekly_plans' => $limit
                    ));
                }
            }

            $redirect_url = admin_url('admin.php?page=olama-school-plans&tab=load');

            if (!empty($errors)) {
                set_transient('olama_plan_load_errors', $errors, 45);
                $redirect_url = add_query_arg('message', 'plan_load_warning', $redirect_url);
            } else {
                $redirect_url = add_query_arg('message', 'plan_load_saved', $redirect_url);
            }

            if (!empty($_POST['manage_grade_id'])) {
                $redirect_url = add_query_arg('manage_grade', intval($_POST['manage_grade_id']), $redirect_url);
            }

            wp_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handle Schedule Save
     */
    public function handle_schedule_save()
    {
        if (isset($_POST['olama_save_bulk_schedule']) && check_admin_referer('olama_save_bulk_schedule', 'olama_schedule_nonce')) {
            $semester_id = intval($_POST['semester_id']);
            $section_id = intval($_POST['section_id']);
            $schedule_data = $_POST['schedule'] ?? [];

            Olama_School_Schedule::save_bulk_schedule($section_id, $semester_id, $schedule_data);

            $url = add_query_arg(array(
                'grade_id' => intval($_POST['grade_id']),
                'section_id' => $section_id,
                'semester_id' => $semester_id,
                'message' => 'schedule_saved'
            ), admin_url('admin.php?page=olama-school-plans&tab=schedule'));

            wp_redirect($url);
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_full_schedule' && isset($_GET['section_id']) && isset($_GET['semester_id'])) {
            check_admin_referer('olama_delete_full_schedule');
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}olama_schedule", array(
                'section_id' => intval($_GET['section_id']),
                'semester_id' => intval($_GET['semester_id'])
            ));

            wp_redirect(remove_query_arg(array('action', 'section_id', 'semester_id', '_wpnonce')));
            exit;
        }
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('Olama School', 'olama-school'),
            __('Olama School', 'olama-school'),
            'olama_view_plans',
            'olama-school',
            array($this, 'render_dashboard_page'),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page(
            'olama-school',
            __('Dashboard', 'olama-school'),
            __('Dashboard', 'olama-school'),
            'olama_view_plans',
            'olama-school',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'olama-school',
            __('Reports', 'olama-school'),
            __('Reports', 'olama-school'),
            'olama_view_reports',
            'olama-school-reports',
            array($this, 'render_reports_page')
        );

        add_submenu_page(
            'olama-school',
            __('Weekly Plan Management', 'olama-school'),
            __('Weekly Plan Management', 'olama-school'),
            'olama_view_plans',
            'olama-school-plans',
            array($this, 'render_weekly_plan_management_page')
        );

        add_submenu_page(
            'olama-school',
            __('Academic Management', 'olama-school'),
            __('Academic Management', 'olama-school'),
            'olama_manage_academic_structure',
            'olama-school-academic',
            array($this, 'render_academic_management_page')
        );

        add_submenu_page(
            'olama-school',
            __('Curriculum Management', 'olama-school'),
            __('Curriculum Management', 'olama-school'),
            'olama_manage_curriculum',
            'olama-school-curriculum',
            array($this, 'render_curriculum_management_page')
        );

        add_submenu_page(
            'olama-school',
            __('Users & Permissions', 'olama-school'),
            __('Users & Permissions', 'olama-school'),
            'olama_manage_settings',
            'olama-school-users',
            array($this, 'render_users_page')
        );

        add_submenu_page(
            'olama-school',
            __('Settings', 'olama-school'),
            __('Settings', 'olama-school'),
            'olama_manage_settings',
            'olama-school-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('olama_school_settings_group', 'olama_school_settings');
        register_setting('olama_notifications_group', 'olama_admin_email');
        register_setting('olama_notifications_group', 'olama_enable_notifs');
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'olama-school') === false) {
            return;
        }

        wp_enqueue_style('olama-admin-style', OLAMA_SCHOOL_URL . 'assets/css/admin.css', array(), OLAMA_SCHOOL_VERSION);
        wp_enqueue_script('olama-admin-script', OLAMA_SCHOOL_URL . 'assets/js/admin.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-script', OLAMA_SCHOOL_URL . 'assets/js/plan.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            $active_year = Olama_School_Academic::get_active_year();
            $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
            $semester_id = !empty($semesters) ? $semesters[0]->id : 0;

            wp_localize_script('olama-plan-script', 'olamaPlan', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_curriculum_nonce'),
                'savePlanNonce' => wp_create_nonce('olama_save_plan'),
                'semesterId' => $semester_id,
                'i18n' => array(
                    'selectUnit' => __('Select Unit', 'olama-school'),
                    'noUnits' => __('No units found.', 'olama-school'),
                    'selectLesson' => __('Select Lesson', 'olama-school'),
                    'noLessons' => __('No lessons found.', 'olama-school'),
                    'noQuestions' => __('No questions found for this lesson.', 'olama-school'),
                    'currentStatus' => __('Current Status', 'olama-school'),
                    'published' => __('Published', 'olama-school'),
                    'draft' => __('Draft', 'olama-school'),
                    'revertToDraft' => __('Revert to Draft', 'olama-school'),
                    'saveAsDraft' => __('Save as Draft', 'olama-school'),
                    'updatePlan' => __('Update Plan', 'olama-school'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum') {
            wp_enqueue_style('olama-curriculum-style', OLAMA_SCHOOL_URL . 'assets/css/curriculum.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
            wp_enqueue_script('olama-curriculum-script', OLAMA_SCHOOL_URL . 'assets/js/curriculum.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-curriculum-script', 'olamaCurriculum', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_curriculum_nonce'),
                'i18n' => array(
                    'selectSubject' => __('Select Subject', 'olama-school'),
                    'noUnits' => __('No units found for this subject.', 'olama-school'),
                    'noLessons' => __('No lessons found for this unit.', 'olama-school'),
                    'noQuestions' => __('No questions found for this lesson.', 'olama-school'),
                    'edit' => __('Edit', 'olama-school'),
                    'delete' => __('Delete', 'olama-school'),
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'olama-school'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-curriculum' && isset($_GET['tab']) && $_GET['tab'] === 'timeline') {
            wp_enqueue_style('olama-timeline-style', OLAMA_SCHOOL_URL . 'assets/css/timeline.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
            wp_enqueue_script('olama-timeline-script', OLAMA_SCHOOL_URL . 'assets/js/timeline.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-timeline-script', 'olamaTimeline', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'curriculumNonce' => wp_create_nonce('olama_curriculum_nonce'),
                'i18n' => array(
                    'selectSubject' => __('Select Subject', 'olama-school'),
                    'loading' => __('Loading...', 'olama-school'),
                    'saving' => __('Saving...', 'olama-school'),
                    'error' => __('An error occurred.', 'olama-school'),
                    'dateInvalid' => __('Start date cannot be after end date.', 'olama-school'),
                    'outsideSemester' => __('Dates must be within the semester range.', 'olama-school'),
                    'unitsOverlap' => __('Unit dates cannot overlap.', 'olama-school'),
                    'lessonOutsideUnit' => __('Lesson dates must be within unit dates.', 'olama-school'),
                    'confirmClear' => __('Are you sure you want to clear all dates? This will remove all start and end dates for the current view.', 'olama-school'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-list-script', OLAMA_SCHOOL_URL . 'assets/js/plan-list.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-plan-list-script', 'olamaPlanList', array(
                'i18n' => array(
                    'details' => __('Plan Details', 'olama-school'),
                    'subject' => __('Subject', 'olama-school'),
                    'unit' => __('Unit', 'olama-school'),
                    'lesson' => __('Lesson', 'olama-school'),
                    'customTopic' => __('Topic', 'olama-school'),
                    'homework' => __('Homework', 'olama-school'),
                    'teacherNotes' => __('Teacher Notes', 'olama-school'),
                    'status' => __('Status', 'olama-school'),
                    'draft' => __('Draft', 'olama-school'),
                    'published' => __('Published', 'olama-school'),
                    'noDetails' => __('Click on a plan to see details.', 'olama-school'),
                    'confirmBulkApprove' => __('Are you sure you want to approve (publish) all plans for this week and section?', 'olama-school'),
                    'bulkApproveSuccess' => __('All plans have been approved successfully.', 'olama-school'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-academic' && isset($_GET['tab']) && $_GET['tab'] === 'assign_teachers') {
            wp_enqueue_script('olama-teacher-assignment-script', OLAMA_SCHOOL_URL . 'assets/js/teacher-assignment.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-teacher-assignment-script', 'olamaAssignment', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('olama_admin_nonce'),
                'curriculumNonce' => wp_create_nonce('olama_curriculum_nonce'),
                'i18n' => array(
                    'selectTeacher' => __('Please select a teacher first.', 'olama-school'),
                    'selectGrade' => __('Please select a grade first.', 'olama-school'),
                    'selectSection' => __('Please select a section first.', 'olama-school'),
                    'loading' => __('Loading...', 'olama-school'),
                    'saving' => __('Saving...', 'olama-school'),
                    'error' => __('An error occurred.', 'olama-school'),
                )
            ));
        }
    }


    /**
     * Render unified Academic Management page with tabs
     */
    public function render_academic_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calendar';
        ?>
        <div class="wrap olama-school-wrap">
            <h1><?php _e('Academic Management', 'olama-school'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=olama-school-academic&tab=calendar"
                    class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>"><?php _e('Academic Calendar', 'olama-school'); ?></a>
                <a href="?page=olama-school-academic&tab=grades"
                    class="nav-tab <?php echo $active_tab === 'grades' ? 'nav-tab-active' : ''; ?>"><?php _e('Grades & Sections', 'olama-school'); ?></a>
                <a href="?page=olama-school-academic&tab=subjects"
                    class="nav-tab <?php echo $active_tab === 'subjects' ? 'nav-tab-active' : ''; ?>"><?php _e('Subjects', 'olama-school'); ?></a>
                <a href="?page=olama-school-academic&tab=assign_teachers"
                    class="nav-tab <?php echo $active_tab === 'assign_teachers' ? 'nav-tab-active' : ''; ?>"><?php _e('Assign Teachers to Subjects', 'olama-school'); ?></a>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'grades':
                        $this->render_grades_page_content();
                        break;
                    case 'subjects':
                        $this->render_subjects_page_content();
                        break;
                    case 'assign_teachers':
                        $this->render_teacher_assignments_page_content();
                        break;
                    case 'calendar':
                    default:
                        $this->render_academic_page_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render academic structure page content (Calendar)
     */
    public function render_academic_page_content()
    {
        // Handle Actions
        if (isset($_GET['action']) && isset($_GET['year_id'])) {
            $year_id = intval($_GET['year_id']);
            if ($_GET['action'] === 'activate' && check_admin_referer('olama_activate_year_' . $year_id)) {
                Olama_School_Academic::activate_year($year_id);
                echo '<div class="updated"><p>' . __('Academic Year activated.', 'olama-school') . '</p></div>';
            }
            if ($_GET['action'] === 'delete' && check_admin_referer('olama_delete_year_' . $year_id)) {
                Olama_School_Academic::delete_year($year_id);
                echo '<div class="updated"><p>' . __('Academic Year deleted.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Add Year
        if (isset($_POST['add_year']) && check_admin_referer('olama_add_year')) {
            Olama_School_Academic::add_year(array(
                'year_name' => sanitize_text_field($_POST['year_name']),
                'start_date' => sanitize_text_field($_POST['start_date']),
                'end_date' => sanitize_text_field($_POST['end_date']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ));
            echo '<div class="updated"><p>' . __('Academic Year added successfully.', 'olama-school') . '</p></div>';
        }

        // Handle Add Semester
        if (isset($_POST['add_semester']) && check_admin_referer('olama_add_semester')) {
            Olama_School_Academic::add_semester(array(
                'academic_year_id' => intval($_POST['semester_year_id']),
                'semester_name' => sanitize_text_field($_POST['semester_name']),
                'start_date' => sanitize_text_field($_POST['sem_start_date']),
                'end_date' => sanitize_text_field($_POST['sem_end_date']),
            ));
            echo '<div class="updated"><p>' . __('Semester added successfully.', 'olama-school') . '</p></div>';
        }

        // Handle Delete Semester
        if (isset($_GET['action']) && $_GET['action'] === 'delete_semester' && isset($_GET['semester_id'])) {
            $sem_id = intval($_GET['semester_id']);
            if (check_admin_referer('olama_delete_semester_' . $sem_id)) {
                Olama_School_Academic::delete_semester($sem_id);
                echo '<div class="updated"><p>' . __('Semester deleted.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Add Event
        if (isset($_POST['add_event']) && check_admin_referer('olama_add_event')) {
            $result = Olama_School_Academic::add_event(array(
                'academic_year_id' => intval($_POST['event_year_id']),
                'event_description' => sanitize_textarea_field($_POST['event_description']),
                'start_date' => sanitize_text_field($_POST['event_start_date']),
                'end_date' => sanitize_text_field($_POST['event_end_date']),
            ));

            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Event added successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Update Event
        if (isset($_POST['update_event']) && check_admin_referer('olama_update_event')) {
            $event_id = intval($_POST['edit_event_id']);
            $result = Olama_School_Academic::update_event($event_id, array(
                'event_description' => sanitize_textarea_field($_POST['edit_event_description']),
                'start_date' => sanitize_text_field($_POST['edit_event_start_date']),
                'end_date' => sanitize_text_field($_POST['edit_event_end_date']),
            ));

            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Event updated successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Delete Event
        if (isset($_GET['action']) && $_GET['action'] === 'delete_event' && isset($_GET['event_id'])) {
            $event_id = intval($_GET['event_id']);
            if (check_admin_referer('olama_delete_event_' . $event_id)) {
                Olama_School_Academic::delete_event($event_id);
                echo '<div class="updated"><p>' . __('Event deleted.', 'olama-school') . '</p></div>';
            }
        }

        $years = Olama_School_Academic::get_years();
        $selected_year_id = isset($_GET['manage_year']) ? intval($_GET['manage_year']) : 0;
        ?>
        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
            <div class="olama-main-col">
                <div class="olama-card"
                    style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                    <h2><?php _e('Academic Years', 'olama-school'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php _e('ID', 'olama-school'); ?></th>
                                <th><?php _e('Year Name', 'olama-school'); ?></th>
                                <th><?php _e('Start Date', 'olama-school'); ?></th>
                                <th><?php _e('End Date', 'olama-school'); ?></th>
                                <th><?php _e('Status', 'olama-school'); ?></th>
                                <th style="width: 250px;"><?php _e('Actions', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($years): ?>
                                <?php foreach ($years as $year): ?>
                                    <tr class="<?php echo ($selected_year_id === $year->id) ? 'active-row' : ''; ?>"
                                        style="<?php echo ($selected_year_id === $year->id) ? 'background-color: #f0f6fb;' : ''; ?>">
                                        <td><?php echo $year->id; ?></td>
                                        <td><strong><?php echo esc_html($year->year_name); ?></strong></td>
                                        <td><?php echo esc_html($year->start_date); ?></td>
                                        <td><?php echo esc_html($year->end_date); ?></td>
                                        <td>
                                            <?php if ($year->is_active): ?>
                                                <span class="status-pill active"
                                                    style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php _e('Active', 'olama-school'); ?></span>
                                            <?php else: ?>
                                                <span class="status-pill inactive"
                                                    style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php _e('Inactive', 'olama-school'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-academic&manage_year=' . $year->id); ?>"
                                                class="button button-small"><?php _e('Manage Semesters', 'olama-school'); ?></a>

                                            <?php if (!$year->is_active): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&action=activate&year_id=' . $year->id), 'olama_activate_year_' . $year->id); ?>"
                                                    class="button button-small primary"><?php _e('Activate', 'olama-school'); ?></a>
                                            <?php endif; ?>

                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&action=delete&year_id=' . $year->id), 'olama_delete_year_' . $year->id); ?>"
                                                class="button button-small delete-button" style="color: #dc2626;"
                                                onclick="return confirm('<?php _e('Delete Year and its Semesters?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6"><?php _e('No academic years found.', 'olama-school'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selected_year_id) {
                    $selected_year = null;
                    foreach ($years as $y) {
                        if (intval($y->id) === $selected_year_id) {
                            $selected_year = $y;
                            break;
                        }
                    }

                    if ($selected_year) {
                        $semesters = Olama_School_Academic::get_semesters($selected_year_id);
                        ?>
                        <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h2 style="margin: 0;">
                                    <?php printf(__('Semesters for %s', 'olama-school'), esc_html($selected_year->year_name)); ?>
                                </h2>
                                <button type="button" class="button"
                                    onclick="document.getElementById('add-semester-form').style.display='block'"><?php _e('Add Semester', 'olama-school'); ?></button>
                            </div>

                            <div id="add-semester-form"
                                style="display: none; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">
                                <form method="post" action="">
                                    <?php wp_nonce_field('olama_add_semester'); ?>
                                    <input type="hidden" name="semester_year_id" value="<?php echo $selected_year_id; ?>" />
                                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Name', 'olama-school'); ?></label>
                                            <select name="semester_name" required>
                                                <option value="1st Semester"><?php _e('1st Semester', 'olama-school'); ?></option>
                                                <option value="2nd Semester"><?php _e('2nd Semester', 'olama-school'); ?></option>
                                                <option value="Summer Semester"><?php _e('Summer Semester', 'olama-school'); ?>
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Start Date', 'olama-school'); ?></label>
                                            <input type="date" name="sem_start_date" required />
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('End Date', 'olama-school'); ?></label>
                                            <input type="date" name="sem_end_date" required />
                                        </div>
                                        <div>
                                            <?php submit_button(__('Add', 'olama-school'), 'primary', 'add_semester', false); ?>
                                            <button type="button" class="button"
                                                onclick="document.getElementById('add-semester-form').style.display='none'"><?php _e('Cancel', 'olama-school'); ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Semester Name', 'olama-school'); ?></th>
                                        <th><?php _e('Start Date', 'olama-school'); ?></th>
                                        <th><?php _e('End Date', 'olama-school'); ?></th>
                                        <th><?php _e('Actions', 'olama-school'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($semesters): ?>
                                        <?php foreach ($semesters as $sem): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($sem->semester_name); ?></strong></td>
                                                <td><?php echo esc_html($sem->start_date); ?></td>
                                                <td><?php echo esc_html($sem->end_date); ?></td>
                                                <td>
                                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id . '&action=delete_semester&semester_id=' . $sem->id), 'olama_delete_semester_' . $sem->id); ?>"
                                                        class="button button-small" style="color: #dc2626;"
                                                        onclick="return confirm('<?php _e('Delete Semester?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4"><?php _e('No semesters defined for this year.', 'olama-school'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Events Section -->
                        <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h2 style="margin: 0;">
                                    <?php printf(__('Events for %s', 'olama-school'), esc_html($selected_year->year_name)); ?>
                                </h2>
                                <button type="button" class="button"
                                    onclick="document.getElementById('add-event-form').style.display='block'"><?php _e('Add Event', 'olama-school'); ?></button>
                            </div>

                            <div id="add-event-form"
                                style="display: none; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">
                                <form method="post"
                                    action="<?php echo esc_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id)); ?>">
                                    <?php wp_nonce_field('olama_add_event'); ?>
                                    <input type="hidden" name="event_year_id" value="<?php echo $selected_year_id; ?>" />
                                    <div
                                        style="display: grid; grid-template-columns: 1fr 150px 150px 100px; gap: 10px; align-items: flex-end;">
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Description', 'olama-school'); ?></label>
                                            <input type="text" name="event_description" required style="width: 100%;" />
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Start Date', 'olama-school'); ?></label>
                                            <input type="date" name="event_start_date" required style="width: 100%;"
                                                min="<?php echo esc_attr($selected_year->start_date); ?>"
                                                max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('End Date', 'olama-school'); ?></label>
                                            <input type="date" name="event_end_date" required style="width: 100%;"
                                                min="<?php echo esc_attr($selected_year->start_date); ?>"
                                                max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                        </div>
                                        <div>
                                            <?php submit_button(__('Add', 'olama-school'), 'primary', 'add_event', false); ?>
                                            <button type="button" class="button button-small" style="margin-top: 5px; width: 100%;"
                                                onclick="document.getElementById('add-event-form').style.display='none'"><?php _e('Cancel', 'olama-school'); ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div id="edit-event-form"
                                style="display: none; background: #fffbeb; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px dashed #d97706;">
                                <form method="post"
                                    action="<?php echo esc_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id)); ?>">
                                    <?php wp_nonce_field('olama_update_event'); ?>
                                    <input type="hidden" name="edit_event_id" id="edit_event_id" />
                                    <div
                                        style="display: grid; grid-template-columns: 1fr 150px 150px 100px; gap: 10px; align-items: flex-end;">
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Description', 'olama-school'); ?></label>
                                            <input type="text" name="edit_event_description" id="edit_event_description" required
                                                style="width: 100%;" />
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Start Date', 'olama-school'); ?></label>
                                            <input type="date" name="edit_event_start_date" id="edit_event_start_date" required
                                                style="width: 100%;" min="<?php echo esc_attr($selected_year->start_date); ?>"
                                                max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                        </div>
                                        <div>
                                            <label
                                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('End Date', 'olama-school'); ?></label>
                                            <input type="date" name="edit_event_end_date" id="edit_event_end_date" required
                                                style="width: 100%;" min="<?php echo esc_attr($selected_year->start_date); ?>"
                                                max="<?php echo esc_attr($selected_year->end_date); ?>" />
                                        </div>
                                        <div>
                                            <?php submit_button(__('Update', 'olama-school'), 'primary', 'update_event', false); ?>
                                            <button type="button" class="button button-small" style="margin-top: 5px; width: 100%;"
                                                onclick="document.getElementById('edit-event-form').style.display='none'"><?php _e('Cancel', 'olama-school'); ?></button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Event Description', 'olama-school'); ?></th>
                                        <th><?php _e('Start Date', 'olama-school'); ?></th>
                                        <th><?php _e('End Date', 'olama-school'); ?></th>
                                        <th><?php _e('Actions', 'olama-school'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $events = Olama_School_Academic::get_events($selected_year_id);
                                    if ($events): ?>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($event->event_description); ?></strong></td>
                                                <td><?php echo esc_html($event->start_date); ?></td>
                                                <td><?php echo esc_html($event->end_date); ?></td>
                                                <td>
                                                    <button type="button" class="button button-small olama-edit-event-btn"
                                                        data-id="<?php echo esc_attr($event->id); ?>"
                                                        data-desc="<?php echo esc_attr($event->event_description); ?>"
                                                        data-start="<?php echo esc_attr($event->start_date); ?>"
                                                        data-end="<?php echo esc_attr($event->end_date); ?>"
                                                        onclick="olamaEditEvent(this)"><?php _e('Edit', 'olama-school'); ?></button>
                                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&manage_year=' . $selected_year_id . '&action=delete_event&event_id=' . $event->id), 'olama_delete_event_' . $event->id); ?>"
                                                        class="button button-small" style="color: #dc2626;"
                                                        onclick="return confirm('<?php _e('Delete Event?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4"><?php _e('No events defined for this year.', 'olama-school'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php }
                } ?>
            </div>

            <div class="olama-side-col">
                <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                    <h2 style="margin-top: 0;"><?php _e('Add Academic Year', 'olama-school'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('olama_add_year'); ?>
                        <p>
                            <label
                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Year Name', 'olama-school'); ?></label>
                            <input type="text" name="year_name" required class="widefat" placeholder="e.g. 2025-2026" />
                        </p>
                        <p>
                            <label
                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Start Date', 'olama-school'); ?></label>
                            <input type="date" name="start_date" required class="widefat" />
                        </p>
                        <p>
                            <label
                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('End Date', 'olama-school'); ?></label>
                            <input type="date" name="end_date" required class="widefat" />
                        </p>
                        <p>
                            <label><input type="checkbox" name="is_active" value="1" />
                                <?php _e('Set as Active', 'olama-school'); ?></label>
                        </p>
                        <?php submit_button(__('Add Year', 'olama-school'), 'primary', 'add_year', true, array('style' => 'width: 100%;')); ?>
                    </form>
                </div>
            </div>
        </div>
        </div>
        <script>
            function olamaEditEven                          t(btn)           {
                document.getElementById('add-event-form').style.display = 'none';
                const editForm = document.getElementById('edit-event-form');
                editForm.style.display = 'block';

                document.getElementById('edit_event_id').value = btn.getAttribute('data-id');
                document.getElementById('edit_event_description').value = btn.getAttribute('data-desc');
                document.getElementById('edit_event_start_date').value = btn.getAttribute('data-start');
                document.getElementById('edit_event_end_date').value = btn.getAttribute('data-end');

                editForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        </script>
        <?php
    }

    /**
     * Render grades and sections page content
     */
    public function render_grades_page_content()
    {
        // Handle Grade submission
        if (isset($_POST['add_grade']) && check_admin_referer('olama_add_grade')) {
            $result = Olama_School_Grade::add_grade(array(
                'grade_name' => sanitize_text_field($_POST['grade_name']),
                'grade_level' => intval($_POST['grade_level']),
                'periods_count' => intval($_POST['periods_count']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Grade added successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Grade Update
        if (isset($_POST['edit_grade']) && check_admin_referer('olama_edit_grade')) {
            $grade_id = intval($_POST['grade_id']);
            $result = Olama_School_Grade::update_grade($grade_id, array(
                'grade_name' => sanitize_text_field($_POST['grade_name']),
                'grade_level' => intval($_POST['grade_level']),
                'periods_count' => intval($_POST['periods_count']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Grade updated successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Grade Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_grade' && isset($_GET['grade_id'])) {
            $grade_id = intval($_GET['grade_id']);
            if (check_admin_referer('olama_delete_grade_' . $grade_id)) {
                $result = Olama_School_Grade::delete_grade($grade_id);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>' . __('Grade deleted.', 'olama-school') . '</p></div>';
                }
            }
        }

        // Handle Section submission
        if (isset($_POST['add_section']) && check_admin_referer('olama_add_section')) {
            $result = Olama_School_Section::add_section(array(
                'grade_id' => intval($_POST['grade_id']),
                'section_name' => sanitize_text_field($_POST['section_name']),
                'room_number' => sanitize_text_field($_POST['room_number']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Section added successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Section Update
        if (isset($_POST['edit_section']) && check_admin_referer('olama_edit_section')) {
            $section_id = intval($_POST['section_id']);
            $result = Olama_School_Section::update_section($section_id, array(
                'grade_id' => intval($_POST['grade_id']),
                'section_name' => sanitize_text_field($_POST['section_name']),
                'room_number' => sanitize_text_field($_POST['room_number']),
            ));
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Section updated successfully.', 'olama-school') . '</p></div>';
            }
        }

        // Handle Section Delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_section' && isset($_GET['section_id'])) {
            $section_id = intval($_GET['section_id']);
            if (check_admin_referer('olama_delete_section_' . $section_id)) {
                $result = Olama_School_Section::delete_section($section_id);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>' . __('Section deleted.', 'olama-school') . '</p></div>';
                }
            }
        }

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['manage_grade']) ? intval($_GET['manage_grade']) : 0;
        $selected_grade = null;
        if ($selected_grade_id) {
            $selected_grade = Olama_School_Grade::get_grade($selected_grade_id);
        }
        ?>
        <div class="olama-flex" style="display: flex; gap: 20px;">
            <div class="olama-main-col" style="flex: 2;">
                <!-- Existing Grades Card -->
                <div class="olama-card"
                    style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                    <h2><?php _e('Existing Grades', 'olama-school'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;"><?php _e('ID', 'olama-school'); ?></th>
                                <th><?php _e('Grade Name', 'olama-school'); ?></th>
                                <th><?php _e('Level', 'olama-school'); ?></th>
                                <th><?php _e('Periods', 'olama-school'); ?></th>
                                <th style="width: 250px;"><?php _e('Actions', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($grades): ?>
                                <?php foreach ($grades as $grade): ?>
                                    <tr class="<?php echo ($selected_grade_id === (int) $grade->id) ? 'olama-selected-row' : ''; ?>"
                                        style="<?php echo ($selected_grade_id === (int) $grade->id) ? 'background-color: #f0f7ff;' : ''; ?>">
                                        <td><?php echo $grade->id; ?></td>
                                        <td><strong><?php echo esc_html($grade->grade_name); ?></strong></td>
                                        <td><?php echo esc_html($grade->grade_level); ?></td>
                                        <td><?php echo esc_html($grade->periods_count ?? 8); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $grade->id); ?>"
                                                class="button button-small button-primary"><?php _e('Manage Sections', 'olama-school'); ?></a>
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&action=edit_grade&grade_id=' . $grade->id); ?>"
                                                class="button button-small"><?php _e('Edit', 'olama-school'); ?></a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=grades&action=delete_grade&grade_id=' . $grade->id), 'olama_delete_grade_' . $grade->id); ?>"
                                                class="button button-small" style="color: #dc2626;"
                                                onclick="return confirm('<?php _e('Delete Grade?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5"><?php _e('No grades found.', 'olama-school'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selected_grade): ?>
                    <!-- Sections Management Card -->
                    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2><?php echo sprintf(__('Sections for %s', 'olama-school'), esc_html($selected_grade->grade_name)); ?>
                            </h2>
                            <button type="button" class="button button-primary" onclick="olamaToggleForm('olama-add-section-form')">
                                <?php _e('Add Section', 'olama-school'); ?>
                            </button>
                        </div>

                        <!-- Add Section Form (Hidden by default) -->
                        <div id="olama-add-section-form"
                            style="display: none; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
                            <h3><?php _e('Add New Section', 'olama-school'); ?></h3>
                            <form method="post"
                                action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id); ?>">
                                <?php wp_nonce_field('olama_add_section'); ?>
                                <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Section Name', 'olama-school'); ?></th>
                                        <td><input type="text" name="section_name" required placeholder="e.g. Section A"
                                                class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Room Number', 'olama-school'); ?></th>
                                        <td><input type="text" name="room_number" placeholder="e.g. 101" class="regular-text" />
                                        </td>
                                    </tr>
                                </table>
                                <div style="margin-top: 15px;">
                                    <?php submit_button(__('Save Section', 'olama-school'), 'primary', 'add_section', false); ?>
                                    <button type="button" class="button"
                                        onclick="olamaToggleForm('olama-add-section-form')"><?php _e('Cancel', 'olama-school'); ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Edit Section Form (Hidden by default) -->
                        <div id="olama-edit-section-form"
                            style="display: none; background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;">
                            <h3><?php _e('Edit Section', 'olama-school'); ?></h3>
                            <form method="post"
                                action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id); ?>">
                                <?php wp_nonce_field('olama_edit_section'); ?>
                                <input type="hidden" name="section_id" id="edit_section_id" />
                                <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Section Name', 'olama-school'); ?></th>
                                        <td><input type="text" name="section_name" id="edit_section_name" required
                                                class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Room Number', 'olama-school'); ?></th>
                                        <td><input type="text" name="room_number" id="edit_room_number" class="regular-text" />
                                        </td>
                                    </tr>
                                </table>
                                <div style="margin-top: 15px;">
                                    <?php submit_button(__('Update Section', 'olama-school'), 'primary', 'edit_section', false); ?>
                                    <button type="button" class="button"
                                        onclick="olamaToggleForm('olama-edit-section-form')"><?php _e('Cancel', 'olama-school'); ?></button>
                                </div>
                            </form>
                        </div>

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Section Name', 'olama-school'); ?></th>
                                    <th><?php _e('Room', 'olama-school'); ?></th>
                                    <th style="width: 150px;"><?php _e('Actions', 'olama-school'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $grade_sections = Olama_School_Section::get_by_grade($selected_grade_id);
                                if ($grade_sections): ?>
                                    <?php foreach ($grade_sections as $sec): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($sec->section_name); ?></strong></td>
                                            <td><?php echo esc_html($sec->room_number); ?></td>
                                            <td>
                                                <button type="button" class="button button-small" onclick="olamaEditSection(this)"
                                                    data-id="<?php echo $sec->id; ?>"
                                                    data-name="<?php echo esc_attr($sec->section_name); ?>"
                                                    data-room="<?php echo esc_attr($sec->room_number); ?>">
                                                    <?php _e('Edit', 'olama-school'); ?>
                                                </button>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=grades&manage_grade=' . $selected_grade_id . '&action=delete_section&section_id=' . $sec->id), 'olama_delete_section_' . $sec->id); ?>"
                                                    class="button button-small" style="color: #dc2626;"
                                                    onclick="return confirm('<?php _e('Delete Section?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3"><?php _e('No sections defined for this grade.', 'olama-school'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="olama-side-col" style="flex: 1;">
                <!-- Add Grade Form -->
                <div class="olama-card"
                    style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                    <?php if (isset($_GET['action']) && $_GET['action'] === 'edit_grade' && isset($_GET['grade_id'])):
                        $edit_grade_id = intval($_GET['grade_id']);
                        $edit_grade = Olama_School_Grade::get_grade($edit_grade_id);
                        if ($edit_grade): ?>
                            <h2><?php _e('Edit Grade', 'olama-school'); ?></h2>
                            <form method="post" action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades'); ?>">
                                <?php wp_nonce_field('olama_edit_grade'); ?>
                                <input type="hidden" name="grade_id" value="<?php echo $edit_grade_id; ?>" />
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Grade Name', 'olama-school'); ?></th>
                                        <td><input type="text" name="grade_name" required class="regular-text"
                                                value="<?php echo esc_attr($edit_grade->grade_name); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Grade Level', 'olama-school'); ?></th>
                                        <td><input type="number" name="grade_level" required
                                                value="<?php echo esc_attr($edit_grade->grade_level); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Periods per Day', 'olama-school'); ?></th>
                                        <td><input type="number" name="periods_count" required min="1" max="15"
                                                value="<?php echo esc_attr($edit_grade->periods_count ?? 8); ?>" /></td>
                                    </tr>
                                </table>
                                <?php submit_button(__('Update Grade', 'olama-school'), 'primary', 'edit_grade', false); ?>
                                <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=grades'); ?>"
                                    class="button"><?php _e('Cancel', 'olama-school'); ?></a>
                            </form>
                        <?php else: ?>
                            <div class="error">
                                <p><?php _e('Grade not found.', 'olama-school'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <h2><?php _e('Add New Grade', 'olama-school'); ?></h2>
                        <form method="post" action="">
                            <?php wp_nonce_field('olama_add_grade'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Grade Name', 'olama-school'); ?></th>
                                    <td><input type="text" name="grade_name" required class="regular-text"
                                            placeholder="e.g. Grade 5" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Grade Level', 'olama-school'); ?></th>
                                    <td><input type="number" name="grade_level" required placeholder="5" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Periods/Day', 'olama-school'); ?></th>
                                    <td><input type="number" name="periods_count" required min="1" max="15" value="8" /></td>
                                </tr>
                            </table>
                            <?php submit_button(__('Add Grade', 'olama-school'), 'primary', 'add_grade'); ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            function olamaToggleForm(formId) {
                var forms = ['olama-add-section-form', 'olama-edit-section-form'];
                forms.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (id === formId) {
                        el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }

            function olamaEditSection(btn) {
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name');
                var room = btn.getAttribute('data-room');

                document.getElementById('edit_section_id').value = id;
                document.getElementById('edit_section_name').value = name;
                document.getElementById('edit_room_number').value = room;

                olamaToggleForm('olama-edit-section-form');
                document.getElementById('olama-edit-section-form').scrollIntoView({ behavior: 'smooth' });
            }
        </script>
        </div>
        <?php
    }

    /**
     * Render subjects page content
     */
    public function render_subjects_page_content()
    {
        // Handle Subject submission (Add)
        if (isset($_POST['add_subject']) && check_admin_referer('olama_add_subject')) {
            Olama_School_Subject::add_subject(array(
                'subject_name' => sanitize_text_field($_POST['subject_name']),
                'subject_code' => sanitize_text_field($_POST['subject_code']),
                'grade_id' => intval($_POST['grade_id']),
                'color_code' => sanitize_hex_color($_POST['color_code']),
            ));
            echo '<div class="updated"><p>' . __('Subject added successfully.', 'olama-school') . '</p></div>';
        }

        // Handle Subject update (Edit)
        if (isset($_POST['edit_subject']) && check_admin_referer('olama_edit_subject')) {
            $subject_id = intval($_POST['subject_id']);
            Olama_School_Subject::update_subject($subject_id, array(
                'subject_name' => sanitize_text_field($_POST['subject_name']),
                'subject_code' => sanitize_text_field($_POST['subject_code']),
                'grade_id' => intval($_POST['grade_id']),
                'color_code' => sanitize_hex_color($_POST['color_code']),
            ));
            echo '<div class="updated"><p>' . __('Subject updated successfully.', 'olama-school') . '</p></div>';
        }

        // Handle Subject delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_subject' && isset($_GET['subject_id'])) {
            $subject_id = intval($_GET['subject_id']);
            if (check_admin_referer('olama_delete_subject_' . $subject_id)) {
                Olama_School_Subject::delete_subject($subject_id);
                echo '<div class="updated"><p>' . __('Subject deleted.', 'olama-school') . '</p></div>';
            }
        }

        $grades = Olama_School_Grade::get_grades();
        $subjects = Olama_School_Subject::get_subjects();

        // Group subjects by grade
        $grouped_subjects = array();
        foreach ($subjects as $subject) {
            $grouped_subjects[$subject->grade_name][] = $subject;
        }

        // Handle Edit Mode
        $edit_subject = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit_subject' && isset($_GET['subject_id'])) {
            $edit_subject = Olama_School_Subject::get_subject(intval($_GET['subject_id']));
        }
        ?>
        <div class="olama-flex" style="display: flex; gap: 20px;">
            <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <?php if ($edit_subject): ?>
                    <h2><?php _e('Edit Subject', 'olama-school'); ?></h2>
                    <form method="post" action="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects'); ?>">
                        <?php wp_nonce_field('olama_edit_subject'); ?>
                        <input type="hidden" name="subject_id" value="<?php echo $edit_subject->id; ?>" />
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Subject Name', 'olama-school'); ?></th>
                                <td><input type="text" name="subject_name" required class="regular-text"
                                        value="<?php echo esc_attr($edit_subject->subject_name); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Subject Code', 'olama-school'); ?></th>
                                <td><input type="text" name="subject_code" placeholder="e.g. ENG01"
                                        value="<?php echo esc_attr($edit_subject->subject_code); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                                <td>
                                    <select name="grade_id" required>
                                        <?php foreach ($grades as $grade): ?>
                                            <option value="<?php echo $grade->id; ?>" <?php selected($edit_subject->grade_id, $grade->id); ?>>
                                                <?php echo esc_html($grade->grade_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Color Code', 'olama-school'); ?></th>
                                <td><input type="color" name="color_code"
                                        value="<?php echo esc_attr($edit_subject->color_code); ?>" /></td>
                            </tr>
                        </table>
                        <?php submit_button(__('Update Subject', 'olama-school'), 'primary', 'edit_subject', false); ?>
                        <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects'); ?>"
                            class="button"><?php _e('Cancel', 'olama-school'); ?></a>
                    </form>
                <?php else: ?>
                    <h2><?php _e('Add Subject', 'olama-school'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('olama_add_subject'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Subject Name', 'olama-school'); ?></th>
                                <td><input type="text" name="subject_name" required class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Subject Code', 'olama-school'); ?></th>
                                <td><input type="text" name="subject_code" placeholder="e.g. ENG01" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                                <td>
                                    <select name="grade_id" required>
                                        <?php foreach ($grades as $grade): ?>
                                            <option value="<?php echo $grade->id; ?>"><?php echo esc_html($grade->grade_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Color Code', 'olama-school'); ?></th>
                                <td><input type="color" name="color_code" value="#3498db" /></td>
                            </tr>
                        </table>
                        <?php submit_button(__('Add Subject', 'olama-school'), 'primary', 'add_subject'); ?>
                    </form>
                <?php endif; ?>
            </div>

            <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <h2><?php _e('Existing Subjects', 'olama-school'); ?></h2>
                <?php if ($grouped_subjects): ?>
                    <?php foreach ($grouped_subjects as $grade_name => $grade_subjects): ?>
                        <h3 style="background: #f8f9fa; padding: 8px 12px; border-left: 4px solid #2271b1; margin-top: 20px;">
                            <?php echo esc_html($grade_name); ?>
                        </h3>
                        <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Subject Name', 'olama-school'); ?></th>
                                    <th style="width: 80px;"><?php _e('Code', 'olama-school'); ?></th>
                                    <th style="width: 60px; text-align: center;"><?php _e('Color', 'olama-school'); ?></th>
                                    <th style="width: 140px;"><?php _e('Actions', 'olama-school'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grade_subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($subject->subject_name); ?></strong></td>
                                        <td><code><?php echo esc_html($subject->subject_code); ?></code></td>
                                        <td style="text-align: center;">
                                            <span
                                                style="display: inline-block; width: 24px; height: 24px; background: <?php echo esc_attr($subject->color_code); ?>; border: 1px solid #ccc; border-radius: 4px;"></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-academic&tab=subjects&action=edit_subject&subject_id=' . $subject->id); ?>"
                                                class="button button-small"><?php _e('Edit', 'olama-school'); ?></a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=olama-school-academic&tab=subjects&action=delete_subject&subject_id=' . $subject->id), 'olama_delete_subject_' . $subject->id); ?>"
                                                class="button button-small" style="color: #dc2626;"
                                                onclick="return confirm('<?php _e('Delete Subject?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php _e('No subjects found. Add your first subject using the form on the left.', 'olama-school'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <?php
    }

    /**
     * Render unified Users page
     */
    public function render_users_page()
    {
        // Handle Student submission
        if (isset($_POST['add_student']) && check_admin_referer('olama_add_student')) {
            Olama_School_Student::add_student(array(
                'student_name' => sanitize_text_field($_POST['student_name']),
                'student_id_number' => sanitize_text_field($_POST['student_id_number']),
                'grade_id' => intval($_POST['grade_id']),
                'section_id' => intval($_POST['section_id']),
            ));
            echo '<div class="updated"><p>' . __('Student added successfully.', 'olama-school') . '</p></div>';
        }

        // Handle Teacher update
        if (isset($_POST['update_teacher']) && check_admin_referer('olama_update_teacher')) {
            Olama_School_Teacher::update_teacher(intval($_POST['teacher_id']), array(
                'employee_id' => sanitize_text_field($_POST['employee_id']),
                'phone_number' => sanitize_text_field($_POST['phone_number']),
            ));
            echo '<div class="updated"><p>' . __('Teacher information updated.', 'olama-school') . '</p></div>';
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'students';
        $grades = Olama_School_Grade::get_grades();
        $sections = Olama_School_Section::get_sections();
        $students = Olama_School_Student::get_students();
        $teachers = Olama_School_Teacher::get_teachers();

        // Get Admins
        $admin_users = get_users(array('role' => 'administrator'));
        ?>
        <div class="wrap olama-school-wrap">
            <h1><?php _e('User Management', 'olama-school'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=olama-school-users&tab=students"
                    class="nav-tab <?php echo $active_tab === 'students' ? 'nav-tab-active' : ''; ?>"><?php _e('Students', 'olama-school'); ?></a>
                <a href="?page=olama-school-users&tab=teachers"
                    class="nav-tab <?php echo $active_tab === 'teachers' ? 'nav-tab-active' : ''; ?>"><?php _e('Teachers', 'olama-school'); ?></a>
                <a href="?page=olama-school-users&tab=admins"
                    class="nav-tab <?php echo $active_tab === 'admins' ? 'nav-tab-active' : ''; ?>"><?php _e('Administrators', 'olama-school'); ?></a>
                <a href="?page=olama-school-users&tab=permissions"
                    class="nav-tab <?php echo $active_tab === 'permissions' ? 'nav-tab-active' : ''; ?>"><?php _e('Permissions', 'olama-school'); ?></a>
                <a href="?page=olama-school-users&tab=logs"
                    class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e('Audit Logs', 'olama-school'); ?></a>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'students'): ?>
                    <!-- Students Tab -->
                    <div class="olama-flex" style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="olama-card" style="flex: 2; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                            <h2><?php _e('Add Student', 'olama-school'); ?></h2>
                            <form method="post" action="">
                                <?php wp_nonce_field('olama_add_student'); ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Student Name', 'olama-school'); ?></th>
                                        <td><input type="text" name="student_name" required class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Student ID Number', 'olama-school'); ?></th>
                                        <td><input type="text" name="student_id_number" required class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Select Grade', 'olama-school'); ?></th>
                                        <td>
                                            <select name="grade_id" required id="student-grade-select">
                                                <option value=""><?php _e('Select Grade', 'olama-school'); ?></option>
                                                <?php foreach ($grades as $grade): ?>
                                                    <option value="<?php echo $grade->id; ?>">
                                                        <?php echo esc_html($grade->grade_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Select Section', 'olama-school'); ?></th>
                                        <td>
                                            <select name="section_id" required id="student-section-select">
                                                <option value=""><?php _e('Select Section', 'olama-school'); ?></option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?php echo $section->id; ?>"
                                                        data-grade="<?php echo $section->grade_id; ?>">
                                                        <?php echo esc_html($section->section_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button(__('Add Student', 'olama-school'), 'primary', 'add_student'); ?>
                            </form>
                        </div>

                        <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                            <h2><?php _e('Import Students', 'olama-school'); ?></h2>
                            <p><?php _e('Upload a CSV file containing student names, IDs, grades, and sections.', 'olama-school'); ?>
                            </p>
                            <form method="post" action="" enctype="multipart/form-data">
                                <?php wp_nonce_field('olama_import_students'); ?>
                                <input type="hidden" name="olama_import_type" value="students" />
                                <p><input type="file" name="olama_import_file" accept=".csv" required /></p>
                                <p class="description">
                                    <?php _e('Required columns: Name, ID Number, Grade, Section', 'olama-school'); ?>
                                </p>
                                <?php submit_button(__('Upload CSV', 'olama-school'), 'secondary', 'import_students'); ?>
                            </form>
                        </div>
                    </div>

                    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                        <h2><?php _e('Existing Students', 'olama-school'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'olama-school'); ?></th>
                                    <th><?php _e('ID Number', 'olama-school'); ?></th>
                                    <th><?php _e('Grade', 'olama-school'); ?></th>
                                    <th><?php _e('Section', 'olama-school'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo esc_html($student->student_name); ?></td>
                                            <td><?php echo esc_html($student->student_id_number); ?></td>
                                            <td><?php echo esc_html($student->grade_name); ?></td>
                                            <td><?php echo esc_html($student->section_name); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><?php _e('No students found.', 'olama-school'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($active_tab === 'teachers'): ?>
                    <!-- Teachers Tab -->
                    <div class="olama-flex" style="display: flex; gap: 20px;">
                        <div class="olama-card" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                            <h2><?php _e('Teacher Profile Management', 'olama-school'); ?></h2>
                            <p><?php _e('Enhance WordPress users with teacher-specific data.', 'olama-school'); ?></p>
                            <form method="post" action="">
                                <?php wp_nonce_field('olama_update_teacher'); ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Select User', 'olama-school'); ?></th>
                                        <td>
                                            <select name="teacher_id" required>
                                                <option value=""><?php _e('-- Select Teacher --', 'olama-school'); ?></option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo $teacher->ID; ?>">
                                                        <?php echo esc_html($teacher->display_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Employee ID', 'olama-school'); ?></th>
                                        <td><input type="text" name="employee_id" required /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php _e('Phone Number', 'olama-school'); ?></th>
                                        <td><input type="text" name="phone_number" /></td>
                                    </tr>
                                </table>
                                <?php submit_button(__('Update Teacher', 'olama-school'), 'primary', 'update_teacher'); ?>
                            </form>
                        </div>

                        <div style="flex: 1;">
                            <h2><?php _e('Teacher List', 'olama-school'); ?></h2>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Name', 'olama-school'); ?></th>
                                        <th><?php _e('Employee ID', 'olama-school'); ?></th>
                                        <th><?php _e('Phone', 'olama-school'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?php echo esc_html($teacher->display_name); ?></td>
                                            <td><?php echo esc_html($teacher->employee_id ?? '-'); ?></td>
                                            <td><?php echo esc_html($teacher->phone_number ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'admins'): ?>
                    <!-- Admins Tab -->
                    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                        <h2><?php _e('System Administrators', 'olama-school'); ?></h2>
                        <p><?php _e('These WordPress users have administrative access to the system.', 'olama-school'); ?></p>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Username', 'olama-school'); ?></th>
                                    <th><?php _e('Display Name', 'olama-school'); ?></th>
                                    <th><?php _e('Email', 'olama-school'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admin_users as $admin): ?>
                                    <tr>
                                        <td><?php echo esc_html($admin->user_login); ?></td>
                                        <td><?php echo esc_html($admin->display_name); ?></td>
                                        <td><?php echo esc_html($admin->user_email); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($active_tab === 'permissions'): ?>
                    <!-- Permissions Tab -->
                    <?php $this->render_permissions_page_content(); ?>
                <?php elseif ($active_tab === 'logs'): ?>
                    <!-- Logs Tab -->
                    <?php $this->render_notifications_page_content(); ?>
                <?php endif; ?>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const gradeSelect = document.getElementById('student-grade-select');
                const sectionSelect = document.getElementById('student-section-select');
                if (gradeSelect && sectionSelect) {
                    const sectionOptions = Array.from(sectionSelect.options);
                    gradeSelect.addEventListener('change', function () {
                        const selectedGradeId = this.value;
                        sectionSelect.innerHTML = '<option value=""><?php _e('Select Section', 'olama-school'); ?></option>';
                        sectionOptions.forEach(option => {
                            if (option.getAttribute('data-grade') === selectedGradeId || option.value === "") {
                                sectionSelect.appendChild(option.cloneNode(true));
                            }
                        });
                    });
                }
            });
        </script>
        <?php
    }

    /**
     * Render unified Curriculum Management page with tabs
     */
    public function render_curriculum_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'curriculum';
        ?>
        <div class="wrap olama-school-wrap">
            <h1><?php _e('Curriculum Management', 'olama-school'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=olama-school-curriculum&tab=curriculum"
                    class="nav-tab <?php echo $active_tab === 'curriculum' ? 'nav-tab-active' : ''; ?>"><?php _e('Curriculum', 'olama-school'); ?></a>
                <a href="?page=olama-school-curriculum&tab=timeline"
                    class="nav-tab <?php echo $active_tab === 'timeline' ? 'nav-tab-active' : ''; ?>"><?php _e('Timeline', 'olama-school'); ?></a>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'timeline':
                        $this->render_timeline_page_content();
                        break;
                    case 'curriculum':
                    default:
                        $this->render_curriculum_page_content();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap olama-school-wrap">
            <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                <?php _e('Plugin Settings', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=olama-school-settings&tab=general"
                    class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General Settings', 'olama-school'); ?>
                </a>
                <a href="?page=olama-school-settings&tab=shortcode"
                    class="nav-tab <?php echo $active_tab === 'shortcode' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Shortcode Generator', 'olama-school'); ?>
                </a>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'general'): ?>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('olama_school_settings_group');
                        do_settings_sections('olama_school_settings_group');
                        $settings = get_option('olama_school_settings', array());
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><?php _e('School Name (Arabic)', 'olama-school'); ?></th>
                                <td><input type="text" name="olama_school_settings[school_name_ar]"
                                        value="<?php echo esc_attr($settings['school_name_ar'] ?? ''); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('School Name (English)', 'olama-school'); ?></th>
                                <td><input type="text" name="olama_school_settings[school_name_en]"
                                        value="<?php echo esc_attr($settings['school_name_en'] ?? ''); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('School Start Day', 'olama-school'); ?></th>
                                <td>
                                    <select name="olama_school_settings[start_day]">
                                        <?php
                                        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
                                        foreach ($days as $day): ?>
                                            <option value="<?php echo strtolower($day); ?>" <?php selected($settings['start_day'] ?? 'monday', strtolower($day)); ?>>
                                                <?php echo $day; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('School Last Day', 'olama-school'); ?></th>
                                <td>
                                    <select name="olama_school_settings[last_day]">
                                        <?php foreach ($days as $day): ?>
                                            <option value="<?php echo strtolower($day); ?>" <?php selected($settings['last_day'] ?? 'friday', strtolower($day)); ?>>
                                                <?php echo $day; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><?php _e('Default Language', 'olama-school'); ?></th>
                                <td>
                                    <select name="olama_school_settings[default_lang]">
                                        <option value="ar" <?php selected($settings['default_lang'] ?? '', 'ar'); ?>>
                                            <?php _e('Arabic', 'olama-school'); ?>
                                        </option>
                                        <option value="en" <?php selected($settings['default_lang'] ?? '', 'en'); ?>>
                                            <?php _e('English', 'olama-school'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                <?php else: ?>
                    <?php $this->render_shortcode_generator_content(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Shortcode Generator Tab Content
     */
    public function render_shortcode_generator_content()
    {
        $grades = Olama_School_Grade::get_grades();
        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
        $weeks = Olama_School_Academic::get_academic_weeks();
        ?>
        <div class="olama-card"
            style="max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <h2 style="margin-top: 0; color: #1e293b; font-size: 1.5rem; font-weight: 700;">
                <span class="dashicons dashicons-shortcode"
                    style="font-size: 24px; width: 24px; height: 24px; margin-right: 10px; color: #2563eb;"></span>
                <?php _e('Shortcode Generator', 'olama-school'); ?>
            </h2>
            <p style="color: #64748b; margin-bottom: 30px; font-size: 1rem; line-height: 1.5;">
                <?php _e('Configure the options below to generate a custom shortcode for displaying weekly plans. You can paste this shortcode into any post, page, or widget.', 'olama-school'); ?>
            </p>

            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 40px;">
                <div>
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                        <?php _e('Active Semester', 'olama-school'); ?>
                    </label>
                    <select id="gen-semester"
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo $sem->id; ?>"><?php echo esc_html($sem->semester_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                        <?php _e('Target Grade', 'olama-school'); ?>
                    </label>
                    <select id="gen-grade" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                        <option value=""><?php _e('-- Select Grade --', 'olama-school'); ?></option>
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?php echo $grade->id; ?>"><?php echo esc_html($grade->grade_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                        <?php _e('Target Section', 'olama-school'); ?>
                    </label>
                    <select id="gen-section" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;"
                        disabled>
                        <option value=""><?php _e('-- Select Grade First --', 'olama-school'); ?></option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px; font-size: 0.9rem;">
                        <?php _e('Specific Week', 'olama-school'); ?>
                    </label>
                    <select id="gen-week" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                        <option value=""><?php _e('-- Current Week --', 'olama-school'); ?></option>
                        <?php foreach ($weeks as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div
                style="background: #f8fafc; padding: 25px; border-radius: 12px; border: 2px dashed #e2e8f0; text-align: center;">
                <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 15px; font-size: 1.1rem;">
                    <?php _e('Copy & Paste This Code:', 'olama-school'); ?>
                </label>
                <div id="shortcode-display-wrapper" style="position: relative; margin-bottom: 20px;">
                    <code id="generated-shortcode"
                        style="display: block; font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 1.2rem; background: #fff; padding: 20px 15px; border: 1px solid #cbd5e1; border-radius: 8px; color: #2563eb; overflow-x: auto; white-space: nowrap;">
                                        [olama_weekly_plan]
                                    </code>
                </div>
                <button type="button" class="button button-primary button-large" id="copy-shortcode"
                    style="height: 46px; padding: 0 30px; font-size: 1rem; font-weight: 600; border-radius: 8px; background: #2563eb;">
                    <span class="dashicons dashicons-admin-page" style="margin-top: 10px; margin-right: 5px;"></span>
                    <?php _e('Copy to Clipboard', 'olama-school'); ?>
                </button>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                function updateShortcode() {
                    var semester = $('#gen-semester').val();
                    var grade = $('#gen-grade').val();
                    var section = $('#gen-section').val();
                    var week = $('#gen-week').val();

                    var shortcode = '[olama_weekly_plan';
                    if (semester) shortcode += ' semester="' + semester + '"';
                    if (grade) shortcode += ' grade="' + grade + '"';
                    if (section) shortcode += ' section="' + section + '"';
                    if (week) shortcode += ' week="' + week + '"';
                    shortcode += ']';

                    $('#generated-shortcode').text(shortcode);
                }

                $('#gen-grade').on('change', function () {
                    var gradeId = $(this).val();
                    var $sectionSelect = $('#gen-section');

                    if (!gradeId) {
                        $sectionSelect.html('<option value=""><?php _e('-- Select Grade First --', 'olama-school'); ?></option>').prop('disabled', true);
                        updateShortcode();
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'olama_get_sections_by_grade',
                            grade_id: gradeId,
                            nonce: '<?php echo wp_create_nonce("olama_curriculum_nonce"); ?>'
                        },
                        success: function (response) {
                            if (response.success && response.data) {
                                var options = '<option value=""><?php _e('-- All Sections --', 'olama-school'); ?></option>';
                                $.each(response.data, function (i, section) {
                                    options += '<option value="' + section.id + '">' + section.section_name + '</option>';
                                });
                                $sectionSelect.html(options).prop('disabled', false);
                            } else {
                                $sectionSelect.html('<option value=""><?php _e('No sections found', 'olama-school'); ?></option>').prop('disabled', true);
                            }
                            updateShortcode();
                        }
                    });
                });

                $('#gen-semester, #gen-section, #gen-week').on('change', updateShortcode);

                $('#copy-shortcode').on('click', function () {
                    var text = $('#generated-shortcode').text().trim();
                    var $temp = $('<input>');
                    $('body').append($temp);
                    $temp.val(text).select();
                    document.execCommand('copy');
                    $temp.remove();

                    var $btn = $(this);
                    var originalContent = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 10px; margin-right: 5px;"></span> <?php _e('Copied!', 'olama-school'); ?>');
                    $btn.css('background', '#10b981');

                    setTimeout(function () {
                        $btn.html(originalContent);
                        $btn.css('background', '#2563eb');
                    }, 2000);
                });

                updateShortcode();
            });
        </script>
        <?php
    }

    public function render_curriculum_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
        ?>
        <div class="wrap olama-school-wrap">
            <h1><?php _e('Curriculum Management', 'olama-school'); ?></h1>

            <!-- Section 1: Filters -->
            <div class="olama-card" style="margin-bottom: 20px;">
                <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Semester', 'olama-school'); ?></label>
                        <select id="curriculum-semester" class="olama-select">
                            <option value=""><?php _e('-- Select Semester --', 'olama-school'); ?></option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?php echo $sem->id; ?>"><?php echo esc_html($sem->semester_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Grade', 'olama-school'); ?></label>
                        <select id="curriculum-grade" class="olama-select">
                            <option value=""><?php _e('-- Select Grade --', 'olama-school'); ?></option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade->id; ?>"><?php echo esc_html($grade->grade_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Subject', 'olama-school'); ?></label>
                        <select id="curriculum-subject" class="olama-select">
                            <!-- Populated via JS -->
                        </select>
                    </div>
                </div>

                <!-- Export / Import Section -->
                <div
                    style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <form method="post" id="olama-export-curriculum-form" style="margin: 0;">
                        <?php wp_nonce_field('olama_export_curriculum'); ?>
                        <input type="hidden" name="olama_export_curriculum" value="true">
                        <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
                        <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
                        <input type="hidden" name="subject_id" class="curriculum-hidden-subject">
                        <button type="submit" class="button button-secondary" id="olama-export-curriculum-btn" disabled>
                            <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                            <?php _e('Export Curriculum CSV', 'olama-school'); ?>
                        </button>
                    </form>

                    <div style="height: 24px; width: 1px; background: #cbd5e1; display: inline-block;"></div>

                    <form method="post" enctype="multipart/form-data" id="olama-import-curriculum-form"
                        style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <?php wp_nonce_field('olama_import_curriculum'); ?>
                        <input type="hidden" name="olama_import_type" value="curriculum">
                        <input type="hidden" name="semester_id" class="curriculum-hidden-semester">
                        <input type="hidden" name="grade_id" class="curriculum-hidden-grade">
                        <input type="hidden" name="subject_id" class="curriculum-hidden-subject">

                        <input type="file" name="olama_import_file" accept=".csv" required style="max-width: 200px;"
                            id="olama-import-curriculum-file" disabled>

                        <button type="submit" class="button button-primary" id="olama-import-curriculum-btn" disabled>
                            <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                            <?php _e('Import Curriculum CSV', 'olama-school'); ?>
                        </button>
                    </form>

                    <p class="description" style="margin: 0; font-size: 11px; color: #64748b;">
                        <?php _e('Select Semester, Grade, and Subject to enable Export/Import.', 'olama-school'); ?>
                    </p>
                </div>
            </div>

            <div class="curriculum-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <!-- Section 2: Units -->
                <div class="olama-card section-container" id="unit-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 1.2em;"><?php _e('1. Units', 'olama-school'); ?></h2>
                        <button type="button" id="add-unit-btn"
                            class="button button-small add-unit-btn"><?php _e('+ Add Unit', 'olama-school'); ?></button>
                    </div>

                    <div id="unit-form-container"
                        style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                        <input type="hidden" id="unit-id" value="">
                        <div style="margin-bottom: 10px;">
                            <input type="number" id="unit-number" placeholder="Unit #" style="width: 100%;" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <input type="text" id="unit-name" placeholder="Unit Name" style="width: 100%;" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <textarea id="unit-objectives" placeholder="Learning Objectives"
                                style="width: 100%; height: 60px;"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button"
                                class="button button-primary save-unit-btn"><?php _e('Save Unit', 'olama-school'); ?></button>
                            <button type="button" class="button cancel-unit-btn"><?php _e('Cancel', 'olama-school'); ?></button>
                        </div>
                    </div>

                    <div id="units-list" class="item-list">
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <?php _e('Select Subject to see units.', 'olama-school'); ?>
                        </p>
                    </div>
                </div>

                <!-- Section 3: Lessons -->
                <div class="olama-card section-container" id="lesson-section" style="opacity: 0.5; pointer-events: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 1.2em;"><?php _e('2. Lessons', 'olama-school'); ?></h2>
                        <button type="button" id="add-lesson-btn"
                            class="button button-small add-lesson-btn"><?php _e('+ Add Lesson', 'olama-school'); ?></button>
                    </div>

                    <div id="lesson-form-container"
                        style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                        <input type="hidden" id="lesson-id" value="">
                        <div style="margin-bottom: 10px;">
                            <input type="number" id="lesson-number" placeholder="Lesson #" style="width: 100%;" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <input type="text" id="lesson-title" placeholder="Lesson Title" style="width: 100%;" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <input type="url" id="lesson-url" placeholder="Video URL" style="width: 100%;">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <input type="number" id="lesson-periods" placeholder="Number of Periods" style="width: 100%;"
                                min="1" value="1" required>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button"
                                class="button button-primary save-lesson-btn"><?php _e('Save Lesson', 'olama-school'); ?></button>
                            <button type="button"
                                class="button cancel-lesson-btn"><?php _e('Cancel', 'olama-school'); ?></button>
                        </div>
                    </div>

                    <div id="lessons-list" class="item-list">
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <?php _e('Select Unit to see lessons.', 'olama-school'); ?>
                        </p>
                    </div>
                </div>

                <!-- Section 4: Question Bank -->
                <div class="olama-card section-container" id="question-section" style="opacity: 0.5; pointer-events: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 1.2em;"><?php _e('3. Question Bank', 'olama-school'); ?></h2>
                        <button type="button" id="add-question-btn"
                            class="button button-small add-question-btn"><?php _e('+ Add Question', 'olama-school'); ?></button>
                    </div>

                    <div id="question-form-container"
                        style="display: none; background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                        <input type="hidden" id="question-id" value="">
                        <div style="margin-bottom: 10px;">
                            <input type="number" id="question-number" placeholder="Question #" style="width: 100%;" required>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <textarea id="question-text" placeholder="Question" style="width: 100%; height: 60px;"
                                required></textarea>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <textarea id="question-answer" placeholder="Suggested Answer"
                                style="width: 100%; height: 60px;"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button"
                                class="button button-primary save-question-btn"><?php _e('Save Question', 'olama-school'); ?></button>
                            <button type="button"
                                class="button cancel-question-btn"><?php _e('Cancel', 'olama-school'); ?></button>
                        </div>
                    </div>

                    <div id="questions-list" class="item-list">
                        <p style="color: #999; text-align: center; padding: 20px;">
                            <?php _e('Select Lesson to see questions.', 'olama-school'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .section-container {
                height: 600px;
                display: flex;
                flex-direction: column;
            }

            .item-list {
                flex: 1;
                overflow-y: auto;
                border: 1px solid #eee;
                border-radius: 4px;
                padding: 10px;
            }

            .curriculum-item {
                padding: 10px;
                border-bottom: 1px solid #f0f0f0;
                cursor: pointer;
                position: relative;
                transition: background 0.2s;
            }

            .curriculum-item:hover {
                background: #f5faff;
            }

            .curriculum-item.active {
                background: #e6f3ff;
                border-left: 3px solid #2271b1;
            }

            .item-actions {
                position: absolute;
                top: 10px;
                right: 10px;
                display: none;
            }

            .curriculum-item:hover .item-actions {
                display: block;
            }

            .olama-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                font-size: 0.9em;
                color: #555;
            }

            .olama-select {
                width: 100%;
                height: 35px;
            }
        </style>
        <?php
    }

    /**
     * Render Curriculum Timeline Page Content
     */
    public function render_timeline_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
        ?>
        <div class="olama-timeline-container">

            <div class="olama-card" style="margin-bottom: 20px; padding: 20px;">
                <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Select Semester', 'olama-school'); ?></label>
                        <select id="timeline-semester" class="olama-select">
                            <option value=""><?php _e('Select Semester', 'olama-school'); ?></option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?php echo esc_attr($semester->id); ?>"
                                    data-start="<?php echo esc_attr($semester->start_date); ?>"
                                    data-end="<?php echo esc_attr($semester->end_date); ?>">
                                    <?php echo esc_html($semester->semester_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Select Grade', 'olama-school'); ?></label>
                        <select id="timeline-grade" class="olama-select">
                            <option value=""><?php _e('Choose Grade...', 'olama-school'); ?></option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo esc_attr($grade->id); ?>"><?php echo esc_html($grade->grade_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="olama-label"><?php _e('Select Subject', 'olama-school'); ?></label>
                        <select id="timeline-subject" class="olama-select" disabled>
                            <option value=""><?php _e('Select Grade first...', 'olama-school'); ?></option>
                        </select>
                    </div>
                    <div style="width: auto;">
                        <button type="button" id="load-timeline-btn" class="button button-primary button-large" disabled>
                            <?php _e('Load Timeline', 'olama-school'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div id="timeline-content" style="display: none;">
                <div class="olama-card" style="padding: 20px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                        <h2 id="timeline-title" style="margin: 0;"></h2>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" id="clear-timeline-btn" class="button button-secondary button-large">
                                <?php _e('Clear All Dates', 'olama-school'); ?>
                            </button>
                            <button type="button" id="save-timeline-btn" class="button button-primary button-large">
                                <?php _e('Save All Dates', 'olama-school'); ?>
                            </button>
                        </div>
                    </div>

                    <div id="timeline-grid-container">
                        <!-- Timeline items will be rendered here by JS -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Weekly Plan Management (Tabbed)
     */
    public function render_weekly_plan_management_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'creation';
        ?>
        <div class="wrap olama-school-wrap">
            <h1 style="font-weight: 700; color: #1e293b; margin-bottom: 25px;">
                <?php _e('Weekly Plan Management', 'olama-school'); ?>
            </h1>

            <h2 class="nav-tab-wrapper">
                <?php
                $base_params = array(
                    'grade_id' => isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0,
                    'section_id' => isset($_GET['section_id']) ? intval($_GET['section_id']) : 0,
                    'plan_month' => isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '',
                    'week_start' => isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '',
                );

                // For comparison tab, we might have different param names, but let's keep it simple for now and align the main ones
                $tabs = array(
                    'creation' => __('Plan Creation', 'olama-school'),
                    'list' => __('Plan List', 'olama-school'),
                    'comparison' => __('Plan Comparison', 'olama-school'),
                    'schedule' => __('Weekly Schedule', 'olama-school'),
                    'data' => __('Data Management', 'olama-school'),
                    'load' => __('Plan Load', 'olama-school'),
                    'coverage' => __('Curriculum Coverage', 'olama-school'),
                );

                foreach ($tabs as $tab_slug => $tab_label):
                    $url = add_query_arg(array_merge(array('page' => 'olama-school-plans', 'tab' => $tab_slug), array_filter($base_params)), admin_url('admin.php'));
                    ?>
                    <a href="<?php echo esc_url($url); ?>"
                        class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tab_label); ?></a>
                <?php endforeach; ?>
            </h2>

            <div class="olama-tab-content" style="margin-top: 20px;">
                <?php if ($active_tab === 'creation'): ?>
                    <?php $this->render_plan_page_content(); ?>
                <?php elseif ($active_tab === 'list'): ?>
                    <?php $this->render_plan_list_page_content(); ?>
                <?php elseif ($active_tab === 'comparison'): ?>
                    <?php $this->render_comparison_page_content(); ?>
                <?php elseif ($active_tab === 'schedule'): ?>
                    <?php $this->render_schedule_page_content(); ?>
                <?php elseif ($active_tab === 'data'): ?>
                    <?php $this->render_data_management_page_content(); ?>
                <?php elseif ($active_tab === 'load'): ?>
                    <?php $this->render_plan_load_page_content(); ?>
                <?php elseif ($active_tab === 'coverage'): ?>
                    <?php $this->render_curriculum_coverage_page_content(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Weekly Plan Creation Content
     */
    public function render_plan_page_content()
    {
        if (isset($_POST['save_plan']) && check_admin_referer('olama_save_plan', 'olama_plan_nonce')) {
            $data = $_POST;
            // Sanitize homework fields and notes
            $data['homework_sb'] = sanitize_textarea_field($data['homework_sb'] ?? '');
            $data['homework_eb'] = sanitize_textarea_field($data['homework_eb'] ?? '');
            $data['homework_nb'] = sanitize_textarea_field($data['homework_nb'] ?? '');
            $data['homework_ws'] = sanitize_textarea_field($data['homework_ws'] ?? '');
            $data['teacher_notes'] = sanitize_textarea_field($data['teacher_notes'] ?? '');

            $result = Olama_School_Plan::save_plan($data);
            if (is_wp_error($result)) {
                echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>' . __('Weekly plan saved successfully.', 'olama-school') . '</p></div>';
            }
        }

        $grades = Olama_School_Grade::get_grades();

        if (!current_user_can('manage_options')) {
            $user_id = get_current_user_id();
            global $wpdb;
            $assigned_grade_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT grade_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d",
                $user_id
            ));
            $grades = array_filter($grades, function ($g) use ($assigned_grade_ids) {
                return in_array($g->id, $assigned_grade_ids);
            });
            $grades = array_values($grades);
        }
        if (!$grades) {
            echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
            return;
        }

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);

        if (!current_user_can('manage_options')) {
            $user_id = get_current_user_id();
            global $wpdb;
            $assigned_section_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d AND grade_id = %d",
                $user_id,
                $selected_grade_id
            ));
            $sections = array_filter($sections, function ($s) use ($assigned_section_ids) {
                return in_array($s->id, $assigned_section_ids);
            });
            $sections = array_values($sections);
        }

        $selected_section_id = 0;
        if (!empty($sections)) {
            $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);

            // Validate section belongs to the selected grade
            $is_valid_section = false;
            foreach ($sections as $sec) {
                if (intval($sec->id) === $selected_section_id) {
                    $is_valid_section = true;
                    break;
                }
            }

            if (!$is_valid_section) {
                $selected_section_id = intval($sections[0]->id);
            }
        }

        // Date logic: Week start (Sunday) dropdown grouped by month
        $all_weeks = Olama_School_Academic::get_academic_weeks();
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key = date('Y-m', strtotime($val));
            $months_weeks[$m_key][] = array('val' => $val, 'label' => $label);
        }

        // Default month/week logic
        $today = time();
        $today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));

        $initial_week = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;
        $selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : date('Y-m', strtotime($initial_week));

        if (!isset($months_weeks[$selected_month]) && !empty($months_weeks)) {
            // Fallback to month of today or first available
            $today_month = date('Y-m');
            if (isset($months_weeks[$today_month])) {
                $selected_month = $today_month;
            } else {
                $m_keys = array_keys($months_weeks);
                $selected_month = $m_keys[0];
            }
        }

        $current_month_weeks = $months_weeks[$selected_month] ?? array();

        $week_start = $initial_week;
        $valid_week = false;
        foreach ($current_month_weeks as $w) {
            if ($w['val'] === $week_start) {
                $valid_week = true;
                break;
            }
        }
        if (!$valid_week && !empty($current_month_weeks)) {
            $week_start = $current_month_weeks[0]['val'];
        }

        $days = array(
            'Sunday' => date('Y-m-d', strtotime($week_start)),
            'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
            'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
            'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
            'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
        );

        $active_day = isset($_GET['active_day']) ? sanitize_text_field($_GET['active_day']) : 'Sunday';
        $selected_date = $days[$active_day];

        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));
        ?>
        <div class="olama-plan-creation-container">

            <!-- Section 1: Top Navigation (Filters) -->
            <div class="olama-filter-section"
                style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <form method="get" id="olama-plan-filters"
                    style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="olama-school-plans" />

                    <input type="hidden" name="tab" value="creation" />
                    <input type="hidden" name="active_day" id="active_day_input" value="<?php echo esc_attr($active_day); ?>" />

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Month', 'olama-school'); ?></label>
                        <select name="plan_month" onchange="this.form.submit()">
                            <?php foreach ($months_weeks as $m_key => $weeks): ?>
                                <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                                    <?php echo date('F Y', strtotime($m_key . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Week Start (Sunday)', 'olama-school'); ?></label>
                        <select name="week_start" onchange="this.form.submit()">
                            <?php if (empty($current_month_weeks)): ?>
                                <option value="<?php echo esc_attr($week_start); ?>"><?php echo esc_html($week_start); ?>
                                </option>
                            <?php else: ?>
                                <?php
                                $w_count = 1;
                                foreach ($current_month_weeks as $w): ?>
                                    <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                                        <?php echo "W$w_count " . esc_html($w['label']); ?>
                                    </option>
                                    <?php $w_count++; endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
                        <select name="grade_id" onchange="this.form.submit()">
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                                    <?php echo esc_html($grade->grade_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
                        <select name="section_id" onchange="this.form.submit()">
                            <?php if ($sections): ?>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                                        <?php echo esc_html($section->section_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="0"><?php _e('No sections found', 'olama-school'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Section 2: Days Tabs -->
            <div class="olama-tabs-wrapper" style="margin-bottom: 20px;">
                <ul class="olama-tabs"
                    style="display: flex; list-style: none; margin: 0; padding: 0; border-bottom: 1px solid #ddd;">
                    <?php foreach ($days as $day_name => $date): ?>
                        <li class="olama-tab <?php echo $active_day === $day_name ? 'active' : ''; ?>"
                            style="padding: 10px 20px; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; background: <?php echo $active_day === $day_name ? '#fff' : '#f1f1f1'; ?>; <?php echo $active_day === $day_name ? 'border-color: #ddd; margin-bottom: -1px;' : ''; ?>"
                            onclick="document.getElementById('active_day_input').value='<?php echo $day_name; ?>'; document.getElementById('olama-plan-filters').submit();">
                            <strong><?php echo esc_html($day_name); ?></strong><br>
                            <small><?php echo date('M d', strtotime($date)); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="olama-two-column" style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
                <!-- Left Column: Form -->
                <div class="olama-form-col"
                    style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <h2 style="margin-top: 0; color: #1d2327;"><?php echo esc_html($active_day); ?>'s Plan -
                        <?php echo date('Y-m-d', strtotime($selected_date)); ?>
                    </h2>
                    <form method="post" id="olama-weekly-plan-form">
                        <?php wp_nonce_field('olama_save_plan', 'olama_plan_nonce'); ?>
                        <input type="hidden" name="plan_id" id="olama-plan-id" value="0" />
                        <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                        <input type="hidden" name="plan_date" value="<?php echo $selected_date; ?>" />
                        <input type="hidden" name="teacher_id" value="<?php echo get_current_user_id(); ?>" />
                        <input type="hidden" name="period_number" value="0" />
                        <input type="hidden" name="status" id="olama-plan-status" value="draft" />

                        <div id="olama-edit-status-container"
                            style="display: none; margin-bottom: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <span
                                        style="font-weight: 600; color: #64748b; font-size: 0.85rem; text-transform: uppercase;"><?php _e('Current Status', 'olama-school'); ?>:</span>
                                    <span id="olama-current-status-badge" style="margin-left: 8px;"></span>
                                </div>
                                <label
                                    style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-weight: 500;">
                                    <input type="checkbox" id="olama-revert-draft-check" />
                                    <?php _e('Revert to Draft', 'olama-school'); ?>
                                </label>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Subject', 'olama-school'); ?></label>
                                <select name="subject_id" id="olama-subject-select" style="width: 100%; height: 40px;" required>
                                    <option value=""><?php _e('-- Select Subject --', 'olama-school'); ?></option>
                                    <?php
                                    $active_year = Olama_School_Academic::get_active_year();
                                    $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : [];
                                    $semester_id = $semesters[0]->id ?? 0;
                                    $scheduled_subjects = Olama_School_Schedule::get_unique_subjects_for_day($selected_section_id, $active_day, $semester_id);

                                    if (!current_user_can('manage_options')) {
                                        $teacher_id = get_current_user_id();
                                        $assigned_ids = Olama_School_Teacher::get_assigned_subjects($teacher_id, $selected_section_id);
                                        $scheduled_subjects = array_filter($scheduled_subjects, function ($subj) use ($assigned_ids) {
                                            return in_array($subj->id, $assigned_ids);
                                        });
                                    }

                                    foreach ($scheduled_subjects as $subj): ?>
                                        <option value="<?php echo $subj->id; ?>"><?php echo esc_html($subj->subject_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Unit', 'olama-school'); ?></label>
                                <select name="unit_id" id="olama-unit-select" style="width: 100%; height: 40px;" disabled>
                                    <option value=""><?php _e('-- Select Unit --', 'olama-school'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Lesson', 'olama-school'); ?></label>
                                <select name="lesson_id" id="olama-lesson-select" style="width: 100%; height: 40px;" disabled>
                                    <option value=""><?php _e('-- Select Lesson --', 'olama-school'); ?></option>
                                </select>
                                <div id="olama-lesson-progress-check"
                                    style="margin-top: 10px; display: none; text-align: center;"></div>
                            </div>
                        </div>

                        <div id="olama-questions-area" style="margin-bottom: 20px; display: none;">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Questions to Cover', 'olama-school'); ?></label>
                            <div id="olama-questions-list"
                                style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto;">
                                <!-- AJAX populated -->
                            </div>
                        </div>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Homework (Student Book)', 'olama-school'); ?></label>
                                <textarea name="homework_sb" rows="3" style="width: 100%;"
                                    placeholder="Page numbers or details..."></textarea>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Homework (Exercise Book)', 'olama-school'); ?></label>
                                <textarea name="homework_eb" rows="3" style="width: 100%;"
                                    placeholder="Page numbers or details..."></textarea>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Homework (Notebook)', 'olama-school'); ?></label>
                                <textarea name="homework_nb" rows="3" style="width: 100%;"
                                    placeholder="Notebook instructions..."></textarea>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Homework (Worksheet)', 'olama-school'); ?></label>
                                <textarea name="homework_ws" rows="3" style="width: 100%;"
                                    placeholder="Worksheet details..."></textarea>
                            </div>
                        </div>

                        <div class="olama-form-group" style="margin-bottom: 20px;">
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Teacher\'s Notes', 'olama-school'); ?></label>
                            <textarea name="teacher_notes" rows="3" style="width: 100%;"
                                placeholder="Additional notes..."></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 30px;">
                            <button type="button" id="olama-cancel-edit-btn" class="button button-large"
                                style="margin-right: 15px; display: none; height: 46px; font-weight: 600;">
                                <?php _e('Cancel', 'olama-school'); ?>
                            </button>
                            <input type="submit" name="save_plan" id="olama-save-plan-btn"
                                class="button button-primary button-large"
                                style="height: 46px; padding: 0 30px; font-weight: 600;"
                                value="<?php _e('Save as Draft', 'olama-school'); ?>" />
                        </div>
                    </form>
                </div>

                <!-- Right Column: Today's Summary -->
                <div class="olama-list-col"
                    style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <h2 style="margin-top: 0; color: #1d2327;"><?php _e('Saved Plans for Today', 'olama-school'); ?></h2>
                    <?php
                    $today_plans = array_filter($all_plans, function ($p) use ($selected_date) {
                        return $p->plan_date === $selected_date;
                    });
                    if ($today_plans): ?>
                        <?php foreach ($today_plans as $plan):
                            $q_ids = Olama_School_Plan::get_plan_questions($plan->id);
                            $plan_json = wp_json_encode([
                                'id' => $plan->id,
                                'grade_id' => $selected_grade_id,
                                'section_id' => $selected_section_id,
                                'subject_id' => $plan->subject_id,
                                'unit_id' => $plan->unit_id,
                                'lesson_id' => $plan->lesson_id,
                                'homework_sb' => $plan->homework_sb,
                                'homework_eb' => $plan->homework_eb,
                                'homework_nb' => $plan->homework_nb,
                                'homework_ws' => $plan->homework_ws,
                                'teacher_notes' => $plan->teacher_notes,
                                'question_ids' => $q_ids,
                                'status' => $plan->status
                            ]);
                            ?>
                            <div class="olama-plan-item" data-plan="<?php echo esc_attr($plan_json); ?>"
                                style="border-left: 4px solid <?php echo esc_attr($plan->color_code); ?>; padding: 15px; margin-bottom: 15px; background: #fcfcfc; border-radius: 0 8px 8px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <strong
                                        style="font-size: 1.1em; color: <?php echo esc_attr($plan->color_code); ?>;"><?php echo esc_html($plan->subject_name); ?></strong>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <span class="status-badge <?php echo esc_attr($plan->status); ?>"
                                            style="font-size: 0.8em; padding: 2px 8px; border-radius: 12px; background: #eee; color: #666;"><?php echo ucfirst($plan->status); ?></span>
                                        <a href="#" class="olama-edit-plan" title="<?php _e('Edit', 'olama-school'); ?>"
                                            style="color: #666; text-decoration: none;"><i class="dashicons dashicons-edit"></i></a>
                                        <a href="#" class="olama-delete-plan" title="<?php _e('Delete', 'olama-school'); ?>"
                                            style="color: #d63638; text-decoration: none;"><i class="dashicons dashicons-trash"></i></a>
                                    </div>
                                </div>
                                <div style="font-size: 0.95em; color: #444; margin-bottom: 5px;">
                                    <?php echo esc_html($plan->unit_name); ?> -
                                    <strong><?php echo esc_html($plan->lesson_title); ?></strong>
                                </div>
                                <?php if ($plan->homework_sb || $plan->homework_eb): ?>
                                    <div style="font-size: 0.85em; color: #666;">
                                        <i class="dashicons dashicons-book-alt" style="font-size: 16px; margin-right: 5px;"></i>
                                        <?php echo $plan->homework_sb ? 'SB: ' . esc_html($plan->homework_sb) : ''; ?>
                                        <?php echo $plan->homework_eb ? ' EB: ' . esc_html($plan->homework_eb) : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div
                            style="text-align: center; color: #999; padding: 40px 20px; border: 2px dashed #eee; border-radius: 12px;">
                            <i class="dashicons dashicons-calendar-alt"
                                style="font-size: 40px; margin-bottom: 10px; width: 40px; height: 40px;"></i>
                            <p><?php _e('No plans saved for this day.', 'olama-school'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
    }

    /**
     * Render weekly plan list page (grouped by day)
     */
    /**
     * Render Weekly Plan List Content
     */
    public function render_plan_list_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        if (!$grades) {
            echo '<div class="error"><p>' . __('Please create grades first.', 'olama-school') . '</p></div>';
            return;
        }

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0);
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);

        $selected_section_id = 0;
        if (!empty($sections)) {
            $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : intval($sections[0]->id);

            // Validate section belongs to the selected grade
            $is_valid_section = false;
            foreach ($sections as $sec) {
                if (intval($sec->id) === $selected_section_id) {
                    $is_valid_section = true;
                    break;
                }
            }

            if (!$is_valid_section) {
                $selected_section_id = intval($sections[0]->id);
            }
        }

        // Reuse week selection logic
        $all_weeks = Olama_School_Academic::get_academic_weeks();
        $months_weeks = array();
        foreach ($all_weeks as $val => $label) {
            $m_key = date('Y-m', strtotime($val));
            $months_weeks[$m_key][] = array('val' => $val, 'label' => $label);
        }

        $today = time();
        $today_val = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
        $initial_week = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : $today_val;
        $selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : date('Y-m', strtotime($initial_week));

        if (!isset($months_weeks[$selected_month]) && !empty($months_weeks)) {
            $m_keys = array_keys($months_weeks);
            $selected_month = $m_keys[0];
        }

        $current_month_weeks = $months_weeks[$selected_month] ?? array();
        $week_start = $initial_week;
        $valid_week = false;
        foreach ($current_month_weeks as $w) {
            if ($w['val'] === $week_start) {
                $valid_week = true;
                break;
            }
        }
        if (!$valid_week && !empty($current_month_weeks)) {
            $week_start = $current_month_weeks[0]['val'] ?? '';
        }

        $days = array(
            'Sunday' => date('Y-m-d', strtotime($week_start)),
            'Monday' => date('Y-m-d', strtotime($week_start . ' +1 day')),
            'Tuesday' => date('Y-m-d', strtotime($week_start . ' +2 days')),
            'Wednesday' => date('Y-m-d', strtotime($week_start . ' +3 days')),
            'Thursday' => date('Y-m-d', strtotime($week_start . ' +4 days')),
        );

        $all_plans = Olama_School_Plan::get_plans($selected_section_id, $week_start, date('Y-m-d', strtotime($week_start . ' +4 days')));

        // Group plans by date
        $grouped_plans = array();
        foreach ($days as $day_name => $date) {
            $grouped_plans[$date] = array_filter($all_plans, function ($p) use ($date) {
                return $p->plan_date === $date;
            });
        }
        ?>
            <div class="olama-plan-list-container">

                <!-- Filters -->
                <div class="olama-filter-section"
                    style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <form method="get" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="page" value="olama-school-plans" />
                        <input type="hidden" name="tab" value="list" />
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Month', 'olama-school'); ?></label>
                            <select name="plan_month" onchange="this.form.submit()">
                                <?php foreach ($months_weeks as $m_key => $weeks): ?>
                                    <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                                        <?php echo date('F Y', strtotime($m_key . '-01')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Week Start', 'olama-school'); ?></label>
                            <select name="week_start" onchange="this.form.submit()">
                                <?php
                                $w_count = 1;
                                foreach ($current_month_weeks as $w): ?>
                                    <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                                        <?php echo "W$w_count " . esc_html($w['label']); ?>
                                    </option>
                                    <?php $w_count++; endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
                            <select name="grade_id" onchange="this.form.submit()">
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                                        <?php echo esc_html($grade->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
                            <select name="section_id" onchange="this.form.submit()">
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                                        <?php echo esc_html($section->section_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="margin-left: auto;">
                            <button type="button" id="olama-bulk-approve-btn" class="button button-primary"
                                style="height: 35px; background: #10b981; border-color: #059669; font-weight: 600; margin-top: 20px;"
                                data-section="<?php echo $selected_section_id; ?>"
                                data-week="<?php echo esc_attr($week_start); ?>"
                                data-nonce="<?php echo wp_create_nonce('olama_admin_nonce'); ?>">
                                <span class="dashicons dashicons-yes-alt" style="margin-top: 5px; margin-right: 5px;"></span>
                                <?php _e('Approve All', 'olama-school'); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Weekly Grid -->
                <div class="olama-weekly-list-grid"
                    style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; align-items: stretch;">
                    <?php foreach ($days as $day_name => $date): ?>
                        <div class="olama-day-column"
                            style="background: #fbfbfb; border-radius: 8px; border: 1px solid #eee; display: flex; flex-direction: column;">
                            <div class="day-header"
                                style="background: #f1f1f1; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; border-radius: 8px 8px 0 0;">
                                <strong style="display: block; color: #1d2327;"><?php echo esc_html($day_name); ?></strong>
                                <small style="color: #666;"><?php echo date('M d', strtotime($date)); ?></small>
                            </div>
                            <div class="day-content" style="padding: 10px; flex-grow: 1;">
                                <?php if (!empty($grouped_plans[$date])): ?>
                                    <?php foreach ($grouped_plans[$date] as $plan):
                                        $status_data = $this->get_progress_status($plan->plan_date, $plan->lesson_start_date, $plan->lesson_end_date);
                                        ?>
                                        <div class="olama-plan-card" data-plan='<?php echo esc_attr(wp_json_encode($plan)); ?>'
                                            style="border-left: 4px solid <?php echo esc_attr($plan->color_code); ?>; background: #fff; padding: 10px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 10px; cursor: pointer; position: relative;">

                                            <?php if ($status_data): ?>
                                                <div class="olama-progress-badge <?php echo esc_attr($status_data['class']); ?>"
                                                    title="<?php echo esc_attr($status_data['label']); ?>">
                                                    <?php echo esc_html($status_data['label']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div
                                                style="font-weight: 700; color: <?php echo esc_attr($plan->color_code); ?>; font-size: 0.9em; margin-bottom: 4px;">
                                                <?php echo esc_html($plan->subject_name); ?>
                                            </div>
                                            <div style="font-size: 0.85em; color: #333; margin-bottom: 6px; line-height: 1.3;">
                                                <?php echo esc_html($plan->lesson_title); ?>
                                            </div>
                                            <?php if ($plan->homework_sb || $plan->homework_eb): ?>
                                                <div
                                                    style="font-size: 0.75em; color: #777; border-top: 1px solid #eee; pt: 6px; margin-top: 6px;">
                                                    <i class="dashicons dashicons-book-alt"
                                                        style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></i>
                                                    <?php echo $plan->homework_sb ? 'SB: ' . esc_html($plan->homework_sb) : ''; ?>
                                                    <?php echo $plan->homework_eb ? ' EB: ' . esc_html($plan->homework_eb) : ''; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p
                                        style="text-align: center; color: #ccc; font-style: italic; font-size: 0.85em; margin-top: 20px;">
                                        <?php _e('No plans', 'olama-school'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Plan Details Section -->
                <div id="olama-plan-details-container" style="margin-top: 30px;">
                    <div id="olama-plan-details-card"
                        style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); display: none;">
                        <!-- Content will be injected by JS -->
                    </div>
                </div>
            </div>

            <?php
    }


    /**
     * Render Weekly Schedule (Form 14)
     */
    public function render_schedule_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        $teachers = Olama_School_Teacher::get_teachers();

        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : ($grades[0]->id ?? 0);
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);
        $selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : ($sections[0]->id ?? 0);
        $selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

        // Local mapping for days
        $days_map = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $settings = get_option('olama_school_settings', array());
        $start_day_name = $settings['start_day'] ?? 'Monday';
        $last_day_name = $settings['last_day'] ?? 'Thursday';

        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : [];

        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($semesters[0]->id ?? 0);

        $periods_to_show = 8;
        if ($selected_grade_id) {
            $current_grade = Olama_School_Grade::get_grade($selected_grade_id);
            if ($current_grade && isset($current_grade->periods_count)) {
                $periods_to_show = $current_grade->periods_count;
            }
        }

        // Days of week in order
        $all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Find indices of start and last day
        $start_idx = array_search($start_day_name, $all_days);
        $last_idx = array_search($last_day_name, $all_days);

        if ($start_idx === false)
            $start_idx = 0;
        if ($last_idx === false)
            $last_idx = 4; // Default to Thursday

        $display_days = [];
        if ($start_idx <= $last_idx) {
            for ($i = $start_idx; $i <= $last_idx; $i++) {
                $display_days[] = $all_days[$i];
            }
        } else {
            // Wraps around, e.g. Saturday to Wednesday
            for ($i = $start_idx; $i < 7; $i++) {
                $display_days[] = $all_days[$i];
            }
            for ($i = 0; $i <= $last_idx; $i++) {
                $display_days[] = $all_days[$i];
            }
        }

        // Fetch master schedule
        $schedule = [];
        if ($selected_section_id && $selected_semester_id) {
            $schedule = Olama_School_Schedule::get_schedule($selected_section_id, $selected_semester_id);
        }

        // Subjects for dropdown
        $subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id) : [];

        $scheduled_sections = Olama_School_Schedule::get_scheduled_sections();

        ?>


            <?php if (isset($_GET['message']) && $_GET['message'] === 'schedule_saved'): ?>
                <div class="updated notice is-dismissible">
                    <p><?php _e('Master schedule saved successfully.', 'olama-school'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Schedule List Section -->
            <div class="olama-card"
                style="margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h2 style="margin-top: 0;"><?php _e('Saved Schedules', 'olama-school'); ?></h2>
                <?php if ($scheduled_sections): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Semester', 'olama-school'); ?></th>
                                <th><?php _e('Grade', 'olama-school'); ?></th>
                                <th><?php _e('Section', 'olama-school'); ?></th>
                                <th><?php _e('Actions', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduled_sections as $ss): ?>
                                <tr>
                                    <td><?php echo esc_html($ss->semester_name); ?></td>
                                    <td><?php echo esc_html($ss->grade_name); ?></td>
                                    <td><?php echo esc_html($ss->section_name); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=schedule&grade_id=' . $ss->grade_id . '&section_id=' . $ss->section_id . '&semester_id=' . $ss->semester_id); ?>"
                                            class="button button-small"><?php _e('Edit', 'olama-school'); ?></a>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'delete_full_schedule', 'section_id' => $ss->section_id, 'semester_id' => $ss->semester_id]), 'olama_delete_full_schedule'); ?>"
                                            class="button button-small button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e('Delete this entire schedule?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">
                        <?php _e('No schedules defined yet. Use the filters below to create one.', 'olama-school'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="olama-filter-section"
                style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="olama-school-plans" />
                    <input type="hidden" name="tab" value="schedule" />

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
                        <select name="grade_id" onchange="this.form.submit()">
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                                    <?php echo esc_html($grade->grade_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
                        <select name="section_id" onchange="this.form.submit()">
                            <?php if ($sections): ?>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                                        <?php echo esc_html($section->section_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="0"><?php _e('No sections', 'olama-school'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Semester', 'olama-school'); ?></label>
                        <select name="semester_id" onchange="this.form.submit()">
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                                    <?php echo esc_html($s->semester_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-left: auto;">
                        <button type="button" class="button button-secondary" onclick="window.print()"><span
                                class="dashicons dashicons-printer" style="margin-top: 4px;"></span>
                            <?php _e('Print Schedule', 'olama-school'); ?></button>
                    </div>
                </form>
            </div>

            <div class="olama-schedule-container"
                style="background: #fff; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); padding: 5px; overflow-x: auto;">
                <form method="post">
                    <?php wp_nonce_field('olama_save_bulk_schedule', 'olama_schedule_nonce'); ?>
                    <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>" />
                    <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                    <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />

                    <div style="display: flex; justify-content: flex-end; padding: 10px;">
                        <button type="submit" name="olama_save_bulk_schedule" value="1" class="button button-primary">
                            <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                            <?php _e('Save Master Schedule', 'olama-school'); ?>
                        </button>
                    </div>

                    <table class="wp-list-table widefat fixed striped" style="border: none;">
                        <thead>
                            <tr style="background: #2271b1; color: #fff;">
                                <th
                                    style="width: 80px; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); color: #fff !important;">
                                    <?php _e('Period', 'olama-school'); ?>
                                </th>
                                <?php foreach ($display_days as $day): ?>
                                    <th
                                        style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); color: #fff !important;">
                                        <div style="font-size: 1.1em; font-weight: 700;"><?php echo esc_html($day); ?></div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($period = 1; $period <= $periods_to_show; $period++): ?>
                                <tr>
                                    <td
                                        style="text-align: center; font-weight: 700; background: #f8f9fa; border-right: 1px solid #eee; color: #2271b1; font-size: 1.2em;">
                                        <?php echo $period; ?>
                                    </td>
                                    <?php foreach ($display_days as $day):
                                        $item = $schedule[$day][$period] ?? null;
                                        $item_subject_id = $item ? $item->subject_id : 0;
                                        ?>
                                        <td style="padding: 15px; border-right: 1px solid #eee; vertical-align: top;">
                                            <select name="schedule[<?php echo esc_attr($day); ?>][<?php echo $period; ?>]"
                                                style="width: 100%; font-size: 12px;">
                                                <option value=""><?php _e('-- Select Subject --', 'olama-school'); ?></option>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <option value="<?php echo $subject->id; ?>" <?php selected($item_subject_id, $subject->id); ?>>
                                                        <?php echo esc_html($subject->subject_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($item): ?>
                                                <div
                                                    style="margin-top: 5px; font-size: 11px; color: <?php echo esc_attr($item->color_code ?: '#2271b1'); ?>; font-weight: 600;">
                                                    ● <?php _e('Scheduled', 'olama-school'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <div style="display: flex; justify-content: flex-end; padding: 20px;">
                        <button type="submit" name="olama_save_bulk_schedule" value="1"
                            class="button button-primary button-large">
                            <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                            <?php _e('Save Master Schedule', 'olama-school'); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
    }


    /**
     * Render Dashboard (Form 18)
     */
    public function render_dashboard_page()
    {
        global $wpdb;

        // Stats
        $count_grades = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_grades");
        $count_sections = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_sections");
        $count_teachers = count(Olama_School_Teacher::get_teachers());
        $count_students = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}olama_students");

        // Plan stats
        $plan_stats = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$wpdb->prefix}olama_plans GROUP BY status");
        $stats_by_status = array('draft' => 0, 'submitted' => 0, 'approved' => 0);
        foreach ($plan_stats as $s) {
            $stats_by_status[$s->status] = $s->count;
        }

        // Recent Plans
        $recent_plans = $wpdb->get_results("
            SELECT p.*, s.subject_name, sec.section_name, g.grade_name 
            FROM {$wpdb->prefix}olama_plans p
            JOIN {$wpdb->prefix}olama_subjects s ON p.subject_id = s.id
            JOIN {$wpdb->prefix}olama_sections sec ON p.section_id = sec.id
            JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
            ORDER BY p.created_at DESC LIMIT 5
        ");

        ?>
            <div class="wrap olama-school-wrap">
                <h1><?php _e('School Dashboard', 'olama-school'); ?></h1>

                <div class="olama-stats-grid"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; margin-top: 20px;">
                    <div class="olama-stat-card"
                        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                        <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_grades; ?></div>
                        <div style="color: #666; font-weight: 600;"><?php _e('Grades', 'olama-school'); ?></div>
                    </div>
                    <div class="olama-stat-card"
                        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                        <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_sections; ?>
                        </div>
                        <div style="color: #666; font-weight: 600;"><?php _e('Sections', 'olama-school'); ?></div>
                    </div>
                    <div class="olama-stat-card"
                        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                        <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_teachers; ?>
                        </div>
                        <div style="color: #666; font-weight: 600;"><?php _e('Teachers', 'olama-school'); ?></div>
                    </div>
                    <div class="olama-stat-card"
                        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center;">
                        <div style="font-size: 2.5em; font-weight: 700; color: #2271b1;"><?php echo $count_students; ?>
                        </div>
                        <div style="color: #666; font-weight: 600;"><?php _e('Students', 'olama-school'); ?></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                    <!-- Plan Overview -->
                    <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <?php _e('Plans Overview', 'olama-school'); ?>
                        </h2>
                        <div style="margin: 20px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span><?php _e('Drafts', 'olama-school'); ?></span>
                                <span style="font-weight: 700;"><?php echo $stats_by_status['draft']; ?></span>
                            </div>
                            <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                <div
                                    style="width: <?php echo $stats_by_status['draft'] > 0 ? 100 : 0; ?>%; height: 100%; background: #ccc;">
                                </div>
                            </div>
                        </div>
                        <div style="margin: 20px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span><?php _e('Submitted', 'olama-school'); ?></span>
                                <span
                                    style="font-weight: 700; color: #dba617;"><?php echo $stats_by_status['submitted']; ?></span>
                            </div>
                            <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                <div
                                    style="width: <?php echo $stats_by_status['submitted'] > 0 ? 100 : 0; ?>%; height: 100%; background: #dba617;">
                                </div>
                            </div>
                        </div>
                        <div style="margin: 20px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span><?php _e('Approved', 'olama-school'); ?></span>
                                <span
                                    style="font-weight: 700; color: #00a32a;"><?php echo $stats_by_status['approved']; ?></span>
                            </div>
                            <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                <div
                                    style="width: <?php echo $stats_by_status['approved'] > 0 ? 100 : 0; ?>%; height: 100%; background: #00a32a;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <?php _e('Recent Plans', 'olama-school'); ?>
                        </h2>
                        <table class="wp-list-table widefat fixed striped" style="box-shadow: none; border: none;">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'olama-school'); ?></th>
                                    <th><?php _e('Details', 'olama-school'); ?></th>
                                    <th><?php _e('Status', 'olama-school'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_plans): ?>
                                    <?php foreach ($recent_plans as $rp): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($rp->plan_date)); ?></td>
                                            <td>
                                                <strong><?php echo esc_html($rp->subject_name); ?></strong><br>
                                                <span
                                                    style="font-size: 0.85em; color: #666;"><?php echo esc_html($rp->grade_name . ' - ' . $rp->section_name); ?></span>
                                            </td>
                                            <td>
                                                <span
                                                    style="padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 600; background: <?php echo ($rp->status == 'approved' ? '#e7ffef' : ($rp->status == 'submitted' ? '#fff9e7' : '#f0f0f1')); ?>; color: <?php echo ($rp->status == 'approved' ? '#2271b1' : ($rp->status == 'submitted' ? '#996800' : '#50575e')); ?>;">
                                                    <?php echo ucfirst($rp->status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #999;">
                                            <?php _e('No recent plans found.', 'olama-school'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
    }

    /**
     * Render Reports (Forms 16, 19)
     */
    public function render_reports_page()
    {
        global $wpdb;

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : ($grades[0]->id ?? 0);

        // Report 1: Plan Status by Teacher
        $teacher_stats = $wpdb->get_results("
            SELECT u.display_name, 
                   COUNT(p.id) as total_plans,
                   SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved_plans,
                   SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted_plans,
                   SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft_plans
            FROM {$wpdb->users} u
            JOIN {$wpdb->prefix}olama_teachers t ON u.ID = t.id
            LEFT JOIN {$wpdb->prefix}olama_plans p ON u.ID = p.teacher_id
            GROUP BY u.ID
        ");

        // Report 2: Homework Summary for selected grade
        $homework_summary = $wpdb->get_results($wpdb->prepare("
            SELECT sec.section_name, COUNT(p.id) as total_homeworks
            FROM {$wpdb->prefix}olama_sections sec
            LEFT JOIN {$wpdb->prefix}olama_plans p ON sec.id = p.section_id AND p.homework_content IS NOT NULL AND p.homework_content != ''
            WHERE sec.grade_id = %d
            GROUP BY sec.id
        ", $selected_grade_id));

        ?>
            <div class="wrap olama-school-wrap">
                <h1><?php _e('School Reports', 'olama-school'); ?></h1>

                <div class="olama-tabs" style="margin-top: 20px;">
                    <h2 class="nav-tab-wrapper">
                        <a href="#completion" class="nav-tab nav-tab-active"
                            onclick="return false;"><?php _e('Plan Completion', 'olama-school'); ?></a>
                        <a href="#homework" class="nav-tab"
                            onclick="return false;"><?php _e('Homework Summary', 'olama-school'); ?></a>
                    </h2>
                </div>

                <div id="completion-report" class="olama-report-section"
                    style="margin-top: 20px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3><?php _e('Teacher Plan Status (Current Term)', 'olama-school'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Teacher', 'olama-school'); ?></th>
                                <th><?php _e('Total Plans', 'olama-school'); ?></th>
                                <th><?php _e('Approved', 'olama-school'); ?></th>
                                <th><?php _e('Submitted', 'olama-school'); ?></th>
                                <th><?php _e('Draft', 'olama-school'); ?></th>
                                <th><?php _e('Completion %', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_stats as $ts): ?>
                                <?php $perc = $ts->total_plans > 0 ? round(($ts->approved_plans / $ts->total_plans) * 100) : 0; ?>
                                <tr>
                                    <td><strong><?php echo esc_html($ts->display_name); ?></strong></td>
                                    <td><?php echo $ts->total_plans; ?></td>
                                    <td style="color: #00a32a; font-weight: 600;"><?php echo $ts->approved_plans; ?></td>
                                    <td style="color: #dba617;"><?php echo $ts->submitted_plans; ?></td>
                                    <td style="color: #666;"><?php echo $ts->draft_plans; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div
                                                style="flex-grow: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                                <div
                                                    style="width: <?php echo $perc; ?>%; height: 100%; background: <?php echo $perc > 80 ? '#00a32a' : ($perc > 50 ? '#dba617' : '#d63638'); ?>;">
                                                </div>
                                            </div>
                                            <span><?php echo $perc; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="homework-report" class="olama-report-section"
                    style="margin-top: 30px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;"><?php _e('Homework Frequency by Section', 'olama-school'); ?></h3>
                        <form method="get">
                            <input type="hidden" name="page" value="olama-school-reports">
                            <select name="grade_id" onchange="this.form.submit()">
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                        <?php echo esc_html($g->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                        <?php foreach ($homework_summary as $hs): ?>
                            <div
                                style="background: #f9f9f9; padding: 15px; border-radius: 6px; border-left: 4px solid #2271b1; text-align: center;">
                                <div style="font-size: 0.9em; color: #666; margin-bottom: 5px;">
                                    <?php echo esc_html($hs->section_name); ?>
                                </div>
                                <div style="font-size: 1.8em; font-weight: 700; color: #2271b1;">
                                    <?php echo $hs->total_homeworks; ?>
                                </div>
                                <div style="font-size: 0.8em; color: #999;"><?php _e('Assigned Tasks', 'olama-school'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button class="button button-primary" onclick="window.print()"><span class="dashicons dashicons-printer"
                            style="margin-top: 4px;"></span> <?php _e('Print all Reports', 'olama-school'); ?></button>
                </div>
            </div>
            <?php
    }

    /**
     * Render Plan Comparison Content
     */
    public function render_comparison_page_content()
    {
        global $wpdb;

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($_GET['compare_grade_id']) ? intval($_GET['compare_grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0));
        $sections = Olama_School_Section::get_by_grade($selected_grade_id);

        $sec1_id = 0;
        if (!empty($sections)) {
            $sec1_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : (isset($_GET['sec1']) ? intval($_GET['sec1']) : intval($sections[0]->id));

            // Validate section belongs to the selected grade
            $is_valid_sec1 = false;
            foreach ($sections as $sec) {
                if (intval($sec->id) === $sec1_id) {
                    $is_valid_sec1 = true;
                    break;
                }
            }
            if (!$is_valid_sec1) {
                $sec1_id = intval($sections[0]->id);
            }
        }

        $sec2_id = isset($_GET['sec2']) ? intval($_GET['sec2']) : ($sections[1]->id ?? 0);

        // Fetch subjects for this grade
        $subjects = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_subjects WHERE grade_id = %d", $selected_grade_id));

        ?>
            <div class="olama-comparison-container">
                <p><?php _e('Compare progress across sections for the same grade.', 'olama-school'); ?></p>

                <div
                    style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ccd0d4;">
                    <form method="get" style="display: flex; gap: 20px; align-items: flex-end;">
                        <input type="hidden" name="page" value="olama-school-plans">
                        <input type="hidden" name="tab" value="comparison">
                        <div>
                            <label
                                style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Grade', 'olama-school'); ?></label>
                            <select name="compare_grade_id" onchange="this.form.submit()">
                                <?php foreach ($grades as $g): ?>
                                    <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                        <?php echo esc_html($g->grade_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($sections): ?>
                            <div>
                                <label
                                    style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Section 1', 'olama-school'); ?></label>
                                <select name="sec1">
                                    <?php foreach ($sections as $s): ?>
                                        <option value="<?php echo $s->id; ?>" <?php selected($sec1_id, $s->id); ?>>
                                            <?php echo esc_html($s->section_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label
                                    style="display: block; margin-bottom: 5px; font-weight: 600;"><?php _e('Section 2', 'olama-school'); ?></label>
                                <select name="sec2">
                                    <?php foreach ($sections as $s): ?>
                                        <option value="<?php echo $s->id; ?>" <?php selected($sec2_id, $s->id); ?>>
                                            <?php echo esc_html($s->section_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="button button-primary"><?php _e('Compare', 'olama-school'); ?></button>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($sec1_id && $sec2_id): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <div
                            style="background: #fff; padding: 25px; border-radius: 8px; border-top: 4px solid #2271b1; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <h2><?php echo esc_html(Olama_School_Section::get_section($sec1_id)->section_name ?? 'Section 1'); ?>
                            </h2>
                            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <th><?php _e('Subject', 'olama-school'); ?></th>
                                        <th><?php _e('Current Progress', 'olama-school'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $sub):
                                        $latest_plan = $wpdb->get_row($wpdb->prepare(
                                            "SELECT p.*, u.unit_name, l.lesson_title 
                                             FROM {$wpdb->prefix}olama_plans p
                                             LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id
                                             LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id
                                             WHERE p.section_id = %d AND p.subject_id = %d 
                                             ORDER BY p.plan_date DESC LIMIT 1",
                                            $sec1_id,
                                            $sub->id
                                        ));
                                        ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($sub->subject_name); ?></strong></td>
                                            <td><?php echo $latest_plan ? esc_html(($latest_plan->unit_name ?? '') . ' - ' . ($latest_plan->lesson_title ?? '')) : '<i style="color:#999">No data</i>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div
                            style="background: #fff; padding: 25px; border-radius: 8px; border-top: 4px solid #d63638; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <h2><?php echo esc_html(Olama_School_Section::get_section($sec2_id)->section_name ?? 'Section 2'); ?>
                            </h2>
                            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                                <thead>
                                    <tr>
                                        <th><?php _e('Subject', 'olama-school'); ?></th>
                                        <th><?php _e('Current Progress', 'olama-school'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $sub):
                                        $latest_plan = $wpdb->get_row($wpdb->prepare(
                                            "SELECT p.*, u.unit_name, l.lesson_title 
                                             FROM {$wpdb->prefix}olama_plans p
                                             LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id
                                             LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id
                                             WHERE p.section_id = %d AND p.subject_id = %d 
                                             ORDER BY p.plan_date DESC LIMIT 1",
                                            $sec2_id,
                                            $sub->id
                                        ));
                                        ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($sub->subject_name); ?></strong></td>
                                            <td><?php echo $latest_plan ? esc_html(($latest_plan->unit_name ?? '') . ' - ' . ($latest_plan->lesson_title ?? '')) : '<i style="color:#999">No data</i>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
    }

    public function render_permissions_page_content()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $roles = array(
            'administrator' => __('Administrator', 'olama-school'),
            'editor' => __('Coordinator/Editor', 'olama-school'),
            'author' => __('Teacher/Author', 'olama-school'),
            'subscriber' => __('Student/Subscriber', 'olama-school'),
        );

        $capabilities = array(
            'olama_view_plans' => __('View Weekly Plans', 'olama-school'),
            'olama_create_plans' => __('Create Own Plans', 'olama-school'),
            'olama_manage_own_plans' => __('Edit Own Plans', 'olama-school'),
            'olama_approve_plans' => __('Approve Weekly Plans', 'olama-school'),
            'olama_manage_academic_structure' => __('Manage Academic Structure', 'olama-school'),
            'olama_manage_curriculum' => __('Manage Curriculum', 'olama-school'),
            'olama_view_reports' => __('View Reports', 'olama-school'),
            'olama_import_export_data' => __('Import/Export Data', 'olama-school'),
            'olama_view_logs' => __('View Logs', 'olama-school'),
        );

        if (isset($_POST['save_permissions'])) {
            check_admin_referer('olama_save_permissions');
            foreach ($roles as $role_name => $role_label) {
                $role = get_role($role_name);
                if (!$role)
                    continue;

                foreach ($capabilities as $cap => $cap_label) {
                    if (isset($_POST['caps'][$role_name][$cap])) {
                        $role->add_cap($cap);
                    } else {
                        $role->remove_cap($cap);
                    }
                }
            }
            echo '<div class="updated"><p>' . __('Permissions updated successfully.', 'olama-school') . '</p></div>';
        }

        ?>
            <div class="olama-permissions-container">
                <form method="post">
                    <?php wp_nonce_field('olama_save_permissions'); ?>
                    <div
                        style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 250px;"><?php _e('Capability', 'olama-school'); ?></th>
                                    <?php foreach ($roles as $label): ?>
                                        <th><?php echo esc_html($label); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($capabilities as $cap => $cap_label): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($cap_label); ?></strong></td>
                                        <?php foreach ($roles as $role_name => $label):
                                            $role = get_role($role_name);
                                            $has_cap = $role ? $role->has_cap($cap) : false;
                                            ?>
                                            <td>
                                                <input type="checkbox"
                                                    name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]"
                                                    <?php checked($has_cap); ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 20px;">
                        <?php submit_button(__('Save All Permissions', 'olama-school'), 'primary', 'save_permissions'); ?>
                    </div>
                </form>
            </div>
            <?php
    }

    /**
     * Render Notifications & Logs Content
     */
    public function render_notifications_page_content()
    {
        global $wpdb;

        // Fetch logs (last 50)
        $logs = $wpdb->get_results("
            SELECT l.*, u.display_name 
            FROM {$wpdb->prefix}olama_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 50
        ");

        ?>
            <div class="olama-logs-container" style="background: #f0f2f5; padding: 20px; border-radius: 12px;">

                <div
                    style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px;">
                    <h2 style="margin-top: 0;"><?php _e('Recent Activities (Audit Log)', 'olama-school'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date/Time', 'olama-school'); ?></th>
                                <th><?php _e('User', 'olama-school'); ?></th>
                                <th><?php _e('Action', 'olama-school'); ?></th>
                                <th><?php _e('Details', 'olama-school'); ?></th>
                                <th><?php _e('IP Address', 'olama-school'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs):
                                foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log->created_at); ?></td>
                                        <td><?php echo esc_html($log->display_name ?: 'System'); ?></td>
                                        <td><span class="badge"
                                                style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-size: 11px;"><?php echo esc_html($log->action); ?></span>
                                        </td>
                                        <td><?php echo esc_html($log->details); ?></td>
                                        <td><?php echo esc_html($log->ip_address); ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="5"><?php _e('No logs found.', 'olama-school'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;"><?php _e('Notification Settings', 'olama-school'); ?></h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('olama_notifications_group');
                        $notif_email = get_option('olama_admin_email', get_option('admin_email'));
                        $enable_notifs = get_option('olama_enable_notifs', 'yes');
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Admin Notification Email', 'olama-school'); ?></th>
                                <td><input type="email" name="olama_admin_email" value="<?php echo esc_attr($notif_email); ?>"
                                        class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable Email Notifications', 'olama-school'); ?></th>
                                <td>
                                    <select name="olama_enable_notifs">
                                        <option value="yes" <?php selected($enable_notifs, 'yes'); ?>>
                                            <?php _e('Yes', 'olama-school'); ?>
                                        </option>
                                        <option value="no" <?php selected($enable_notifs, 'no'); ?>>
                                            <?php _e('No', 'olama-school'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
            <?php
    }

    /**
     * Render Data Management (Import/Export)
     */
    /**
     * Render Data Management Content
     */
    public function render_data_management_page_content()
    {
        if ($message = get_transient('olama_import_message')) {
            echo '<div class="updated"><p>' . esc_html($message) . '</p></div>';
            delete_transient('olama_import_message');
        }
        ?>
            <div class="olama-data-management-container">

                <div class="grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div
                        style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h2 style="margin-top: 0;"><?php _e('Export Data', 'olama-school'); ?></h2>
                        <p><?php _e('Download all weekly plans and schedule data in CSV format.', 'olama-school'); ?></p>
                        <form method="post">
                            <?php wp_nonce_field('olama_export_action'); ?>
                            <input type="hidden" name="olama_export" value="true">
                            <?php submit_button(__('Download CSV Export', 'olama-school'), 'secondary'); ?>
                        </form>
                    </div>

                    <div
                        style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <h2 style="margin-top: 0;"><?php _e('Import Data', 'olama-school'); ?></h2>
                        <p><?php _e('Upload a CSV file to bulk import weekly plans or curriculum units.', 'olama-school'); ?>
                        </p>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('olama_import_action'); ?>
                            <input type="file" name="olama_import_file" accept=".csv" required
                                style="margin-bottom: 15px; display: block;">
                            <?php submit_button(__('Upload & Import', 'olama-school'), 'secondary'); ?>
                        </form>
                    </div>
                </div>
            </div>
            <?php
    }

    // --- Curriculum AJAX Handlers ---

    public function ajax_save_curriculum_unit()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        // Debug: Log what data we received
        $received_data = array(
            'grade_id' => isset($_POST['grade_id']) ? $_POST['grade_id'] : 'NOT SET',
            'subject_id' => isset($_POST['subject_id']) ? $_POST['subject_id'] : 'NOT SET',
            'semester_id' => isset($_POST['semester_id']) ? $_POST['semester_id'] : 'NOT SET',
            'unit_name' => isset($_POST['unit_name']) ? $_POST['unit_name'] : 'NOT SET',
            'unit_number' => isset($_POST['unit_number']) ? $_POST['unit_number'] : 'NOT SET',
        );

        $result = Olama_School_Unit::save_unit($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => is_array($result) ? $result['id'] : $result,
                'message' => __('Unit saved successfully', 'olama-school'),
                'debug_received' => $received_data,
                'save_result' => $result  // Contains id, result, error, query, data
            ));
        }
    }

    public function ajax_get_curriculum_units()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $subject_id = intval($_GET['subject_id']);
        $grade_id = intval($_GET['grade_id']);
        $semester_id = intval($_GET['semester_id']);
        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        wp_send_json_success($units);
    }

    public function ajax_delete_curriculum_unit()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Unit::delete_unit(intval($_POST['id']));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function ajax_save_curriculum_lesson()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        // Debug received data
        $received_data = array(
            'unit_id' => isset($_POST['unit_id']) ? $_POST['unit_id'] : 'NOT SET',
            'lesson_title' => isset($_POST['lesson_title']) ? $_POST['lesson_title'] : 'NOT SET',
            'lesson_number' => isset($_POST['lesson_number']) ? $_POST['lesson_number'] : 'NOT SET',
        );

        $result = Olama_School_Lesson::save_lesson($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => is_array($result) ? $result['id'] : $result,
                'message' => __('Lesson saved successfully', 'olama-school'),
                'debug_received' => $received_data,
                'save_result' => $result
            ));
        }
    }

    public function ajax_get_curriculum_lessons()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $lessons = Olama_School_Lesson::get_lessons(intval($_GET['unit_id']));
        wp_send_json_success($lessons);
    }

    public function ajax_delete_curriculum_lesson()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Lesson::delete_lesson(intval($_POST['id']));

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function ajax_save_curriculum_question()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $result = Olama_School_Question_Bank::save_question($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'id' => $result,
                'message' => __('Question saved successfully', 'olama-school')
            ));
        }
    }

    public function ajax_get_curriculum_questions()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $questions = Olama_School_Question_Bank::get_questions(intval($_GET['lesson_id']));

        // Map fields for compatibility
        $normalized = array_map(function ($q) {
            $q->question_text = $q->question;
            $q->answer_text = $q->answer;
            return $q;
        }, $questions);

        wp_send_json_success($normalized);
    }

    public function ajax_delete_curriculum_question()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $id = intval($_POST['id']);
        if ($id > 0) {
            Olama_School_Question_Bank::delete_question($id);
            wp_send_json_success();
        }
        wp_send_json_error('Invalid ID');
    }

    public function ajax_get_subjects_by_grade()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $grade_id = intval($_GET['grade_id']);
        $subjects = Olama_School_Subject::get_by_grade($grade_id);
        wp_send_json_success($subjects);
    }

    /**
     * AJAX handler for getting scheduled subjects for a day/section
     */
    public function ajax_get_scheduled_subjects()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $section_id = intval($_GET['section_id']);
        $day_name = sanitize_text_field($_GET['day_name']);

        $active_year = Olama_School_Academic::get_active_year();
        $semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : [];
        $semester_id = $semesters[0]->id ?? 0; // Default to first semester of active year

        $subjects = Olama_School_Schedule::get_unique_subjects_for_day($section_id, $day_name, $semester_id);
        wp_send_json_success($subjects);
    }

    public function ajax_delete_plan()
    {
        check_ajax_referer('olama_save_plan', 'nonce');
        $plan_id = intval($_POST['plan_id']);
        if ($plan_id > 0) {
            Olama_School_Plan::delete_plan($plan_id);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    /**
     * AJAX: Get Timeline Data (Units and Lessons)
     */
    public function ajax_get_timeline_data()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $semester_id = intval($_GET['semester_id']);
        $grade_id = intval($_GET['grade_id']);
        $subject_id = intval($_GET['subject_id']);

        if (!$semester_id || !$grade_id || !$subject_id) {
            wp_send_json_error(__('Missing parameters.', 'olama-school'));
        }

        $units = Olama_School_Unit::get_units($subject_id, $grade_id, $semester_id);
        $timeline_data = array();

        foreach ($units as $unit) {
            $lessons = Olama_School_Lesson::get_lessons($unit->id);
            $timeline_data[] = array(
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'unit_name' => $unit->unit_name,
                'start_date' => $unit->start_date,
                'end_date' => $unit->end_date,
                'lessons' => array_map(function ($lesson) {
                    return array(
                        'id' => $lesson->id,
                        'lesson_number' => $lesson->lesson_number,
                        'lesson_title' => $lesson->lesson_title,
                        'periods' => $lesson->periods,
                        'start_date' => $lesson->start_date,
                        'end_date' => $lesson->end_date
                    );
                }, $lessons)
            );
        }

        wp_send_json_success($timeline_data);
    }

    /**
     * AJAX: Save Timeline Dates
     */
    public function ajax_save_timeline_dates()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $data = json_decode(stripslashes($_POST['timeline_data']), true);

        if (!$data || !is_array($data)) {
            wp_send_json_error(__('Invalid data format.', 'olama-school'));
        }

        global $wpdb;
        $units_table = $wpdb->prefix . 'olama_curriculum_units';
        $lessons_table = $wpdb->prefix . 'olama_curriculum_lessons';

        foreach ($data as $unit) {
            // Update Unit
            $wpdb->update(
                $units_table,
                array(
                    'start_date' => !empty($unit['start_date']) ? $unit['start_date'] : null,
                    'end_date' => !empty($unit['end_date']) ? $unit['end_date'] : null
                ),
                array('id' => intval($unit['id']))
            );

            // Update Lessons
            if (!empty($unit['lessons']) && is_array($unit['lessons'])) {
                foreach ($unit['lessons'] as $lesson) {
                    $wpdb->update(
                        $lessons_table,
                        array(
                            'start_date' => !empty($lesson['start_date']) ? $lesson['start_date'] : null,
                            'end_date' => !empty($lesson['end_date']) ? $lesson['end_date'] : null,
                            'periods' => isset($lesson['periods']) ? intval($lesson['periods']) : 1
                        ),
                        array('id' => intval($lesson['id']))
                    );
                }
            }
        }

        wp_send_json_success(__('Timeline dates saved successfully.', 'olama-school'));
    }
    /**
     * Render Plan Load Tab Content
     */
    public function render_plan_load_page_content()
    {
        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['manage_grade']) ? intval($_GET['manage_grade']) : 0;

        $subjects = [];
        if ($selected_grade_id) {
            $subjects = Olama_School_Subject::get_by_grade($selected_grade_id);
        }

        if (isset($_GET['message'])) {
            if ($_GET['message'] === 'plan_load_saved') {
                echo '<div class="updated notice is-dismissible"><p>' . __('Plan Load settings saved successfully.', 'olama-school') . '</p></div>';
            } elseif ($_GET['message'] === 'plan_load_warning') {
                $errors = get_transient('olama_plan_load_errors');
                if ($errors) {
                    echo '<div class="notice notice-warning is-dismissible"><ul>';
                    foreach ($errors as $error) {
                        echo '<li><strong>' . esc_html($error) . '</strong></li>';
                    }
                    echo '</ul><p>' . __('Settings were saved, but some limits were adjusted to respect grade constraints.', 'olama-school') . '</p></div>';
                    delete_transient('olama_plan_load_errors');
                }
            }
        }
        ?>
            <div class="olama-plan-load-container"
                style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <h1 style="margin-top: 0; color: #1e293b; font-size: 24px; font-weight: 700;">
                    <span class="dashicons dashicons-chart-bar"
                        style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1;"></span>
                    <?php _e('Plan Load Management', 'olama-school'); ?>
                </h1>
                <p class="description" style="font-size: 14px; margin-bottom: 30px;">
                    <?php _e('Manage the maximum number of plans allowed per week. Define limits at the grade level or for specific subjects.', 'olama-school'); ?>
                </p>

                <form method="post">
                    <?php wp_nonce_field('olama_save_plan_load', 'olama_plan_load_nonce'); ?>
                    <input type="hidden" name="manage_grade_id" value="<?php echo $selected_grade_id; ?>">

                    <div class="olama-card"
                        style="margin-bottom: 40px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <div style="background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
                            <h3 style="margin: 0; font-size: 16px; color: #334155;">
                                <?php _e('Grades & Sections Limits', 'olama-school'); ?>
                            </h3>
                        </div>
                        <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                            <thead>
                                <tr style="background: #f1f5f9;">
                                    <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Grade Name', 'olama-school'); ?>
                                    </th>
                                    <th style="padding: 12px 20px; font-weight: 700;">
                                        <?php _e('Grade Level', 'olama-school'); ?>
                                    </th>
                                    <th style="padding: 12px 20px; font-weight: 700; width: 180px;">
                                        <?php _e('Max Weekly Plans', 'olama-school'); ?>
                                    </th>
                                    <th style="padding: 12px 20px; font-weight: 700; width: 150px; text-align: center;">
                                        <?php _e('Actions', 'olama-school'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $grade):
                                    $is_selected = ($selected_grade_id === intval($grade->id));
                                    ?>
                                    <tr style="<?php echo $is_selected ? 'background: #eff6ff;' : ''; ?>">
                                        <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">
                                            <?php echo esc_html($grade->grade_name); ?>
                                            <input type="hidden" name="grade_name[<?php echo $grade->id; ?>]"
                                                value="<?php echo esc_attr($grade->grade_name); ?>">
                                            <input type="hidden" name="grade_level[<?php echo $grade->id; ?>]"
                                                value="<?php echo esc_attr($grade->grade_level); ?>">
                                        </td>
                                        <td style="padding: 12px 20px;"><?php echo esc_html($grade->grade_level); ?></td>
                                        <td style="padding: 12px 20px;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <input type="number" name="grade_limit[<?php echo $grade->id; ?>]"
                                                    value="<?php echo intval($grade->max_weekly_plans); ?>" min="0"
                                                    style="width: 70px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 4px 8px;">
                                                <span
                                                    style="font-size: 11px; color: #64748b;"><?php _e('plans', 'olama-school'); ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 20px; text-align: center;">
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=load&manage_grade=' . $grade->id); ?>"
                                                class="button button-small <?php echo $is_selected ? 'button-primary' : ''; ?>"
                                                style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="dashicons dashicons-list-view"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                                <?php _e('Manage Subjects', 'olama-school'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($selected_grade_id):
                        $current_grade = null;
                        foreach ($grades as $g) {
                            if (intval($g->id) === $selected_grade_id) {
                                $current_grade = $g;
                                break;
                            }
                        }
                        ?>
                        <div class="olama-card"
                            style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; animation: slideDown 0.3s ease-out;">
                            <div
                                style="background: #2271b1; padding: 15px 20px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; font-size: 16px; color: #fff;">
                                    <?php printf(__('Subject Limits for %s', 'olama-school'), esc_html($current_grade->grade_name)); ?>
                                </h3>
                                <span
                                    style="font-size: 12px; opacity: 0.9;"><?php _e('Overrides grade-level limit for specific subjects', 'olama-school'); ?></span>
                            </div>
                            <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 20px; font-weight: 700;">
                                            <?php _e('Subject Name', 'olama-school'); ?>
                                        </th>
                                        <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Color', 'olama-school'); ?></th>
                                        <th style="padding: 12px 20px; font-weight: 700; width: 250px;">
                                            <?php _e('Max Weekly Plans', 'olama-school'); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($subjects): ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <tr>
                                                <td style="padding: 12px 20px; font-weight: 600; color: #334155;">
                                                    <?php echo esc_html($subject->subject_name); ?>
                                                </td>
                                                <td style="padding: 12px 20px;">
                                                    <span
                                                        style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo esc_attr($subject->color_code); ?>; margin-right: 5px;"></span>
                                                    <code style="font-size: 11px;"><?php echo esc_html($subject->color_code); ?></code>
                                                </td>
                                                <td style="padding: 12px 20px;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <input type="number" name="subject_limit[<?php echo $subject->id; ?>]"
                                                            value="<?php echo intval($subject->max_weekly_plans); ?>" min="0"
                                                            style="width: 70px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 4px 8px;">
                                                        <span
                                                            style="font-size: 11px; color: #64748b;"><?php _e('plans', 'olama-school'); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" style="padding: 30px; text-align: center; color: #94a3b8;">
                                                <?php _e('No subjects found for this grade.', 'olama-school'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div
                        style="margin-top: 35px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 15px;">
                        <?php if ($selected_grade_id): ?>
                            <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=load'); ?>"
                                class="button button-secondary"><?php _e('Close Subject Limits', 'olama-school'); ?></a>
                        <?php endif; ?>
                        <button type="submit" name="olama_save_plan_load" value="1" class="button button-primary button-large"
                            style="height: 40px; padding: 0 25px;">
                            <span class="dashicons dashicons-saved" style="margin-top: 8px;"></span>
                            <?php _e('Save All Load Settings', 'olama-school'); ?>
                        </button>
                    </div>
                </form>
            </div>
            <style>
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .olama-card table tr:hover td {
                    background: rgba(34, 113, 177, 0.02);
                }
            </style>
            <?php
    }

    /**
     * Calculate progress status based on dates
     */
    private function get_progress_status($plan_date, $start_date, $end_date)
    {
        if (!$start_date || !$end_date)
            return null;

        $plan_ts = strtotime($plan_date);
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);

        if ($plan_ts >= $start_ts && $plan_ts <= $end_ts) {
            return array('label' => __('On-time', 'olama-school'), 'class' => 'status-ontime');
        } elseif ($plan_ts > $end_ts) {
            $days = ceil(($plan_ts - $end_ts) / 86400);
            return array('label' => sprintf(__('Delayed by %d days', 'olama-school'), $days), 'class' => 'status-delayed');
        } else {
            $days = ceil(($start_ts - $plan_ts) / 86400);
            return array('label' => sprintf(__('Bypass by %d days', 'olama-school'), $days), 'class' => 'status-bypass');
        }
    }

    /**
     * Render Curriculum Coverage Tab Content
     */
    public function render_curriculum_coverage_page_content()
    {
        global $wpdb;

        // 1. Get Academic Infrastructure
        $active_year = Olama_School_Academic::get_active_year();
        if (!$active_year) {
            echo '<div class="error"><p>' . __('Please activate an academic year first.', 'olama-school') . '</p></div>';
            return;
        }

        $semesters = Olama_School_Academic::get_semesters($active_year->id);
        if (!$semesters) {
            echo '<div class="error"><p>' . __('Please create semesters for the active year.', 'olama-school') . '</p></div>';
            return;
        }

        // 2. Selection Handling
        $selected_semester_id = isset($_GET['coverage_semester']) ? intval($_GET['coverage_semester']) : (isset($semesters[0]->id) ? intval($semesters[0]->id) : 0);
        $selected_grade_id = isset($_GET['coverage_grade']) ? intval($_GET['coverage_grade']) : 0;

        $sections = $selected_grade_id ? Olama_School_Section::get_by_grade($selected_grade_id) : [];
        $selected_section_id = (isset($_GET['coverage_section']) && $_GET['coverage_section'] != '0') ? intval($_GET['coverage_section']) : (isset($sections[0]->id) ? intval($sections[0]->id) : 0);

        $current_semester = null;
        foreach ($semesters as $sem) {
            if (intval($sem->id) === $selected_semester_id) {
                $current_semester = $sem;
                break;
            }
        }

        // Fallback for current_semester if selection is invalid for active year
        if (!$current_semester && !empty($semesters)) {
            $current_semester = $semesters[0];
            $selected_semester_id = intval($current_semester->id);
        }

        $grades = Olama_School_Grade::get_grades();
        $subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id) : [];

        ?>
            <div class="olama-coverage-container"
                style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <div>
                        <h1 style="margin: 0; color: #1e293b; font-size: 24px; font-weight: 700;">
                            <span class="dashicons dashicons-analytics"
                                style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1;"></span>
                            <?php _e('Homework Curriculum Coverage', 'olama-school'); ?>
                        </h1>
                        <p class="description" style="font-size: 14px; margin-top: 5px;">
                            <?php _e('Track how much of the curriculum is covered by weekly plans and monitor performance trends.', 'olama-school'); ?>
                        </p>
                    </div>

                    <div
                        style="display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 10px 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <?php if ($selected_grade_id && !empty($sections)): ?>
                            <div
                                style="display: flex; align-items: center; gap: 10px; border-right: 1px solid #e2e8f0; padding-right: 15px; margin-right: 5px;">
                                <label style="font-weight: 600; color: #64748b;"><?php _e('Section:', 'olama-school'); ?></label>
                                <select onchange="window.location.href=add_query_arg('coverage_section', this.value)"
                                    style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?php echo $sec->id; ?>" <?php selected($selected_section_id, $sec->id); ?>>
                                            <?php echo esc_html($sec->section_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <label style="font-weight: 600; color: #64748b;"><?php _e('Semester:', 'olama-school'); ?></label>
                        <select onchange="window.location.href=add_query_arg('coverage_semester', this.value)"
                            style="border-radius: 4px; border-color: #cbd5e1; font-weight: 600; color: #1e293b;">
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?php echo $sem->id; ?>" <?php selected($selected_semester_id, $sem->id); ?>>
                                    <?php echo esc_html($sem->semester_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 30px;">
                    <!-- Left Sidebar: Grades -->
                    <div style="width: 250px; flex-shrink: 0;">
                        <h3
                            style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 15px; padding-left: 5px;">
                            <?php _e('Select Grade', 'olama-school'); ?>
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php foreach ($grades as $grade):
                                $is_active = (intval($grade->id) === $selected_grade_id);
                                $url = add_query_arg(array('coverage_grade' => $grade->id, 'coverage_section' => 0));
                                ?>
                                <a href="<?php echo esc_url($url); ?>"
                                    style="padding: 12px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s;
                                  <?php echo $is_active ? 'background: #2271b1; color: #fff; box-shadow: 0 4px 12px rgba(34,113,177,0.2);' : 'background: #f1f5f9; color: #475569;'; ?>">
                                    <?php echo esc_html($grade->grade_name); ?>
                                    <span class="dashicons dashicons-arrow-right-alt2"
                                        style="float: right; font-size: 18px; margin-top: 2px; <?php echo $is_active ? 'opacity: 1;' : 'opacity: 0.3;'; ?>"></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Main Content: Subject Coverage -->
                    <div style="flex-grow: 1;">
                        <?php if (!$selected_grade_id): ?>
                            <div
                                style="height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; color: #94a3b8;">
                                <span class="dashicons dashicons-arrow-left-alt"
                                    style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 15px;"></span>
                                <p style="font-size: 16px; font-weight: 500;">
                                    <?php _e('Please select a grade from the sidebar to view coverage analysis.', 'olama-school'); ?>
                                </p>
                            </div>
                        <?php else:
                            $current_grade = Olama_School_Grade::get_grade($selected_grade_id);
                            ?>
                            <div class="olama-card" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                                <div
                                    style="background: #f8fafc; padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 20px;">
                                        <h3 style="margin: 0; font-size: 18px; color: #1e293b;">
                                            <?php printf(__('Coverage Report: %s', 'olama-school'), esc_html($current_grade->grade_name)); ?>
                                        </h3>
                                    </div>
                                    <div style="font-size: 13px; color: #64748b; font-weight: 500;">
                                        <span class="dashicons dashicons-calendar-alt"
                                            style="font-size: 16px; width: 16px; height: 16px; margin-right: 5px;"></span>
                                        <?php echo esc_html($current_semester->semester_name); ?>
                                        (<?php echo date_i18n(get_option('date_format'), strtotime($current_semester->start_date)); ?>
                                        -
                                        <?php echo date_i18n(get_option('date_format'), strtotime($current_semester->end_date)); ?>)
                                    </div>
                                </div>

                                <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                                    <thead>
                                        <tr style="background: #f1f5f9;">
                                            <th style="padding: 15px 20px; font-weight: 700; color: #475569;">
                                                <?php _e('Subject', 'olama-school'); ?>
                                            </th>
                                            <th style="padding: 15px 20px; font-weight: 700; color: #475569; width: 220px;">
                                                <?php _e('Curriculum Coverage', 'olama-school'); ?>
                                            </th>
                                            <th
                                                style="padding: 15px 20px; font-weight: 700; color: #475569; text-align: center; width: 300px;">
                                                <?php _e('Performance Status', 'olama-school'); ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($subjects): ?>
                                            <?php foreach ($subjects as $subject):
                                                // 1. Get ALL lessons for this subject/semester curriculum
                                                $curriculum_lessons = $wpdb->get_results($wpdb->prepare(
                                                    "SELECT l.id, l.start_date, l.end_date 
                                                     FROM {$wpdb->prefix}olama_curriculum_lessons l 
                                                     JOIN {$wpdb->prefix}olama_curriculum_units u ON l.unit_id = u.id 
                                                     WHERE u.subject_id = %d AND u.grade_id = %d AND u.semester_id = %d",
                                                    $subject->id,
                                                    $selected_grade_id,
                                                    $selected_semester_id
                                                ));

                                                $total_lessons = count($curriculum_lessons);

                                                // 2. Get ALL plans for this section/subject in the semester
                                                $plans = $wpdb->get_results($wpdb->prepare(
                                                    "SELECT p.plan_date, p.lesson_id 
                                                     FROM {$wpdb->prefix}olama_plans p
                                                     WHERE p.subject_id = %d AND p.section_id = %d 
                                                     AND p.plan_date >= %s AND p.plan_date <= %s",
                                                    $subject->id,
                                                    $selected_section_id,
                                                    $current_semester->start_date,
                                                    $current_semester->end_date
                                                ));

                                                // 3. Map plans to lessons
                                                $plans_by_lesson = array();
                                                foreach ($plans as $plan) {
                                                    if (!empty($plan->lesson_id)) {
                                                        $lesson_id = intval($plan->lesson_id);
                                                        if (!isset($plans_by_lesson[$lesson_id])) {
                                                            $plans_by_lesson[$lesson_id] = array();
                                                        }
                                                        $plans_by_lesson[$lesson_id][] = $plan;
                                                    }
                                                }

                                                // 4. Calculate coverage and status based on unique lessons
                                                $covered_lessons_count = 0;
                                                $tallies = ['ontime' => 0, 'delayed' => 0, 'bypass' => 0];

                                                foreach ($curriculum_lessons as $lesson) {
                                                    $lesson_id = intval($lesson->id);

                                                    if (isset($plans_by_lesson[$lesson_id])) {
                                                        $covered_lessons_count++;

                                                        if (!empty($lesson->start_date) && !empty($lesson->end_date)) {
                                                            // Determine best status for this lesson
                                                            $best_status_class = null;

                                                            foreach ($plans_by_lesson[$lesson_id] as $plan) {
                                                                $status = $this->get_progress_status($plan->plan_date, $lesson->start_date, $lesson->end_date);
                                                                if ($status) {
                                                                    $class = $status['class'];
                                                                    // Priority: on-time > delayed > bypass
                                                                    if ($class === 'status-ontime') {
                                                                        $best_status_class = 'status-ontime';
                                                                        break; // on-time is top priority
                                                                    } elseif ($class === 'status-delayed') {
                                                                        $best_status_class = 'status-delayed';
                                                                    } elseif (!$best_status_class && $class === 'status-bypass') {
                                                                        $best_status_class = 'status-bypass';
                                                                    }
                                                                }
                                                            }

                                                            if ($best_status_class === 'status-ontime')
                                                                $tallies['ontime']++;
                                                            elseif ($best_status_class === 'status-delayed')
                                                                $tallies['delayed']++;
                                                            elseif ($best_status_class === 'status-bypass')
                                                                $tallies['bypass']++;
                                                        }
                                                    }
                                                }

                                                $percentage = $total_lessons > 0 ? min(100, round(($covered_lessons_count / $total_lessons) * 100)) : 0;
                                                ?>
                                                <tr>
                                                    <td style="padding: 20px; vertical-align: middle;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <span
                                                                style="width: 4px; height: 24px; border-radius: 2px; background: <?php echo esc_attr($subject->color_code); ?>;"></span>
                                                            <span
                                                                style="font-weight: 600; font-size: 15px; color: #1e293b;"><?php echo esc_html($subject->subject_name); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 20px; vertical-align: middle;">
                                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                                            <div
                                                                style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #64748b;">
                                                                <span><?php printf(__('%d / %d Lessons Covered', 'olama-school'), $covered_lessons_count, $total_lessons); ?></span>
                                                                <span style="color: #2271b1;"><?php echo $percentage; ?>%</span>
                                                            </div>
                                                            <div
                                                                style="width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0;">
                                                                <div
                                                                    style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(90deg, #2271b1, #3b82f6); border-radius: 4px; transition: width 0.6s ease-out;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 20px; vertical-align: middle;">
                                                        <div style="display: flex; justify-content: center; gap: 10px;">
                                                            <div class="coverage-stat status-ontime"
                                                                title="<?php _e('On-time Plans', 'olama-school'); ?>">
                                                                <span class="count"><?php echo $tallies['ontime']; ?></span>
                                                                <span class="label"><?php _e('On-time', 'olama-school'); ?></span>
                                                            </div>
                                                            <div class="coverage-stat status-delayed"
                                                                title="<?php _e('Delayed Plans', 'olama-school'); ?>">
                                                                <span class="count"><?php echo $tallies['delayed']; ?></span>
                                                                <span class="label"><?php _e('Delayed', 'olama-school'); ?></span>
                                                            </div>
                                                            <div class="coverage-stat status-bypass"
                                                                title="<?php _e('Bypass Plans', 'olama-school'); ?>">
                                                                <span class="count"><?php echo $tallies['bypass']; ?></span>
                                                                <span class="label"><?php _e('Bypass', 'olama-school'); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" style="padding: 40px; text-align: center; color: #94a3b8;">
                                                    <span class="dashicons dashicons-warning"
                                                        style="font-size: 30px; width: 30px; height: 30px; margin-bottom: 10px;"></span>
                                                    <p style="font-size: 15px;">
                                                        <?php _e('No subjects found for this grade.', 'olama-school'); ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>         if (typeof add_query_a            
                                            rg !== 'function') { function add_query_arg(key, value) { var url = new URL(window.location.href); url.searchParams.set(key, value); return url.href; } }
            </script>

            <style>
                .coverage-stat {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    width: 80px;
                    padding: 8px 5px;
                    border-radius: 8px;
                    border: 1px solid rgba(0, 0, 0, 0.05);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
                }

                .coverage-stat .count {
                    font-size: 18px;
                    font-weight: 700;
                    line-height: 1.2;
                }

                .coverage-stat .label {
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.02em;
                    opacity: 0.9;
                }

                .status-ontime {
                    background: #ecfdf5;
                    color: #065f46;
                    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.1);
                }

                .status-delayed {
                    background: #fef2f2;
                    color: #991b1b;
                    box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.1);
                }

                .status-bypass {
                    background: #eff6ff;
                    color: #1e40af;
                    box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.1);
                }
            </style>
            <?php
    }

    /**
     * Bulk Approve Plans for a section and week
     */
    public function ajax_bulk_approve_plans()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $week_start = isset($_POST['week_start']) ? sanitize_text_field($_POST['week_start']) : '';

        if (!$section_id || !$week_start) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));

        // We need a custom query because $wpdb->update doesn't support complex WHERE (BETWEEN) easily
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}olama_plans SET status = 'published' 
             WHERE section_id = %d AND plan_date >= %s AND plan_date <= %s",
            $section_id,
            $week_start,
            $week_end
        ));

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Database error');
        }
    }

    /**
     * Render Teacher Assignments Tab Content
     */
    public function render_teacher_assignments_page_content()
    {
        $teachers = Olama_School_Teacher::get_teachers();
        $grades = Olama_School_Grade::get_grades();
        ?>
            <div class="olama-assignment-wrap">
                <div class="assignment-header-info">
                    <h2><?php _e('Assign Teachers to Subjects', 'olama-school'); ?></h2>
                    <p><?php _e('Manage subject assignments by selecting a teacher, then narrowing down by grade and section.', 'olama-school'); ?>
                    </p>
                </div>

                <div class="assignment-interface-grid">
                    <!-- Step 1: Teachers -->
                    <div class="assignment-column" id="teacher-col">
                        <div class="column-header">
                            <span class="dashicons dashicons-businessman"></span>
                            <h3><?php _e('1. Teachers', 'olama-school'); ?></h3>
                        </div>
                        <div class="assignment-list" id="teachers-list">
                            <?php foreach ($teachers as $teacher): ?>
                                <div class="assignment-item teacher-item" data-id="<?php echo $teacher->ID; ?>">
                                    <div class="item-main">
                                        <span class="item-title"><?php echo esc_html($teacher->display_name); ?></span>
                                        <span
                                            class="item-sub"><?php echo esc_html($teacher->employee_id ? 'Employee ID: ' . $teacher->employee_id : ''); ?></span>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 2: Grades -->
                    <div class="assignment-column disabled-col" id="grade-col">
                        <div class="column-header">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <h3><?php _e('2. Grades', 'olama-school'); ?></h3>
                        </div>
                        <div class="assignment-list" id="grades-list">
                            <?php foreach ($grades as $grade): ?>
                                <div class="assignment-item grade-item" data-id="<?php echo $grade->id; ?>">
                                    <span class="item-title"><?php echo esc_html($grade->grade_name); ?></span>
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 3: Sections -->
                    <div class="assignment-column disabled-col" id="section-col">
                        <div class="column-header">
                            <span class="dashicons dashicons-groups"></span>
                            <h3><?php _e('3. Sections', 'olama-school'); ?></h3>
                        </div>
                        <div class="assignment-list" id="sections-list">
                            <div class="select-hint">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                <p><?php _e('Select Grade first', 'olama-school'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Subjects -->
                    <div class="assignment-column disabled-col" id="subject-col">
                        <div class="column-header">
                            <span class="dashicons dashicons-book"></span>
                            <h3><?php _e('4. Subjects', 'olama-school'); ?></h3>
                        </div>
                        <div class="assignment-list" id="subjects-list">
                            <div class="select-hint">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                                <p><?php _e('Select Section first', 'olama-school'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
    }

    /**
     * AJAX: Get sections by grade
     */
    public function ajax_get_sections_by_grade()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $grade_id = intval($_POST['grade_id']);
        if (!$grade_id) {
            wp_send_json_error('Invalid Grade ID');
        }
        $sections = Olama_School_Section::get_by_grade($grade_id);
        wp_send_json_success($sections);
    }

    /**
     * AJAX: Get all assignments for a teacher (summary)
     */
    public function ajax_get_teacher_summary()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');
        $teacher_id = intval($_POST['teacher_id']);
        if (!$teacher_id) {
            wp_send_json_error('Invalid Teacher ID');
        }
        $assignments = Olama_School_Teacher::get_all_assignments($teacher_id);
        wp_send_json_success($assignments);
    }

    /**
     * AJAX: Get teacher assignments for a section
     */
    public function ajax_get_teacher_assignments()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $teacher_id = intval($_POST['teacher_id']);
        $section_id = intval($_POST['section_id']);
        $grade_id = intval($_POST['grade_id']);

        if (!$teacher_id || !$section_id || !$grade_id) {
            wp_send_json_error('Invalid parameters');
        }

        $assigned_subjects = Olama_School_Teacher::get_assigned_subjects($teacher_id, $section_id);
        $all_grade_subjects = Olama_School_Subject::get_by_grade($grade_id);

        wp_send_json_success(array(
            'assigned' => $assigned_subjects,
            'all' => $all_grade_subjects
        ));
    }

    /**
     * AJAX: Toggle teacher assignment
     */
    public function ajax_toggle_teacher_assignment()
    {
        check_ajax_referer('olama_curriculum_nonce', 'nonce');

        $teacher_id = intval($_POST['teacher_id']);
        $section_id = intval($_POST['section_id']);
        $subject_id = intval($_POST['subject_id']);
        $grade_id = intval($_POST['grade_id']);

        if (!$teacher_id || !$section_id || !$subject_id || !$grade_id) {
            wp_send_json_error('Invalid parameters');
        }

        $result = Olama_School_Teacher::toggle_assignment($teacher_id, $section_id, $subject_id, $grade_id);

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Database error');
        }
    }
}
