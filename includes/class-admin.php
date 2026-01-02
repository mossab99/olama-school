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
        add_action('admin_init', array($this, 'handle_office_hours_save'));
        add_action('admin_init', array($this, 'handle_exam_save'));
        add_action('wp_ajax_olama_save_exam', array($this, 'ajax_save_exam'));
        add_action('wp_ajax_olama_get_semesters', array($this, 'ajax_get_semesters'));
        add_action('wp_ajax_olama_get_subjects', array($this, 'ajax_get_subjects'));

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
        static $already_checked = false;
        if ($already_checked) {
            return;
        }
        $already_checked = true;

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

        // Handle Subjects Export
        if (isset($_POST['olama_export_subjects']) && $_POST['olama_export_subjects'] === 'true') {
            Olama_School_Exporter::export_subjects_csv();
        }

        // Handle Grade/Section Export
        if (isset($_POST['olama_export_grades']) && $_POST['olama_export_grades'] === 'true') {
            Olama_School_Exporter::export_grades_sections_csv();
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
            } elseif ($type === 'subjects') {
                Olama_School_Importer::import_subjects_csv();
            } elseif ($type === 'grades') {
                Olama_School_Importer::import_grades_sections_csv();
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
     * Handle Exam Save
     */
    public function handle_exam_save()
    {
        if (wp_doing_ajax()) {
            return;
        }

        if (isset($_POST['olama_save_exam']) && check_admin_referer('olama_save_exam', 'olama_exam_nonce_field')) {
            $result = Olama_School_Exam::save_exam($_POST);

            $redirect_url = admin_url('admin.php?page=olama-school-academic&tab=exam_schedule');
            $redirect_url = add_query_arg(array(
                'academic_year_id' => intval($_POST['academic_year_id']),
                'semester_id' => intval($_POST['semester_id']),
                'grade_id' => intval($_POST['grade_id']),
                'subject_id' => intval($_POST['subject_id']),
                'message' => is_wp_error($result) ? 'error' : 'exam_saved'
            ), $redirect_url);

            wp_redirect($redirect_url);
            exit;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'delete_exam' && isset($_GET['exam_id'])) {
            $exam_id = intval($_GET['exam_id']);
            if (check_admin_referer('olama_delete_exam_' . $exam_id)) {
                Olama_School_Exam::delete_exam($exam_id);
                $redirect_url = remove_query_arg(array('action', 'exam_id', '_wpnonce'), wp_get_referer());
                $redirect_url = add_query_arg('message', 'exam_deleted', $redirect_url);
                wp_redirect($redirect_url);
                exit;
            }
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
        // Only load on plugin pages
        if (strpos($hook, 'olama-school') === false) {
            return;
        }

        wp_enqueue_style('olama-admin-style', OLAMA_SCHOOL_URL . 'assets/css/admin.css', array(), OLAMA_SCHOOL_VERSION);

        if (Olama_School_Helpers::is_arabic()) {
            wp_enqueue_style('olama-admin-rtl', OLAMA_SCHOOL_URL . 'assets/css/admin-rtl.css', array('olama-admin-style'), OLAMA_SCHOOL_VERSION);
        }

        wp_enqueue_script('olama-admin-script', OLAMA_SCHOOL_URL . 'assets/js/admin.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);

        $page = $_GET['page'] ?? '';

        if ($page === 'olama-school-plans') {
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
                    'selectUnit' => Olama_School_Helpers::translate('Select Unit'),
                    'noUnits' => Olama_School_Helpers::translate('No units found.'),
                    'selectLesson' => Olama_School_Helpers::translate('Select Lesson'),
                    'noLessons' => Olama_School_Helpers::translate('No lessons found.'),
                    'noQuestions' => Olama_School_Helpers::translate('No questions found for this lesson.'),
                    'currentStatus' => Olama_School_Helpers::translate('Current Status'),
                    'published' => Olama_School_Helpers::translate('Published'),
                    'draft' => Olama_School_Helpers::translate('Draft'),
                    'revertToDraft' => Olama_School_Helpers::translate('Revert to Draft'),
                    'saveAsDraft' => Olama_School_Helpers::translate('Save as Draft'),
                    'updatePlan' => Olama_School_Helpers::translate('Update Plan'),
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
                    'selectSubject' => Olama_School_Helpers::translate('Select Subject'),
                    'noUnits' => Olama_School_Helpers::translate('No units found for this subject.'),
                    'noLessons' => Olama_School_Helpers::translate('No lessons found for this unit.'),
                    'noQuestions' => Olama_School_Helpers::translate('No questions found for this lesson.'),
                    'edit' => Olama_School_Helpers::translate('Edit'),
                    'delete' => Olama_School_Helpers::translate('Delete'),
                    'confirmDelete' => Olama_School_Helpers::translate('Are you sure you want to delete this item?'),
                    'unitNumberRequired' => Olama_School_Helpers::translate('Unit number is required'),
                    'unitNameRequired' => Olama_School_Helpers::translate('Unit name is required'),
                    'unitExists' => Olama_School_Helpers::translate('Unit # already exists.'),
                    'errorDeletingUnit' => Olama_School_Helpers::translate('Error deleting unit'),
                    'errorLoadingLessons' => Olama_School_Helpers::translate('Error loading lessons.'),
                    'errorConnection' => Olama_School_Helpers::translate('Error connecting to server.'),
                    'periodsLabel' => Olama_School_Helpers::translate('(%d periods)'),
                    'noTitle' => Olama_School_Helpers::translate('(No Title)'),
                    'lessonNumberRequired' => Olama_School_Helpers::translate('Lesson number is required'),
                    'lessonTitleRequired' => Olama_School_Helpers::translate('Lesson title is required'),
                    'noUnitSelected' => Olama_School_Helpers::translate('No unit selected'),
                    'lessonExists' => Olama_School_Helpers::translate('Lesson # already exists in this unit.'),
                    'errorSavingLesson' => Olama_School_Helpers::translate('Error saving lesson'),
                    'questionNumberRequired' => Olama_School_Helpers::translate('Question number is required'),
                    'questionTextRequired' => Olama_School_Helpers::translate('Question text is required'),
                    'questionExists' => Olama_School_Helpers::translate('Question # already exists in this lesson.'),
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
                    'selectSubject' => Olama_School_Helpers::translate('Select Subject'),
                    'loading' => Olama_School_Helpers::translate('Loading...'),
                    'saving' => Olama_School_Helpers::translate('Saving...'),
                    'error' => Olama_School_Helpers::translate('An error occurred.'),
                    'dateInvalid' => Olama_School_Helpers::translate('Start date cannot be after end date.'),
                    'outsideSemester' => Olama_School_Helpers::translate('Dates must be within the semester range.'),
                    'unitsOverlap' => Olama_School_Helpers::translate('Unit dates cannot overlap.'),
                    'lessonOutsideUnit' => Olama_School_Helpers::translate('Lesson dates must be within unit dates.'),
                    'confirmClear' => Olama_School_Helpers::translate('Are you sure you want to clear all dates? This will remove all start and end dates for the current view.'),
                    'noUnitsFound' => Olama_School_Helpers::translate('No units found for this selection.'),
                    'unit' => Olama_School_Helpers::translate('Unit'),
                    'unitStart' => Olama_School_Helpers::translate('Unit Start'),
                    'unitEnd' => Olama_School_Helpers::translate('Unit End'),
                    'lessonTitle' => Olama_School_Helpers::translate('Lesson Title'),
                    'periods' => Olama_School_Helpers::translate('Periods'),
                    'startDate' => Olama_School_Helpers::translate('Start Date'),
                    'endDate' => Olama_School_Helpers::translate('End Date'),
                    'fixErrors' => Olama_School_Helpers::translate('Please fix validation errors before saving.'),
                    'loadTimeline' => Olama_School_Helpers::translate('Load Timeline'),
                    'saveAllDates' => Olama_School_Helpers::translate('Save All Dates'),
                )
            ));
        }

        if (isset($_GET['page']) && $_GET['page'] === 'olama-school-plans') {
            wp_enqueue_script('olama-plan-list-script', OLAMA_SCHOOL_URL . 'assets/js/plan-list.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);
            wp_localize_script('olama-plan-list-script', 'olamaPlanList', array(
                'i18n' => array(
                    'details' => Olama_School_Helpers::translate('Plan Details'),
                    'subject' => Olama_School_Helpers::translate('Subject'),
                    'unit' => Olama_School_Helpers::translate('Unit'),
                    'lesson' => Olama_School_Helpers::translate('Lesson'),
                    'customTopic' => Olama_School_Helpers::translate('Topic'),
                    'homework' => Olama_School_Helpers::translate('Homework'),
                    'homeworkSB' => Olama_School_Helpers::translate('Homework (Student Book)'),
                    'homeworkEB' => Olama_School_Helpers::translate('Homework (Exercise Book)'),
                    'homeworkNB' => Olama_School_Helpers::translate('Homework (Notebook)'),
                    'homeworkWS' => Olama_School_Helpers::translate('Homework (Worksheet)'),
                    'teacherNotes' => Olama_School_Helpers::translate('Teacher Notes'),
                    'status' => Olama_School_Helpers::translate('Status'),
                    'draft' => Olama_School_Helpers::translate('Draft'),
                    'published' => Olama_School_Helpers::translate('Published'),
                    'noDetails' => Olama_School_Helpers::translate('Click on a plan to see details.'),
                    'confirmBulkApprove' => Olama_School_Helpers::translate('Are you sure you want to approve (publish) all plans for this week and section?'),
                    'bulkApproveSuccess' => Olama_School_Helpers::translate('All plans have been approved successfully.'),
                    'onTime' => Olama_School_Helpers::translate('On-time'),
                    'delayedBy' => Olama_School_Helpers::translate('Delayed by %d days'),
                    'bypassBy' => Olama_School_Helpers::translate('Bypass by %d days'),
                    'loading' => Olama_School_Helpers::translate('Loading...'),
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
                <a href="?page=olama-school-academic&tab=exam_schedule"
                    class="nav-tab <?php echo $active_tab === 'exam_schedule' ? 'nav-tab-active' : ''; ?>"><?php _e('Exam Schedule', 'olama-school'); ?></a>
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
                    case 'exam_schedule':
                        $this->render_exam_schedule_content();
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-calendar.php';
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

        // Display import messages
        if ($import_message = get_transient('olama_import_message')) {
            echo '<div class="updated"><p>' . esc_html($import_message) . '</p></div>';
            delete_transient('olama_import_message');
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-grades.php';
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

        // Display import messages
        if ($import_message = get_transient('olama_import_message')) {
            echo '<div class="updated"><p>' . esc_html($import_message) . '</p></div>';
            delete_transient('olama_import_message');
        }
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-subjects.php';
    }

    /**
     * Render Exam Schedule Tab Content
     */
    public function render_exam_schedule_content()
    {
        $years = Olama_School_Academic::get_years();
        $active_year = Olama_School_Academic::get_active_year();

        $selected_year_id = isset($_GET['academic_year_id']) ? intval($_GET['academic_year_id']) : ($active_year ? $active_year->id : 0);
        $semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
        $selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : (!empty($semesters) ? $semesters[0]->id : 0);

        $grades = Olama_School_Grade::get_grades();
        $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (!empty($grades) ? $grades[0]->id : 0);

        $subjects = $selected_grade_id ? Olama_School_Subject::get_subjects_by_grade($selected_grade_id) : array();
        $selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/exam-schedule.php';
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

        // Only load data for the active tab to improve performance
        $grades = array();
        $sections = array();
        $students = array();
        $teachers = array();
        $admin_users = array();

        if ($active_tab === 'students') {
            $grades = Olama_School_Grade::get_grades();
            $sections = Olama_School_Section::get_sections();
            $students = Olama_School_Student::get_students();
        } elseif ($active_tab === 'teachers') {
            $teachers = Olama_School_Teacher::get_teachers();
        }
        // Permissions and logs tabs don't need user data

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/admin-users.php';
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/shortcode-generator.php';
    }

    /**
     * Render Curriculum Page Content
     */
    public function render_curriculum_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-main.php';
    }

    /**
     * Render Curriculum Timeline Page Content
     */
    public function render_timeline_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/curriculum-timeline.php';
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
                    'office_hours' => __('Office Hours', 'olama-school'),
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
                <?php elseif ($active_tab === 'office_hours'): ?>
                    <?php $this->render_teacher_office_hours_page_content(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle Teacher Office Hours Save
     */
    public function handle_office_hours_save()
    {
        if (isset($_POST['olama_save_office_hours']) && check_admin_referer('olama_save_office_hours', 'olama_office_hours_nonce')) {
            $teacher_id = intval($_POST['teacher_id']);
            $slots = $_POST['slots'] ?? [];

            Olama_School_Teacher::save_office_hours($teacher_id, $slots);

            $url = add_query_arg(array(
                'tab' => 'office_hours',
                'teacher_id' => $teacher_id,
                'message' => 'office_hours_saved'
            ), admin_url('admin.php?page=olama-school-plans'));

            wp_redirect($url);
            exit;
        }
    }

    /**
     * Render Teacher Office Hours Page Content
     */
    public function render_teacher_office_hours_page_content()
    {
        $teachers = Olama_School_Teacher::get_teachers();
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Default to current user if they are a teacher, unless an ID is specified and user is admin
        $selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

        if (!$selected_teacher_id) {
            $user = wp_get_current_user();
            if (in_array('teacher', (array) $user->roles)) {
                $selected_teacher_id = $current_user_id;
            } elseif (!empty($teachers)) {
                $selected_teacher_id = $teachers[0]->ID;
            }
        }

        // Security check: Teachers can only edit their own office hours, Admins can edit anyone's
        if (!$is_admin && $selected_teacher_id !== $current_user_id) {
            $selected_teacher_id = $current_user_id;
        }

        include OLAMA_SCHOOL_PATH . 'includes/admin-views/teacher-office-hours.php';
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-creation.php';
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-list.php';
    }


    /**
     * Render Weekly Schedule (Form 14)
     */
    public function render_schedule_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-schedule.php';
    }


    /**
     * Render Dashboard (Form 18)
     */
    public function render_dashboard_page()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/dashboard.php';
    }

    /**
     * Render Reports (Forms 16, 19)
     */
    public function render_reports_page()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/reports.php';
    }

    /**
     * Render Plan Comparison Content
     */
    public function render_comparison_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-comparison.php';
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
                <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
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
                                                name="caps[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]" <?php checked($has_cap); ?>>
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-data.php';
    }

    /**
     * Render Plan Load Tab Content
     */
    public function render_plan_load_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-load.php';
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
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/weekly-plan-coverage.php';
    }
    /**
     * Render Teacher Assignments Tab Content
     */
    public function render_teacher_assignments_page_content()
    {
        include OLAMA_SCHOOL_PATH . 'includes/admin-views/academic-assignments.php';
    }

    /**
     * AJAX Save Exam
     */
    public function ajax_save_exam()
    {
        // Check nonce from either the appended 'nonce' param or the form field
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_POST['olama_exam_nonce_field']) ? $_POST['olama_exam_nonce_field'] : '');

        if (empty($nonce) || !wp_verify_nonce($nonce, 'olama_save_exam')) {
            wp_send_json_error(__('Security check failed.', 'olama-school'));
        }

        if (!current_user_can('manage_options') && !current_user_can('olama_manage_academic')) {
            wp_send_json_error(__('Permission denied.', 'olama-school'));
        }

        if (empty($_POST['academic_year_id']) || empty($_POST['semester_id']) || empty($_POST['grade_id']) || empty($_POST['subject_id'])) {
            wp_send_json_error(__('Required fields are missing.', 'olama-school'));
        }

        $result = Olama_School_Exam::save_exam($_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } elseif (!$result) {
            wp_send_json_error(__('Database error: Could not save exam.', 'olama-school'));
        } else {
            wp_send_json_success(array('message' => Olama_School_Helpers::translate('Exam saved successfully.')));
        }
    }

    /**
     * AJAX Get Semesters for Year
     */
    public function ajax_get_semesters()
    {
        $year_id = intval($_GET['year_id']);
        $semesters = Olama_School_Academic::get_semesters($year_id);
        wp_send_json_success($semesters);
    }

    /**
     * AJAX Get Subjects for Grade
     */
    public function ajax_get_subjects()
    {
        $grade_id = intval($_GET['grade_id']);
        $subjects = Olama_School_Subject::get_subjects_by_grade($grade_id);
        wp_send_json_success($subjects);
    }
}
