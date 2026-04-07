<?php
/**
 * Shortcodes Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Shortcodes
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('olama_weekly_plan', array($this, 'render_weekly_plan_shortcode'));
        add_shortcode('olama_weekly_schedule', array($this, 'render_weekly_schedule_shortcode'));
        add_shortcode('olama_teachers_office_hours', array($this, 'render_teachers_office_hours_shortcode'));
        add_shortcode('olama_stationary', array($this, 'render_stationary_shortcode'));
        add_shortcode('olama_exam_report', array($this, 'render_exam_report_shortcode'));
        add_shortcode('olama_online_exams_schedule', array($this, 'render_exam_report_shortcode'));
        add_shortcode('olama_attendance', array($this, 'render_attendance_shortcode'));
        add_shortcode('olama_family_performance', array($this, 'render_family_performance_shortcode'));
        add_shortcode('olama_logged_teacher_schedule', array($this, 'render_logged_teacher_schedule_shortcode'));
        add_shortcode('olama_logged_user_shifts', array($this, 'render_logged_user_shifts_shortcode'));
        add_shortcode('force_login', array($this, 'render_force_login_shortcode'));
        add_shortcode('olama_family_gateway', array($this, 'render_family_gateway_shortcode'));
        add_shortcode('olama_online_exams', array($this, 'render_online_exams_shortcode'));
        add_shortcode('olama_supervisor_visits', array($this, 'render_supervisor_visit_schedule_shortcode'));
        add_shortcode('olama_family_number_lookup', array($this, 'render_family_number_lookup_shortcode'));
        add_shortcode('olama_cleaning_form', array($this, 'render_cleaning_form_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }

    /**
     * Enqueue Shortcode Assets
     */
    public function enqueue_shortcode_assets()
    {
        wp_enqueue_style('olama-google-fonts', 'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Inter:wght@400;600;700&display=swap', array(), null);
        wp_enqueue_style('olama-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons', array(), null);
        wp_enqueue_style('olama-shortcodes', OLAMA_SCHOOL_URL . 'assets/css/shortcodes.css', array(), OLAMA_SCHOOL_VERSION);
        wp_enqueue_script('olama-shortcodes-js', OLAMA_SCHOOL_URL . 'assets/js/shortcodes.js', array('jquery'), OLAMA_SCHOOL_VERSION, true);

        wp_localize_script('olama-shortcodes-js', 'olama_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('olama_admin_nonce'),
        ));
    }

    /**
     * Shortcode: [force_login]
     * Forces guest users to log in.
     */
    public function render_force_login_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }
        return '';
    }


    /**
     * Shortcode: [olama_teachers_office_hours]
     */
    public function render_teachers_office_hours_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'year' => '',
            'semester' => '',
            'grade' => '',
            'section' => '',
            'random' => 'false'
        ), $atts, 'olama_teachers_office_hours');

        $academic_year_id = intval($atts['year']);
        if (!$academic_year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_id = $active_year ? $active_year->id : 0;
        }

        $year_obj = Olama_School_Academic::get_year($academic_year_id);
        $year_name = $year_obj ? $year_obj->year_name : '2025 - 2026';

        $semester_id = $atts['semester'];
        if ($semester_id === 'active' || empty($semester_id)) {
            $active_semester = Olama_School_Academic::get_active_semester($academic_year_id);
            $semester_id = $active_semester ? $active_semester->id : 0;
        } else {
            $semester_id = intval($semester_id);
        }

        $semester_obj = Olama_School_Academic::get_semester($semester_id);
        $semester_name = $semester_obj ? $semester_obj->semester_name : Olama_School_Helpers::translate('Semester');

        $grade_id = intval($atts['grade']);
        $section_id = intval($atts['section']);

        // Fetch teachers based on filters
        $teachers = array();
        if ($section_id) {
            $teachers = Olama_School_Teacher::get_teachers_for_section($section_id, $academic_year_id);
        } elseif ($grade_id) {
            $teachers = Olama_School_Teacher::get_teachers_for_grade($grade_id, $academic_year_id);
        } else {
            $teachers = Olama_School_Teacher::get_teachers();
        }

        if (empty($teachers)) {
            return '<div class="olama-no-plans">' . Olama_School_Helpers::translate('No teachers found.') . '</div>';
        }

        if ($atts['random'] === 'true') {
            shuffle($teachers);
        }

        ob_start();
        ?>
        <div class="olama-premium-oh-container dark-mode" dir="rtl">
            <header class="oh-premium-hero">
                <div class="hero-content">
                    <div class="hero-text">
                        <div class="portal-badge">
                            <?php echo Olama_School_Helpers::translate('Academic Portal'); ?>
                        </div>
                        <h1 class="hero-title">
                            <?php echo Olama_School_Helpers::translate('Office Hours'); ?>
                        </h1>
                        <h2 class="hero-subtitle english-font">Teacher Office Hours</h2>
                    </div>
                    <div class="hero-info-card">
                        <div class="info-label">
                            <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                        </div>
                        <div class="info-value english-font">
                            <?php echo esc_html($year_name); ?>
                        </div>
                        <div class="info-semester">
                            <?php echo esc_html($semester_name); ?>
                        </div>
                    </div>
                </div>
                <div class="hero-blob blob-1"></div>
                <div class="hero-blob blob-2"></div>
            </header>

            <div class="oh-premium-controls">
                <div class="search-wrapper">
                    <input type="text" id="oh-teacher-search"
                        placeholder="<?php echo Olama_School_Helpers::translate('Search by teacher name...'); ?>">
                </div>
                <div class="search-wrapper">
                    <input type="text" id="oh-subject-search"
                        placeholder="<?php echo Olama_School_Helpers::translate('Search by subject name...'); ?>">
                </div>
            </div>

            <div class="oh-premium-grid" id="oh-teachers-list">
                <?php foreach ($teachers as $teacher):
                    $office_hours = Olama_School_Teacher::get_office_hours($teacher->ID, $academic_year_id, $semester_id);
                    if (empty($office_hours))
                        continue;

                    $assignments = Olama_School_Teacher::get_teacher_academic_assignments($teacher->ID, $academic_year_id);
                    $subject_names = wp_list_pluck($assignments, 'subject_name');
                    $subject_str = implode(', ', $subject_names);
                    ?>
                    <div class="oh-premium-card" data-name="<?php echo esc_attr(strtolower($teacher->display_name)); ?>"
                        data-subjects="<?php echo esc_attr(strtolower($subject_str)); ?>">
                        <div class="card-header">
                            <div class="avatar-container">
                                <?php echo get_avatar($teacher->ID, 80); ?>
                                <div class="status-indicator"></div>
                            </div>
                            <div class="teacher-meta">
                                <h3 class="teacher-name">
                                    <?php echo esc_html($teacher->display_name); ?>
                                </h3>
                                <p class="teacher-dept">
                                    <?php echo Olama_School_Helpers::translate('Teacher'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="slots-section">
                            <h4 class="slots-header">
                                <span class="material-icons">schedule</span>
                                <?php echo Olama_School_Helpers::translate('Available Times'); ?>
                            </h4>
                            <div class="slots-list">
                                <?php foreach ($office_hours as $oh): ?>
                                    <div class="slot-item">
                                        <span class="day-name">
                                            <?php echo Olama_School_Helpers::translate($oh->day_name); ?>
                                        </span>
                                        <span class="time-value">
                                            <?php echo esc_html($oh->available_time); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="tags-container">
                            <?php foreach ($assignments as $asgn):
                                $tag_color = !empty($asgn->color_code) ? $asgn->color_code : '#2563eb';
                                ?>
                                <span class="assignment-tag"
                                    style="--tag-bg: <?php echo esc_attr($tag_color); ?>20; --tag-color: <?php echo esc_attr($tag_color); ?>;">
                                    <?php echo esc_html("{$asgn->grade_name} - {$asgn->section_name} - {$asgn->subject_name}"); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="oh-no-results" class="no-results" style="display: none;">
                <span class="material-icons">search_off</span>
                <p>
                    <?php echo Olama_School_Helpers::translate('No teachers match your search.'); ?>
                </p>
            </div>
        </div>

        <button class="oh-theme-toggle"
            onclick="document.querySelector('.olama-premium-oh-container').classList.toggle('dark-mode')">
            <span class="material-icons">contrast</span>
        </button>

        <script>
            jQuery(document).ready(function ($) {
                function filterTeachers() {
                    var nameTerm = $('#oh-teacher-search').val().toLowerCase();
                    var subjectTerm = $('#oh-subject-search').val().toLowerCase();
                    var visibleCount = 0;

                    $('#oh-teachers-list .oh-premium-card').each(function () {
                        var name = $(this).data('name');
                        var subjects = $(this).data('subjects');

                        var nameMatch = !nameTerm || name.includes(nameTerm);
                        var subjectMatch = !subjectTerm || subjects.includes(subjectTerm);

                        if (nameMatch && subjectMatch) {
                            $(this).fadeIn(300).css('display', 'flex');
                            visibleCount++;
                        } else {
                            $(this).hide();
                        }
                    });

                    if (visibleCount === 0) {
                        $('#oh-no-results').show();
                    } else {
                        $('#oh-no-results').hide();
                    }
                }

                $('#oh-teacher-search, #oh-subject-search').on('input', filterTeachers);
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [olama_weekly_plan]
     * Attributes: semester, grade, section, week
     */
    public function render_weekly_plan_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'semester' => '',
            'grade' => '',
            'section' => '',
            'week' => '',
        ), $atts, 'olama_weekly_plan');

        $section_id = intval($atts['section']);
        if (!$section_id && isset($_GET['section_id'])) {
            $section_id = intval($_GET['section_id']);
        }

        if (!$section_id) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please specify a valid section ID in the shortcode.') . '</div>';
        }

        // Resolve Semester ID for header/filtering
        $semester_id = $atts['semester'];
        if ($semester_id === 'active' || empty($semester_id)) {
            $active_year = Olama_School_Academic::get_active_year();
            $active_sem = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;
            $semester_id = $active_sem ? $active_sem->id : 0;
        } else {
            $semester_id = intval($semester_id);
        }

        $week_start = $atts['week'];
        if (!$week_start) {
            // Default to current/upcoming week start based on Saturday-switch logic
            $week_start = Olama_School_Helpers::get_active_week_start();
        } elseif ($week_start === 'previous') {
            $week_start = Olama_School_Helpers::get_previous_week_start();
        }

        $week_range = Olama_School_Helpers::get_week_range($week_start);
        $week_end = $week_range['end'];
        $all_plans = Olama_School_Plan::get_plans($section_id, $week_start, $week_end);

        $is_admin = Olama_School_Permissions::can('olama_view_reports_summary');

        // Filter plans based on status and user role
        $plans = array_filter($all_plans, function ($p) use ($is_admin) {
            if ($is_admin) {
                return true; // Admins can see everything
            }
            return $p->status === 'approved' || $p->status === 'published';
        });

        if (empty($all_plans)) {
            return '<div class="olama-no-plans" style="padding: 30px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No weekly plans found for the selected week.') .
                '</div>';
        }

        if (empty($plans)) {
            return '<div class="olama-no-plans" style="padding: 30px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; color: #92400e; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No approved plans found for this week, but drafts exist.') .
                '</div>';
        }

        // Group plans by date
        $grouped_plans = array();
        foreach ($plans as $plan) {
            $grouped_plans[$plan->plan_date][] = $plan;
        }

        // Get Section/Grade info for the header
        $section = Olama_School_Section::get_section($section_id);
        $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;

        $semester_name = '';
        if ($semester_id > 0) {
            global $wpdb;
            $semester_name = $wpdb->get_var($wpdb->prepare("SELECT semester_name FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
        }

        // Helper to get subject icons
        $get_icon = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'رياضيات') !== false)
                return 'dashicons-calculator';
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'انجليزي') !== false || strpos($subject_name, 'إنجليزية') !== false)
                return 'dashicons-admin-site-alt3';
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'علوم') !== false)
                return 'dashicons-rest-api';
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'عربي') !== false || strpos($subject_name, 'عربية') !== false)
                return 'dashicons-translation';
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'دين') !== false || strpos($subject_name, 'إسلامية') !== false)
                return 'dashicons-heart';
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'حاسوب') !== false || strpos($subject_name, 'مهارات رقمية') !== false)
                return 'dashicons-desktop';
            if (strpos($subject_name, 'art') !== false || strpos($subject_name, 'فنية') !== false)
                return 'dashicons-art';
            if (strpos($subject_name, 'physic') !== false || strpos($subject_name, 'رياضة') !== false)
                return 'dashicons-universal-access';
            return 'dashicons-book-alt';
        };

        // Helper to get subject background color (pastel based on subject type)
        $get_subject_bg = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'عربي') !== false || strpos($subject_name, 'عربية') !== false)
                return '#dcfce7'; // mint
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'رياضيات') !== false)
                return '#dbeafe'; // light blue
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'دين') !== false || strpos($subject_name, 'إسلامية') !== false || strpos($subject_name, 'تربية') !== false)
                return '#fef3c7'; // amber
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'انجليزي') !== false || strpos($subject_name, 'إنجليزية') !== false)
                return '#e0e7ff'; // indigo
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'علوم') !== false)
                return '#ccfbf1'; // teal
            return '#f1f5f9'; // default gray
        };

        ob_start();
        ?>
        <div class="olama-weekly-plan-v2">
            <?php
            // Get active academic year and calculate week number
            $active_year = Olama_School_Academic::get_active_year();
            $academic_year_display = '';
            $week_number = 1;

            if ($active_year) {
                $academic_year_display = $active_year->year_name ?? ($active_year->start_year . '-' . $active_year->end_year);

                // Get weeks for the active year to find the current week number
                $all_weeks = Olama_School_Academic::get_academic_weeks($active_year->id, $semester_id, true);
                if (!empty($all_weeks) && isset($all_weeks[$week_start])) {
                    $week_number = $all_weeks[$week_start]['number'];
                } else {
                    // Fallback: calculate week number from semester start
                    if ($semester_id) {
                        global $wpdb;
                        $semester_data = $wpdb->get_row($wpdb->prepare("SELECT start_date FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));
                        if ($semester_data) {
                            $semester_start = strtotime($semester_data->start_date);
                            $week_start_ts = strtotime($week_start);
                            $week_number = max(1, floor(($week_start_ts - $semester_start) / (7 * 86400)) + 1);
                        }
                    }
                }
            } else {
                // Fallback if no active year
                $start_year = date('Y', strtotime($week_start));
                $end_year = $start_year + 1;
                $academic_year_display = $start_year . '-' . $end_year;
            }

            // Arabic ordinal week names
            $week_ordinals = array(
                1 => 'الأول',
                2 => 'الثاني',
                3 => 'الثالث',
                4 => 'الرابع',
                5 => 'الخامس',
                6 => 'السادس',
                7 => 'السابع',
                8 => 'الثامن',
                9 => 'التاسع',
                10 => 'العاشر',
                11 => 'الحادي عشر',
                12 => 'الثاني عشر',
                13 => 'الثالث عشر',
                14 => 'الرابع عشر',
                15 => 'الخامس عشر',
                16 => 'السادس عشر',
                17 => 'السابع عشر',
                18 => 'الثامن عشر',
                19 => 'التاسع عشر',
                20 => 'العشرون'
            );
            $week_ordinal = isset($week_ordinals[$week_number]) ? $week_ordinals[$week_number] : $week_number;
            ?>
            <!-- Illustrated Header -->
            <div class="plan-header-v2">
                <div class="header-content">
                    <h1 class="header-title">الخطة الأسبوعية</h1>
                    <div class="header-subtitle">
                        <?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                        <?php echo $section ? esc_html($section->section_name) : ''; ?>
                    </div>
                </div>
                <!-- Academic Year & Week Info Bar (Integrated) -->
                <div class="semester-bar">
                    <div class="semester-left">
                        <span class="week-label">
                            <?php echo Olama_School_Helpers::translate('الأسبوع الدراسي') . ' ' . $week_ordinal; ?>
                        </span>
                        <span class="week-dates">(
                            <?php echo date_i18n('j', strtotime($week_start)); ?>-
                            <?php echo date_i18n('j F', strtotime($week_end)); ?>)
                        </span>
                    </div>
                    <div class="semester-right">
                        <span class="academic-year-label">
                            <?php echo Olama_School_Helpers::translate('العام الدراسي'); ?>
                        </span>
                        <span class="academic-year">
                            <?php echo esc_html($academic_year_display); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Days Accordion -->
            <div class="days-accordion">
                <?php
                $school_days = Olama_School_Helpers::get_school_days();
                $day_translations = array(
                    'Sunday' => 'الأحد',
                    'Monday' => 'الاثنين',
                    'Tuesday' => 'الثلاثاء',
                    'Wednesday' => 'الأربعاء',
                    'Thursday' => 'الخميس',
                    'Friday' => 'الجمعة',
                    'Saturday' => 'السبت'
                );
                $day_index = 0;
                foreach ($school_days as $day_en):
                    $day_ar = $day_translations[$day_en] ?? $day_en;
                    $current_date = date('Y-m-d', strtotime($week_start . ' +' . $day_index . ' days'));
                    $day_plans = $grouped_plans[$current_date] ?? array();
                    $is_active = $current_date == date('Y-m-d') ? 'active' : '';
                    if (empty($is_active) && empty($grouped_plans[date('Y-m-d')]) && $day_index === 0)
                        $is_active = 'active';
                    $day_index++;
                    ?>
                    <div class="day-item <?php echo $is_active; ?> <?php echo empty($day_plans) ? 'empty' : ''; ?>">
                        <div class="day-header">
                            <div class="day-left">
                                <div class="day-text">
                                    <span class="day-name-ar">
                                        <?php echo esc_html($day_ar); ?>
                                    </span>
                                    <?php
                                    // Count homeworks vs reviews separately
                                    $homework_count = 0;
                                    $review_count = 0;
                                    foreach ($day_plans as $p) {
                                        if (isset($p->plan_type) && $p->plan_type === 'review') {
                                            $review_count++;
                                        } else {
                                            $homework_count++;
                                        }
                                    }
                                    ?>
                                    <span class="day-count">
                                        <?php if ($homework_count > 0 || $review_count > 0): ?>
                                            <?php
                                            $parts = array();
                                            if ($homework_count > 0) {
                                                $parts[] = $homework_count . ' ' . Olama_School_Helpers::translate('واجبات');
                                            }
                                            if ($review_count > 0) {
                                                $parts[] = $review_count . ' ' . Olama_School_Helpers::translate('متابعات');
                                            }
                                            echo implode(' - ', $parts);
                                            ?>
                                        <?php else: ?>
                                            <?php echo Olama_School_Helpers::translate('لا واجبات'); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                            <div class="day-date-badge">
                                <span class="date-month">
                                    <?php echo strtoupper(Olama_School_Helpers::format_date($current_date, false, 'M')); ?>
                                </span>
                                <span class="date-day">
                                    <?php echo Olama_School_Helpers::format_date($current_date, false, 'd'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="day-content">
                            <?php if (empty($day_plans)): ?>
                                <div class="empty-day">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <p>
                                        <?php echo Olama_School_Helpers::translate('لا توجد حصص مخططة لهذا اليوم'); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($day_plans as $plan):
                                    $icon = $get_icon($plan->subject_name);
                                    $bg_color = $get_subject_bg($plan->subject_name);
                                    ?>
                                    <div class="subject-card" style="background: <?php echo esc_attr($bg_color); ?>;">
                                        <div class="subject-header">
                                            <span class="dashicons <?php echo $icon; ?> subject-icon"></span>
                                            <span class="subject-name">
                                                <?php echo esc_html($plan->subject_name); ?>
                                            </span>
                                            <?php if (isset($plan->plan_type) && $plan->plan_type === 'review'): ?>
                                                <span class="review-badge"
                                                    style="background: #f3e8ff; color: #7c3aed; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: 700; margin-right: 8px;">
                                                    ًں”„
                                                    <?php echo Olama_School_Helpers::translate('Review'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Classwork Section -->
                                        <div class="section-block classwork">
                                            <div class="section-label">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php
                                                // Show appropriate label based on plan type
                                                if (isset($plan->plan_type) && $plan->plan_type === 'review') {
                                                    echo Olama_School_Helpers::translate('Review the following lesson');
                                                } else {
                                                    echo Olama_School_Helpers::translate('حصة اليوم');
                                                }
                                                ?>
                                                <?php if (!isset($plan->plan_type) || $plan->plan_type !== 'review'): ?>
                                                    <span class="label-en">(Classwork)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail-list">
                                                <?php if ($plan->unit_name): ?>
                                                    <div class="detail-item">
                                                        <span class="dashicons dashicons-category detail-icon"></span>
                                                        <span class="detail-label">
                                                            <?php echo Olama_School_Helpers::translate('الوحدة'); ?>:
                                                        </span>
                                                        <span class="detail-value" style="text-align: right;">
                                                            <?php echo esc_html($plan->unit_name); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-book-alt detail-icon"></span>
                                                    <span class="detail-label">
                                                        <?php echo Olama_School_Helpers::translate('الدرس'); ?>:
                                                    </span>
                                                    <span class="detail-value" style="text-align: right;">
                                                        <?php echo esc_html($plan->lesson_title); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Homework Section (only for homework plans) -->
                                        <?php if ((!isset($plan->plan_type) || $plan->plan_type === 'homework') && ($plan->homework_sb || $plan->homework_eb || $plan->homework_nb || $plan->homework_ws)): ?>
                                            <div class="section-block homework">
                                                <div class="section-label">
                                                    <span class="dashicons dashicons-admin-home"></span>
                                                    <?php echo Olama_School_Helpers::translate('الواجب البيتي'); ?>
                                                </div>
                                                <div class="homework-list">
                                                    <?php if ($plan->homework_sb): ?>
                                                        <div class="homework-item">
                                                            <div class="homework-item-header">
                                                                <span class="dashicons dashicons-book hw-icon"></span>
                                                                <span class="hw-label">
                                                                    <?php echo Olama_School_Helpers::translate('كتاب الطالب'); ?>:
                                                                </span>
                                                            </div>
                                                            <span class="hw-value" style="text-align: right; display: block;">
                                                                <?php echo esc_html($plan->homework_sb); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_eb): ?>
                                                        <div class="homework-item">
                                                            <div class="homework-item-header">
                                                                <span class="dashicons dashicons-edit hw-icon"></span>
                                                                <span class="hw-label">
                                                                    <?php echo Olama_School_Helpers::translate('كتاب التمارين'); ?>:
                                                                </span>
                                                            </div>
                                                            <span class="hw-value" style="text-align: right; display: block;">
                                                                <?php echo esc_html($plan->homework_eb); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_nb): ?>
                                                        <div class="homework-item">
                                                            <div class="homework-item-header">
                                                                <span class="dashicons dashicons-media-text hw-icon"></span>
                                                                <span class="hw-label">
                                                                    <?php echo Olama_School_Helpers::translate('الدفتر'); ?>:
                                                                </span>
                                                            </div>
                                                            <span class="hw-value" style="text-align: right; display: block;">
                                                                <?php echo esc_html($plan->homework_nb); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_ws): ?>
                                                        <div class="homework-item">
                                                            <div class="homework-item-header">
                                                                <span class="dashicons dashicons-media-document hw-icon"></span>
                                                                <span class="hw-label">
                                                                    <?php echo Olama_School_Helpers::translate('الدوسية'); ?>:
                                                                </span>
                                                            </div>
                                                            <span class="hw-value" style="text-align: right; display: block;">
                                                                <?php echo esc_html($plan->homework_ws); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Teacher Notes -->
                                        <?php if (!empty($plan->teacher_notes)): ?>
                                            <div class="section-block teacher-notes">
                                                <div class="section-label">
                                                    <span class="dashicons dashicons-admin-comments"></span>
                                                    <?php echo Olama_School_Helpers::translate('ملاحظات المعلم'); ?>
                                                </div>
                                                <div class="notes-content" style="text-align: right;">
                                                    <?php echo nl2br(esc_html($plan->teacher_notes)); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
                document.querySelectorAll('.day-header').forEach(header => {
                    header.addEventListener('click', () => {
                        const item = header.parentElement;
                        const wasActive = item.classList.contains('active');
                        const content = item.querySelector('.day-content');

                        document.querySelectorAll('.day-item').forEach(i => {
                            i.classList.remove('active');
                            const c = i.querySelector('.day-content');
                            if (c) c.style.maxHeight = '0px';
                        });

                        if (!wasActive) {
                            item.classList.add('active');
                            if (content) {
                                content.style.maxHeight = '20000px';
                                content.style.overflow = 'visible';
                            }
                        }
                    });
                });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [olama_exam_report]
     * Attributes: year, semester, grade, exam
     */
    public function render_exam_report_shortcode($atts)
    {
        error_log('Olama: render_exam_report_shortcode started');
        $atts = shortcode_atts(array(
            'year' => '',
            'semester' => '',
            'grade' => '',
            'exam' => '', // semester_exam_id
        ), $atts, 'olama_exam_report');

        $student_uid = sanitize_text_field($_GET['student_uid'] ?? '');
        $student_context = null;
        $grade_id = intval($atts['grade']);

        // 1. Try resolving via Student Context (Direct Resolver)
        if ($student_uid) {
            $student_context = Olama_School_Exam::get_student_specific_exams($student_uid);
            if ($student_context) {
                $grade_id = $student_context['grade_id'];
                $year_id = $student_context['year_id'];
                $semester_id = $student_context['semester_id'];
                $semester_exam_id = $student_context['semester_exam_id'];
                $exams = $student_context['exams'];
                error_log("Olama: Resolved Student Context for $student_uid (Grade: $grade_id)");
            }
        }

        // 2. Fallback to standard Grade/Year/Semester resolution if no student context
        if (!$student_context) {
            if (!$grade_id && isset($_GET['grade_id'])) {
                $grade_id = intval($_GET['grade_id']);
            }

            if (!$grade_id) {
                return '<div class="olama-error">' . Olama_School_Helpers::translate('Please specify a valid grade ID in the shortcode.') . '</div>';
            }

            // Resolve Year ID
            $year_id = $atts['year'];
            if ($year_id === 'active' || empty($year_id)) {
                $active_year = Olama_School_Academic::get_active_year();
                $year_id = $active_year ? $active_year->id : 0;
            } else {
                $year_id = intval($year_id);
            }

            // Resolve Semester ID
            $semester_id = $atts['semester'];
            if ($semester_id === 'active' || empty($semester_id)) {
                $active_sem = Olama_School_Academic::get_active_semester($year_id);
                $semester_id = $active_sem ? $active_sem->id : 0;
            } else {
                $semester_id = intval($semester_id);
            }

            $semester_exam_id = $atts['exam'];
            if ($semester_exam_id === 'active') {
                $active_exam = Olama_School_Academic::get_active_exam($semester_id);
                $semester_exam_id = $active_exam ? $active_exam->id : 0;
            } else {
                $semester_exam_id = intval($semester_exam_id);
            }

            error_log("Olama: Filters - Year: $year_id, Sem: $semester_id, Grade: $grade_id, Exam: $semester_exam_id");
            // Fetch Approved Exams
            $exams = Olama_School_Exam::get_exams($year_id, $semester_id, $grade_id, 0, $semester_exam_id);
        }

        // Filter for approved/published only if not admin
        $is_admin = Olama_School_Permissions::can('olama_view_reports_summary');
        $approved_exams = array_filter($exams, function ($e) use ($is_admin) {
            return $is_admin || $e->status === 'approved' || $e->status === 'published';
        });

        if (empty($approved_exams)) {
            $msg = Olama_School_Helpers::translate('No approved exams found for the selected criteria.');
            if ($student_context) {
                $msg = sprintf(Olama_School_Helpers::translate('No approved exams found for student: %s'), $student_context['student_name']);
            }
            return '<div class="olama-no-plans" style="padding: 30px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; text-align: center; font-weight: 600;">' .
                $msg .
                '</div>';
        }

        // Group exams by date
        $grouped_exams = array();
        foreach ($approved_exams as $exam) {
            $grouped_exams[$exam->exam_date][] = $exam;
        }

        // Sort dates
        ksort($grouped_exams);

        // Get Grade info for the header
        $grade = Olama_School_Grade::get_grade($grade_id);
        $year_obj = Olama_School_Academic::get_year($year_id);
        $year_name = $year_obj ? $year_obj->year_name : '';
        $semester_exam = Olama_School_Academic::get_semester_exam($semester_exam_id);
        $exam_display_name = $semester_exam ? $semester_exam->exam_name : '';
        $total_exams = count($approved_exams);

        // Helper to get subject icons (reuse logic from weekly plan)
        $get_icon = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'رياضيات') !== false)
                return 'dashicons-calculator';
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'انجليزي') !== false || strpos($subject_name, 'إنجليزية') !== false)
                return 'dashicons-admin-site-alt3';
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'علوم') !== false)
                return 'dashicons-rest-api';
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'عربي') !== false || strpos($subject_name, 'عربية') !== false)
                return 'dashicons-translation';
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'دين') !== false || strpos($subject_name, 'إسلامية') !== false)
                return 'dashicons-heart';
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'حاسوب') !== false || strpos($subject_name, 'مهارات رقمية') !== false)
                return 'dashicons-desktop';
            if (strpos($subject_name, 'social') !== false || strpos($subject_name, 'اجتماعية') !== false || strpos($subject_name, 'دراسات') !== false)
                return 'dashicons-admin-site';
            return 'dashicons-book-alt';
        };

        // Helper to get subject background color
        $get_subject_bg = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'عربي') !== false || strpos($subject_name, 'عربية') !== false)
                return '#f0fdf4'; // fresh green
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'رياضيات') !== false)
                return '#eff6ff'; // blue
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'دين') !== false || strpos($subject_name, 'إسلامية') !== false)
                return '#fffbeb'; // amber
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'انجليزي') !== false || strpos($subject_name, 'إنجليزية') !== false)
                return '#eef2ff'; // indigo
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'علوم') !== false)
                return '#f0fdfa'; // teal
            return '#f8fafc'; // slate
        };

        ob_start();
        ?>
        <div class="olama-weekly-plan-v2 olama-exam-report-v2 <?php echo Olama_School_Helpers::is_arabic() ? 'is-rtl' : ''; ?>">
            <!-- Illustrated Header -->
            <div class="plan-header-v2" style="background: linear-gradient(145deg, #818cf8 0%, #6366f1 100%);">
                <div class="header-content">
                    <h1 class="header-title" style="color: #ffffff; font-size: 2.2rem; margin-bottom: 15px;">
                        <?php 
                        if ($student_context) {
                            echo sprintf(Olama_School_Helpers::translate('Exam Schedule for %s'), esc_html($student_context['student_name']));
                        } else {
                            echo Olama_School_Helpers::translate('Exam Schedule'); 
                        }
                        ?>
                    </h1>
                    <div class="header-subtitle" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px); padding: 8px 20px; border-radius: 50px; display: inline-flex; align-items: center; gap: 10px; font-weight: 500;">
                        <?php if ($student_context): ?>
                            <span style="font-size: 1.1rem; border-right: 1px solid rgba(255,255,255,0.3); padding-right: 10px;"><?php echo esc_html($grade->grade_name); ?></span>
                        <?php else: ?>
                            <span style="font-size: 1.1rem; border-right: 1px solid rgba(255,255,255,0.3); padding-right: 10px;"><?php echo $grade ? esc_html($grade->grade_name) : ''; ?></span>
                        <?php endif; ?>
                        
                        <span style="opacity: 0.9;"><?php echo esc_html($semester_exam ? $semester_exam->exam_name : ''); ?></span>
                    </div>
                </div>
                <!-- Academic Year & Info Bar -->
                <div class="semester-bar" style="background: rgba(255, 255, 255, 0.15);">
                    <div class="semester-left" style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="week-label">
                                <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                            </span>
                            <span class="week-dates" style="color: #fff;">
                                <?php echo esc_html($year_name); ?>
                            </span>
                        </div>
                        <?php if ($exam_display_name): ?>
                            <div style="margin-top: 5px; font-weight: 700; color: rgba(255,255,255,0.9);">
                                <?php echo esc_html($exam_display_name); ?>
                                <span style="font-weight: 400; font-size: 0.9em; margin-inline-start: 10px; opacity: 0.8;">
                                    (
                                    <?php echo Olama_School_Helpers::translate('Total Exams'); ?>:
                                    <?php echo $total_exams; ?>)
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Exams Accordion -->
            <div class="days-accordion">
                <?php
                $first = true;
                foreach ($grouped_exams as $date => $exams_on_date):
                    $is_active = $first ? 'active' : '';
                    $first = false;
                    // Get first subject for the header
                    $first_exam = reset($exams_on_date);
                    $header_subject = $first_exam->subject_name ?? '';
                    ?>
                    <div class="day-item <?php echo $is_active; ?>">
                        <div class="day-header">
                            <div class="day-left">
                                <div class="day-text">
                                    <span class="day-name-ar">
                                        <?php echo date_i18n('l', strtotime($date)); ?>
                                        <?php if ($header_subject): ?>
                                            <span style="font-weight: 400; margin-inline-start: 10px; color: #64748b;">-
                                                <?php echo esc_html($header_subject); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
                            </div>
                            <div class="day-date-badge" style="background: linear-gradient(145deg, #6366f1 0%, #4f46e5 100%);">
                                <span class="date-month">
                                    <?php echo strtoupper(Olama_School_Helpers::format_date($date, false, 'M')); ?>
                                </span>
                                <span class="date-day">
                                    <?php echo Olama_School_Helpers::format_date($date, false, 'd'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="day-content">
                            <?php foreach ($exams_on_date as $exam):
                                $icon = $get_icon($exam->subject_name);
                                $bg_color = $get_subject_bg($exam->subject_name);
                                ?>
                                <div class="subject-card"
                                    style="background: <?php echo esc_attr($bg_color); ?>; border-inline-start: 5px solid #6366f1;">
                                    <div class="subject-header">
                                        <span class="dashicons <?php echo $icon; ?> subject-icon"></span>
                                        <span class="subject-name">
                                            <?php echo esc_html($exam->subject_name ?? ''); ?>
                                        </span>
                                        <?php if (!empty($exam->exam_name)): ?>
                                            <span class="exam-type-badge"
                                                style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: 700; margin-right: auto;">
                                                <?php echo esc_html($exam->exam_name); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Exam Info -->
                                    <div class="detail-list" style="margin-bottom: 15px;">
                                        <?php if ($exam->room_number || $exam->master_room): ?>
                                            <div class="detail-item">
                                                <span class="dashicons dashicons-location detail-icon"></span>
                                                <span class="detail-label">
                                                    <?php echo Olama_School_Helpers::translate('Hall/Room'); ?>:
                                                </span>
                                                <span class="detail-value">
                                                    <?php echo esc_html($exam->room_number ?: $exam->master_room); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Material Section -->
                                    <div class="section-block classwork"
                                        style="border-radius: 12px; border: 1px solid rgba(99, 102, 241, 0.1);">
                                        <div class="section-label" style="color: #4f46e5;">
                                            <span class="dashicons dashicons-welcome-learn-more"></span>
                                            <?php echo Olama_School_Helpers::translate('Exam Subject'); ?>
                                        </div>

                                        <?php if ($exam->description): ?>
                                            <div class="detail-item" style="margin-bottom: 10px; font-weight: 700; color: #1e293b;">
                                                <?php echo nl2br(esc_html($exam->description)); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="detail-list">
                                            <?php
                                            $json_material = json_decode($exam->exam_material_json, true);
                                            $material = is_array($json_material) ? $json_material : array();
                                            if (!empty($material['curriculum_items'])):
                                                $grouped_items = array();
                                                foreach ($material['curriculum_items'] as $item) {
                                                    $unit_id = !empty($item['unit_id']) ? $item['unit_id'] : 'other';
                                                    if (!isset($grouped_items[$unit_id])) {
                                                        $grouped_items[$unit_id] = array();
                                                    }
                                                    $grouped_items[$unit_id][] = $item;
                                                }

                                                foreach ($grouped_items as $unit_id => $items):
                                                    $unit_name = '';
                                                    if ($unit_id !== 'other') {
                                                        $unit = Olama_School_Unit::get_unit($unit_id);
                                                        $unit_name = $unit ? $unit->unit_name : '';
                                                    }

                                                    if ($unit_name): ?>
                                                        <div class="unit-group-header"
                                                            style="font-weight: 700; color: #4f46e5; margin: 15px 0 8px; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; text-align: right; direction: rtl; justify-content: flex-start;">
                                                            <span class="dashicons dashicons-category"
                                                                style="font-size: 18px; width: 18px; height: 18px; color: #818cf8;"></span>
                                                            <?php echo esc_html($unit_name); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="unit-lessons-list"
                                                        style="padding-inline-start: 15px; border-inline-start: 2px solid #eef2ff; margin-inline-start: 5px; text-align: right; direction: rtl;">
                                                        <?php foreach ($items as $item):
                                                            $lesson = !empty($item['lesson_id']) ? Olama_School_Lesson::get_lesson($item['lesson_id']) : null;
                                                            $lesson_name = $lesson ? $lesson->lesson_title : (isset($item['lesson']) ? $item['lesson'] : '');

                                                            if (empty($lesson_name) && empty($item['material']))
                                                                continue;
                                                            ?>
                                                            <div class="detail-item lesson-row-v2"
                                                                style="padding: 6px 0; border-bottom: 1px solid #f8fafc; margin-bottom: 4px; text-align: right; direction: rtl;">
                                                                <div class="lesson-info"
                                                                    style="display: flex; align-items: flex-start; gap: 10px; text-align: right; direction: rtl;">
                                                                    <span class="dashicons dashicons-arrow-left-alt2"
                                                                        style="font-size: 12px; width: 12px; height: 12px; margin-top: 4px; color: #cbd5e1;"></span>
                                                                    <div style="flex: 1; text-align: right; direction: rtl;">
                                                                        <?php if ($lesson_name): ?>
                                                                            <div
                                                                                style="font-weight: 600; color: #1e293b; font-size: 0.9rem; text-align: right; direction: rtl;">
                                                                                <?php echo esc_html($lesson_name); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($item['material'])): ?>
                                                                            <div
                                                                                style="font-size: 0.85rem; color: #64748b; margin-top: 2px; text-align: right; direction: rtl;">
                                                                                <?php echo esc_html($item['material']); ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach;
                                            endif; ?>

                                            <div class="material-details-v2 <?php echo Olama_School_Helpers::is_arabic() ? 'is-rtl' : ''; ?>"
                                                style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px; display: flex; flex-direction: column; gap: 15px; text-align: right; direction: rtl;">
                                                <?php if ($exam->student_book_material): ?>
                                                    <div class="detail-item-stacked" style="text-align: right; direction: rtl;">
                                                        <div class="stacked-label"
                                                            style="display: flex; align-items: center; gap: 8px; color: #64748b; font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; text-align: right; direction: rtl; justify-content: flex-start;">
                                                            <span class="dashicons dashicons-book"
                                                                style="font-size: 16px; width: 16px; height: 16px; color: #94a3b8;"></span>
                                                            <?php echo Olama_School_Helpers::translate('Student Book'); ?>:
                                                        </div>
                                                        <div class="stacked-value"
                                                            style="color: #1e293b; font-size: 0.95rem; padding-inline-start: 24px; line-height: 1.6; word-break: break-word; text-align: right; direction: rtl;">
                                                            <?php echo esc_html($exam->student_book_material); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($exam->workbook_material): ?>
                                                    <div class="detail-item-stacked" style="text-align: right; direction: rtl;">
                                                        <div class="stacked-label"
                                                            style="display: flex; align-items: center; gap: 8px; color: #64748b; font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; text-align: right; direction: rtl; justify-content: flex-start;">
                                                            <span class="dashicons dashicons-edit"
                                                                style="font-size: 16px; width: 16px; height: 16px; color: #94a3b8;"></span>
                                                            <?php echo Olama_School_Helpers::translate('Workbook'); ?>:
                                                        </div>
                                                        <div class="stacked-value"
                                                            style="color: #1e293b; font-size: 0.95rem; padding-inline-start: 24px; line-height: 1.6; word-break: break-word; text-align: right; direction: rtl;">
                                                            <?php echo esc_html($exam->workbook_material); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php
                                                $booklets_val = !empty($material['booklets_notebooks']) ? $material['booklets_notebooks'] : (!empty($exam->notebook_material) ? $exam->notebook_material : '');
                                                if (!empty($booklets_val)): ?>
                                                    <div class="detail-item-stacked" style="text-align: right; direction: rtl;">
                                                        <div class="stacked-label"
                                                            style="display: flex; align-items: center; gap: 8px; color: #64748b; font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; text-align: right; direction: rtl; justify-content: flex-start;">
                                                            <span class="dashicons dashicons-media-text"
                                                                style="font-size: 16px; width: 16px; height: 16px; color: #94a3b8;"></span>
                                                            <?php echo Olama_School_Helpers::translate('Booklets & Notebooks'); ?>:
                                                        </div>
                                                        <div class="stacked-value"
                                                            style="color: #1e293b; font-size: 0.95rem; padding-inline-start: 24px; line-height: 1.6; word-break: break-word; text-align: right; direction: rtl;">
                                                            <?php echo esc_html($booklets_val); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Teacher Notes -->
                                    <?php
                                    $notes_val = !empty($material['teacher_notes']) ? $material['teacher_notes'] : (!empty($exam->teacher_notes) ? $exam->teacher_notes : '');
                                    if (!empty($notes_val)): ?>
                                        <div class="section-block teacher-notes"
                                            style="background: rgba(254, 243, 199, 0.4); border-color: rgba(251, 191, 36, 0.2);">
                                            <div class="section-label" style="color: #b45309;">
                                                <span class="dashicons dashicons-info"></span>
                                                <?php echo Olama_School_Helpers::translate('Important Notes'); ?>
                                            </div>
                                            <div class="notes-content">
                                                <?php echo nl2br(esc_html($notes_val)); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            return ob_get_clean();
    }

    /**
     * Shortcode: [olama_weekly_schedule]
     * Attributes: semester, section, schedule_type
     */
    public function render_weekly_schedule_shortcode($atts)
    {
        global $wpdb;
        $atts = shortcode_atts(array(
            'semester' => '',
            'section' => '',
            'schedule_type' => 'normal',
        ), $atts, 'olama_weekly_schedule');

        $section_id = intval($atts['section']);
        $semester_id = $atts['semester'];

        // Resolve Semester ID
        if ($semester_id === 'active' || empty($semester_id)) {
            $active_year = Olama_School_Academic::get_active_year();
            $active_sem = $active_year ? Olama_School_Academic::get_active_semester($active_year->id) : null;
            $semester_id = $active_sem ? $active_sem->id : 0;
        } else {
            $semester_id = intval($semester_id);
        }

        if (!$section_id || !$semester_id) {
            return '<div class="olama-error">' . __('Please specify valid section and semester IDs in the shortcode.', 'olama-school') . '</div>';
        }

        $schedule_type = sanitize_text_field($atts['schedule_type']);
        if (!in_array($schedule_type, array('normal', 'ramadan'))) {
            $schedule_type = 'normal';
        }

        $schedule = Olama_School_Schedule::get_schedule($section_id, $semester_id, $schedule_type);
        $section = Olama_School_Section::get_section($section_id);
        $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;
        $semester = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));

        if (empty($schedule)) {
            return '<div class="olama-no-plans">' . __('No master schedule found for the selected section and semester.', 'olama-school') . '</div>';
        }

        $school_days = Olama_School_Helpers::get_school_days();
        $day_translations = array(
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت'
        );
        $days_ar = array();
        foreach ($school_days as $day_en) {
            $days_ar[$day_en] = $day_translations[$day_en] ?? $day_en;
        }

        // Arabic ordinal period names
        $periods_ar = array(
            1 => 'الأولى',
            2 => 'الثانية',
            3 => 'الثالثة',
            4 => 'الرابعة',
            5 => 'الخامسة',
            6 => 'السادسة',
            7 => 'السابعة',
            8 => 'الثامنة',
            9 => 'التاسعة',
            10 => 'العاشرة',
        );

        $max_periods = $grade ? intval($grade->periods_count) : 8;

        // Get subject color based on name
        $get_subject_color = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'رياضيات') !== false)
                return array('bg' => '#dbeafe', 'text' => '#1e40af');
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'عربي') !== false || strpos($subject_name, 'عربية') !== false)
                return array('bg' => '#dcfce7', 'text' => '#166534');
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'انجليزي') !== false || strpos($subject_name, 'إنجليزية') !== false)
                return array('bg' => '#e0e7ff', 'text' => '#4338ca');
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'علوم') !== false)
                return array('bg' => '#ccfbf1', 'text' => '#0f766e');
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'دين') !== false || strpos($subject_name, 'إسلامية') !== false || strpos($subject_name, 'تربية') !== false)
                return array('bg' => '#fef3c7', 'text' => '#92400e');
            if (strpos($subject_name, 'social') !== false || strpos($subject_name, 'اجتماعية') !== false || strpos($subject_name, 'دراسات') !== false)
                return array('bg' => '#fce7f3', 'text' => '#9d174d');
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'حاسوب') !== false || strpos($subject_name, 'رقمية') !== false)
                return array('bg' => '#f3e8ff', 'text' => '#7c3aed');
            if (strpos($subject_name, 'art') !== false || strpos($subject_name, 'فنية') !== false)
                return array('bg' => '#fff7ed', 'text' => '#c2410c');
            if (strpos($subject_name, 'physic') !== false || strpos($subject_name, 'رياضة') !== false || strpos($subject_name, 'بدنية') !== false)
                return array('bg' => '#fef2f2', 'text' => '#b91c1c');
            return array('bg' => '#f1f5f9', 'text' => '#475569');
        };

        ob_start();
        ?>
            <div class="olama-schedule-v2">
                <!-- Header -->
                <div class="schedule-header-v2">
                    <div class="header-main">
                        <h1 class="header-title">
                            <?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                            <?php echo $section ? esc_html($section->section_name) : ''; ?>
                        </h1>
                    </div>
                    <div class="header-badge">
                        <span class="badge-icon">📅</span>
                        <span class="badge-text">
                            <?php
                            // Get academic year
                            $academic_year = $wpdb->get_row($wpdb->prepare(
                                "SELECT ay.* FROM {$wpdb->prefix}olama_academic_years ay 
                             INNER JOIN {$wpdb->prefix}olama_semesters s ON s.academic_year_id = ay.id 
                             WHERE s.id = %d",
                                $semester_id
                            ));
                            if ($academic_year) {
                                echo esc_html($academic_year->year_name) . ' - ';
                            }
                            echo $semester ? esc_html(Olama_School_Helpers::translate($semester->semester_name)) : '';
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Desktop Grid -->
                <div class="schedule-grid-desktop">
                    <table class="schedule-table-v2">
                        <thead>
                            <tr>
                                <th class="day-col-header">
                                    <?php echo Olama_School_Helpers::translate('Day'); ?>
                                </th>
                                <?php for ($i = 1; $i <= $max_periods; $i++): ?>
                                    <th class="period-col-header">
                                        <span class="period-label-text">
                                            <?php echo Olama_School_Helpers::translate('Period'); ?>
                                        </span>
                                        <span class="period-ordinal">
                                            <?php echo isset($periods_ar[$i]) ? $periods_ar[$i] : $i; ?>
                                        </span>
                                    </th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days_ar as $day_en => $day_ar): ?>
                                <tr>
                                    <td class="day-cell">
                                        <span class="day-name">
                                            <?php echo esc_html($day_ar); ?>
                                        </span>
                                    </td>
                                    <?php for ($i = 1; $i <= $max_periods; $i++):
                                        $item = $schedule[$day_en][$i] ?? null;
                                        $colors = $item ? $get_subject_color($item->subject_name) : array('bg' => '#f8fafc', 'text' => '#94a3b8');
                                        ?>
                                        <td class="subject-cell">
                                            <?php if ($item): ?>
                                                <div class="subject-card-v2"
                                                    style="background: <?php echo esc_attr($colors['bg']); ?>; color: <?php echo esc_attr($colors['text']); ?>;">
                                                    <span class="subject-text">
                                                        <?php echo esc_html($item->subject_name); ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-cell">-</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Accordion -->
                <div class="schedule-mobile-v2">
                    <?php foreach ($days_ar as $day_en => $day_ar): ?>
                        <div class="mobile-day-card">
                            <div class="mobile-day-header" onclick="this.parentElement.classList.toggle('expanded')">
                                <span class="day-icon">📅</span>
                                <span class="day-title">
                                    <?php echo esc_html($day_ar); ?>
                                </span>
                                <span class="toggle-arrow">▼</span>
                            </div>
                            <div class="mobile-day-content">
                                <?php
                                $has_periods = false;
                                for ($i = 1; $i <= $max_periods; $i++):
                                    $item = $schedule[$day_en][$i] ?? null;
                                    if (!$item)
                                        continue;
                                    $has_periods = true;
                                    $colors = $get_subject_color($item->subject_name);
                                    ?>
                                    <div class="mobile-period-item" style="background: <?php echo esc_attr($colors['bg']); ?>;">
                                        <span class="period-badge" style="background: <?php echo esc_attr($colors['text']); ?>;">
                                            <?php echo Olama_School_Helpers::translate('Period'); ?>
                                            <?php echo isset($periods_ar[$i]) ? $periods_ar[$i] : $i; ?>
                                        </span>
                                        <span class="subject-name-mobile" style="color: <?php echo esc_attr($colors['text']); ?>;">
                                            <?php echo esc_html($item->subject_name); ?>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                                <?php if (!$has_periods): ?>
                                    <div class="no-periods">
                                        <?php echo Olama_School_Helpers::translate('No periods found'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <style>
                .olama-schedule-v2 {
                    font-family: 'Tajawal', 'Almarai', Arial, sans-serif;
                    max-width: 100%;
                    margin: 0 auto;
                    direction: rtl;
                }

                /* Header */
                .schedule-header-v2 {
                    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                    color: #fff;
                    padding: 25px 30px;
                    border-radius: 16px 16px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 15px;
                }

                .schedule-header-v2 .header-title {
                    margin: 0;
                    font-size: 1.6rem;
                    font-weight: 800;
                    color: #fff;
                }

                .schedule-header-v2 .header-badge {
                    background: rgba(255, 255, 255, 0.2);
                    padding: 12px 22px;
                    border-radius: 25px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-size: 1.15rem;
                    font-weight: 700;
                    color: #fff;
                }

                .schedule-header-v2 .badge-icon {
                    font-size: 1.2rem;
                }

                /* Desktop Grid */
                .schedule-grid-desktop {
                    overflow-x: auto;
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-top: none;
                    border-radius: 0 0 16px 16px;
                }

                .schedule-table-v2 {
                    width: 100%;
                    border-collapse: collapse;
                    min-width: 700px;
                }

                .schedule-table-v2 thead th {
                    background: #f8fafc;
                    padding: 15px 10px;
                    text-align: center;
                    font-weight: 700;
                    color: #334155;
                    border-bottom: 2px solid #e2e8f0;
                    font-size: 0.85rem;
                }

                .schedule-table-v2 .day-col-header {
                    width: 100px;
                    background: #f1f5f9;
                }

                .schedule-table-v2 .period-col-header .period-label-text,
                .schedule-table-v2 .period-col-header .period-ordinal {
                    display: block;
                }

                .schedule-table-v2 .period-label-text {
                    font-size: 0.7rem;
                    color: #64748b;
                    font-weight: 500;
                }

                .schedule-table-v2 .period-ordinal {
                    font-size: 0.9rem;
                    color: #1e40af;
                    font-weight: 700;
                }

                .schedule-table-v2 tbody td {
                    padding: 8px;
                    text-align: center;
                    border-bottom: 1px solid #f1f5f9;
                    vertical-align: middle;
                }

                .schedule-table-v2 .day-cell {
                    background: #f8fafc;
                    font-weight: 700;
                    color: #1e293b;
                    font-size: 0.95rem;
                }

                .schedule-table-v2 .subject-card-v2 {
                    padding: 10px 8px;
                    border-radius: 10px;
                    font-size: 0.85rem;
                    font-weight: 600;
                    min-height: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .schedule-table-v2 .subject-card-v2:hover {
                    transform: scale(1.03);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                }

                .schedule-table-v2 .empty-cell {
                    color: #cbd5e1;
                    font-size: 1rem;
                }

                /* Mobile Accordion */
                .schedule-mobile-v2 {
                    display: none;
                    flex-direction: column;
                    gap: 12px;
                    padding: 15px;
                    background: #f8fafc;
                    border-radius: 0 0 16px 16px;
                }

                .mobile-day-card {
                    background: #fff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                    border: 1px solid #e2e8f0;
                }

                .mobile-day-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 16px 20px;
                    cursor: pointer;
                    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
                    transition: background 0.2s;
                }

                .mobile-day-header:hover {
                    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                }

                .mobile-day-header .day-icon {
                    font-size: 1.3rem;
                }

                .mobile-day-header .day-title {
                    flex: 1;
                    font-weight: 700;
                    font-size: 1.1rem;
                    color: #1e40af;
                }

                .mobile-day-header .toggle-arrow {
                    color: #64748b;
                    transition: transform 0.3s;
                }

                .mobile-day-card.expanded .toggle-arrow {
                    transform: rotate(180deg);
                }

                .mobile-day-content {
                    display: none;
                    padding: 15px;
                    flex-direction: column;
                    gap: 10px;
                }

                .mobile-day-card.expanded .mobile-day-content {
                    display: flex;
                }

                .mobile-period-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 12px 15px;
                    border-radius: 10px;
                }

                .mobile-period-item .period-badge {
                    color: #fff;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    white-space: nowrap;
                }

                .mobile-period-item .subject-name-mobile {
                    font-weight: 700;
                    font-size: 1rem;
                }

                .mobile-day-content .no-periods {
                    text-align: center;
                    color: #94a3b8;
                    padding: 20px;
                    font-size: 0.9rem;
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .schedule-grid-desktop {
                        display: none;
                    }

                    .schedule-mobile-v2 {
                        display: flex;
                    }

                    .schedule-header-v2 {
                        border-radius: 16px 16px 0 0;
                        padding: 20px;
                    }

                    .schedule-header-v2 .header-title {
                        font-size: 1.3rem;
                    }
                }

                /* Fix for WordPress themes */
                .olama-schedule-v2 table {
                    margin: 0 !important;
                }

                .olama-schedule-v2 th,
                .olama-schedule-v2 td {
                    border: none !important;
                }
            </style>
            <?php
            return ob_get_clean();
    }


    /**
     * Shortcode: [olama_stationary]
     * Attributes: year (academic year ID)
     */
    public function render_stationary_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'year' => '',
        ), $atts, 'olama_stationary');

        // Get academic year
        $year_id = intval($atts['year']);
        if (!$year_id) {
            $active_year = Olama_School_Academic::get_active_year();
            $year_id = $active_year ? $active_year->id : 0;
        }

        if (!$year_id) {
            return '<div class="olama-error" style="padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; text-align: center;">' .
                Olama_School_Helpers::translate('No academic year specified or active.') . '</div>';
        }

        // Get all stationary for this year
        $stationary_items = Olama_School_Stationary::get_all_stationary_by_year($year_id);

        // Get academic year name
        $year = Olama_School_Academic::get_year($year_id);
        $year_name = $year ? $year->year_name : '';

        if (empty($stationary_items)) {
            return '<div class="olama-no-data" style="padding: 30px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; color: #92400e; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No stationary defined for this academic year.') . '</div>';
        }

        // Gradient colors for accordion headers
        $gradients = array(
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
            'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
            'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
        );

        ob_start();
        ?>
            <div class="olama-stationary-container">
                <!-- Header -->
                <div class="olama-stationary-header">
                    <div class="header-icon">📚</div>
                    <div class="header-content">
                        <h1>
                            <?php echo Olama_School_Helpers::translate('School Stationery'); ?>
                        </h1>
                        <p>
                            <?php echo Olama_School_Helpers::translate('Stationery list for each grade'); ?>
                        </p>
                    </div>
                    <div class="header-year">
                        <span class="year-label">
                            <?php echo Olama_School_Helpers::translate('Academic Year'); ?>
                        </span>
                        <span class="year-value">
                            <?php echo esc_html($year_name); ?>
                        </span>
                    </div>
                </div>

                <!-- Accordion -->
                <div class="olama-stationary-accordion">
                    <?php
                    $index = 0;
                    foreach ($stationary_items as $item):
                        $gradient = $gradients[$index % count($gradients)];
                        $is_first = $index === 0;
                        $index++;
                        ?>
                        <div class="olama-accordion-item <?php echo $is_first ? 'olama-active' : ''; ?>">
                            <div class="olama-accordion-header" style="background: <?php echo $gradient; ?>;">
                                <div class="header-left">
                                    <span class="grade-icon">🎒</span>
                                    <span class="grade-name">
                                        <?php echo esc_html($item->grade_name); ?>
                                    </span>
                                </div>
                                <div class="header-right">
                                    <span class="toggle-icon">▼</span>
                                </div>
                            </div>
                            <div class="olama-accordion-content" <?php echo $is_first ? 'style="display: block;"' : ''; ?>>
                                <?php if (!empty($item->notebooks)): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <span class="section-icon">📓</span>
                                            <?php echo Olama_School_Helpers::translate('Required Notebooks'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->notebooks)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item->stationary)): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <span class="section-icon">📏</span>
                                            <?php echo Olama_School_Helpers::translate('Required Stationery'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->stationary)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item->teacher_notes)): ?>
                                    <div class="content-section notes">
                                        <div class="section-title">
                                            <span class="section-icon">📝</span>
                                            <?php echo Olama_School_Helpers::translate('Teacher Notes'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->teacher_notes)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($item->notebooks) && empty($item->stationary) && empty($item->teacher_notes)): ?>
                                    <div class="empty-state">
                                        <span class="empty-icon">📭</span>
                                        <p>
                                            <?php echo Olama_School_Helpers::translate('No stationary defined for this grade yet.'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer -->
                <div class="olama-stationary-footer">
                    <p>
                        <?php echo Olama_School_Helpers::translate('Please bring all supplies on the first day of school'); ?>
                        📖
                    </p>
                </div>
            </div>

            <style>
                .olama-stationary-container {
                    font-family: 'Tajawal', 'Almarai', Arial, sans-serif;
                    max-width: 900px;
                    margin: 0 auto;
                    direction: rtl;
                }

                .olama-stationary-header {
                    background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%);
                    color: #1e293b;
                    padding: 30px;
                    border-radius: 16px 16px 0 0;
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    flex-wrap: wrap;
                    border: 1px solid #fde68a;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
                }

                .olama-stationary-header .header-icon {
                    font-size: 50px;
                }

                .olama-stationary-header .header-content {
                    flex: 1;
                }

                .olama-stationary-header h1 {
                    margin: 0 0 5px 0;
                    font-size: 1.8rem;
                    font-weight: 800;
                    color: #92400e;
                }

                .olama-stationary-header p {
                    margin: 0;
                    color: #78716c;
                    font-size: 1rem;
                }

                .header-year {
                    background: #fff;
                    padding: 12px 20px;
                    border-radius: 12px;
                    text-align: center;
                    border: 1px solid #fde68a;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                }

                .header-year .year-label {
                    display: block;
                    font-size: 0.75rem;
                    color: #92400e;
                    margin-bottom: 4px;
                }

                .header-year .year-value {
                    font-size: 1.1rem;
                    font-weight: 700;
                    color: #1e293b;
                }

                .olama-stationary-accordion {
                    background: #f8fafc;
                    padding: 20px;
                    border-radius: 0 0 16px 16px;
                }

                .olama-accordion-item {
                    margin-bottom: 15px;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }

                .olama-accordion-header {
                    padding: 18px 24px;
                    cursor: pointer;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    color: #fff;
                    transition: all 0.3s ease;
                }

                .olama-accordion-header:hover {
                    opacity: 0.95;
                    transform: translateY(-1px);
                }

                .header-left {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .grade-icon {
                    font-size: 24px;
                }

                .grade-name {
                    font-size: 1.2rem;
                    font-weight: 700;
                }

                .toggle-icon {
                    font-size: 14px;
                    transition: transform 0.3s ease;
                }

                .olama-accordion-item.olama-active .toggle-icon {
                    transform: rotate(180deg);
                }

                .olama-accordion-content {
                    display: none;
                    background: #fff;
                    padding: 25px;
                    border-top: 3px solid rgba(0, 0, 0, 0.05);
                }

                .content-section {
                    margin-bottom: 20px;
                    padding-bottom: 20px;
                    border-bottom: 1px dashed #e2e8f0;
                }

                .content-section:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }

                .section-title {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-weight: 700;
                    font-size: 1.1rem;
                    color: #1e293b;
                    margin-bottom: 12px;
                }

                .section-icon {
                    font-size: 20px;
                }

                .section-content {
                    background: #f8fafc;
                    padding: 15px 20px;
                    border-radius: 10px;
                    line-height: 1.8;
                    color: #475569;
                    font-size: 0.95rem;
                    border-right: 4px solid #3b82f6;
                }

                .content-section.notes .section-content {
                    background: #fffbeb;
                    border-right-color: #f59e0b;
                    color: #92400e;
                }

                .empty-state {
                    text-align: center;
                    padding: 30px;
                    color: #94a3b8;
                }

                .empty-state .empty-icon {
                    font-size: 40px;
                    display: block;
                    margin-bottom: 10px;
                }

                .olama-stationary-footer {
                    text-align: center;
                    padding: 20px;
                    background: #f1f5f9;
                    border-radius: 12px;
                    margin-top: 20px;
                    color: #64748b;
                    font-size: 0.9rem;
                }

                /* Mobile Responsive */
                @media (max-width: 600px) {
                    .olama-stationary-header {
                        padding: 20px;
                        flex-direction: column;
                        text-align: center;
                    }

                    .olama-stationary-header h1 {
                        font-size: 1.4rem;
                    }

                    .olama-stationary-header .header-icon {
                        font-size: 40px;
                    }

                    .header-year {
                        width: 100%;
                    }

                    .olama-accordion-header {
                        padding: 15px 18px;
                    }

                    .grade-name {
                        font-size: 1rem;
                    }

                    .olama-accordion-content {
                        padding: 18px;
                    }

                    .section-content {
                        padding: 12px 15px;
                        font-size: 0.9rem;
                    }
                }
            </style>
            <?php
            return ob_get_clean();
    }

    /**
     * Shortcode: [olama_exam_schedule]
     * Attributes: grade
     */
    public function render_exam_schedule_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'grade' => '',
        ), $atts, 'olama_exam_schedule');

        $grade_id = intval($atts['grade']);
        $active_year = Olama_School_Academic::get_active_year();
        $year_id = $active_year ? $active_year->id : 0;
        $semester = Olama_School_Academic::get_active_semester($year_id);
        $semester_id = $semester ? $semester->id : 0;
        $exam = Olama_School_Academic::get_active_exam($semester_id);
        $exam_id = $exam ? $exam->id : 0;

        if (!$grade_id) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please specify a valid grade ID in the shortcode.') . '</div>';
        }

        if (!$exam_id) {
            return '<div class="olama-no-plans">' . Olama_School_Helpers::translate('No active exam found for the current semester.') . '</div>';
        }

        $exams = Olama_School_Exam::get_exams($year_id, $semester_id, $grade_id, 0, $exam_id);

        if (empty($exams)) {
            return '<div class="olama-no-plans">' . Olama_School_Helpers::translate('No subjects found for the active exam.') . '</div>';
        }

        ob_start();
        ?>
            <div class="olama-exam-schedule-student">
                <div class="exam-header">
                    <div class="exam-title-group">
                        <h2 class="exam-name">
                            <?php echo esc_html($exam->exam_name); ?>
                        </h2>
                        <p class="exam-semester">
                            <?php echo esc_html($semester->semester_name); ?> -
                            <?php echo esc_html($active_year->year_name); ?>
                        </p>
                    </div>
                    <div class="exam-grade-badge">
                        <?php
                        $grade_info = Olama_School_Grade::get_grade($grade_id);
                        echo $grade_info ? esc_html($grade_info->grade_name) : '';
                        ?>
                    </div>
                </div>

                <div class="exam-list">
                    <?php foreach ($exams as $ex):
                        $subject = Olama_School_Subject::get_subject($ex->subject_id);
                        $formatted_date = Olama_School_Helpers::format_date($ex->exam_date);
                        ?>
                        <div class="exam-card">
                            <div class="exam-card-header" onclick="this.parentElement.classList.toggle('active')">
                                <div class="subject-info">
                                    <span class="dashicons dashicons-book subject-icon"></span>
                                    <span class="subject-name">
                                        <?php echo $subject ? esc_html($subject->subject_name) : ''; ?>
                                    </span>
                                </div>
                                <div class="exam-meta">
                                    <span class="exam-date">
                                        <?php echo $formatted_date; ?>
                                    </span>
                                    <span class="dashicons dashicons-arrow-down-alt2 toggle-icon"></span>
                                </div>
                            </div>
                            <div class="exam-card-content">
                                <?php if ($ex->room_number): ?>
                                    <div class="detail-row room">
                                        <span class="label">
                                            <?php echo Olama_School_Helpers::translate('Room'); ?>:
                                        </span>
                                        <span class="value">
                                            <?php echo esc_html($ex->room_number); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ex->description): ?>
                                    <div class="detail-row description" style="margin-bottom: 15px;">
                                        <span class="label">
                                            <?php echo Olama_School_Helpers::translate('Description'); ?>:
                                        </span>
                                        <div class="value" style="font-weight: 600; color: #1e293b;">
                                            <?php echo nl2br(esc_html($ex->description)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $json_material = json_decode($ex->exam_material_json, true);
                                $material = is_array($json_material) ? $json_material : array();

                                // Render Curriculum Items if they exist
                                if (!empty($material['curriculum_items'])): ?>
                                    <div class="curriculum-material-section" style="margin-bottom: 20px;">
                                        <span class="label" style="margin-bottom: 8px;">
                                            <?php echo Olama_School_Helpers::translate('Curriculum Material'); ?>:
                                        </span>
                                        <div class="curriculum-table-wrapper"
                                            style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                                                <thead>
                                                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                        <th style="padding: 10px; text-align: start; font-weight: 700; color: #475569;">
                                                            <?php echo Olama_School_Helpers::translate('Unit / Lesson'); ?>
                                                        </th>
                                                        <th style="padding: 10px; text-align: start; font-weight: 700; color: #475569;">
                                                            <?php echo Olama_School_Helpers::translate('Material'); ?>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($material['curriculum_items'] as $item):
                                                        $unit = !empty($item['unit_id']) ? Olama_School_Unit::get_unit($item['unit_id']) : null;
                                                        $lesson = !empty($item['lesson_id']) ? Olama_School_Lesson::get_lesson($item['lesson_id']) : null;
                                                        $unit_name = $unit ? $unit->unit_name : '';
                                                        $lesson_name = $lesson ? $lesson->lesson_title : '';
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                                            <td style="padding: 10px;">
                                                                <div style="font-weight: 700; color: #1e293b;">
                                                                    <?php echo esc_html($unit_name); ?>
                                                                </div>
                                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                                    <?php echo esc_html($lesson_name); ?>
                                                                </div>
                                                            </td>
                                                            <td style="padding: 10px; color: #334155;">
                                                                <?php echo esc_html($item['material']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="material-sections"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 0;">
                                    <?php if ($ex->student_book_material): ?>
                                        <div class="material-block" style="background:#f1f5f9; padding:10px; border-radius:4px;">
                                            <div class="material-label" style="font-weight:700; font-size:11px; color:#64748b;">
                                                <?php echo Olama_School_Helpers::translate('Student Book'); ?>
                                            </div>
                                            <div class="material-content">
                                                <?php echo esc_html($ex->student_book_material); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($ex->workbook_material): ?>
                                        <div class="material-block" style="background:#f1f5f9; padding:10px; border-radius:4px;">
                                            <div class="material-label" style="font-weight:700; font-size:11px; color:#64748b;">
                                                <?php echo Olama_School_Helpers::translate('Workbook'); ?>
                                            </div>
                                            <div class="material-content">
                                                <?php echo esc_html($ex->workbook_material); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($ex->exercise_book_material): ?>
                                        <div class="material-block" style="background:#f1f5f9; padding:10px; border-radius:4px;">
                                            <div class="material-label" style="font-weight:700; font-size:11px; color:#64748b;">
                                                <?php echo Olama_School_Helpers::translate('Exercise Notebook'); ?>
                                            </div>
                                            <div class="material-content">
                                                <?php echo esc_html($ex->exercise_book_material); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    $booklets_val = !empty($material['notebook_material']) ? $material['notebook_material'] : (!empty($ex->notebook_material) ? $ex->notebook_material : '');
                                    if (!empty($booklets_val)): ?>
                                        <div class="material-block" style="background:#f1f5f9; padding:10px; border-radius:4px;">
                                            <div class="material-label" style="font-weight:700; font-size:11px; color:#64748b;">
                                                <?php echo Olama_School_Helpers::translate('Booklets & Notebooks'); ?>
                                            </div>
                                            <div class="material-content">
                                                <?php echo esc_html($booklets_val); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $notes_val = !empty($material['teacher_notes']) ? $material['teacher_notes'] : (!empty($ex->teacher_notes) ? $ex->teacher_notes : '');
                                if (!empty($notes_val)): ?>
                                    <div class="teacher-notes-box"
                                        style="background:#fff7ed; border-right:4px solid #f97316; padding:10px; border-radius:4px;">
                                        <span class="label" style="color:#c2410c; font-weight:800; font-size:11px;">
                                            <?php echo Olama_School_Helpers::translate('Teacher Notes'); ?>:
                                        </span>
                                        <div class="value">
                                            <?php echo nl2br(esc_html($notes_val)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <style>
                .olama-exam-schedule-student {
                    max-width: 800px;
                    margin: 0 auto;
                    font-family: 'Tajawal', sans-serif;
                }

                .exam-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
                    color: #fff;
                    padding: 25px;
                    border-radius: 12px;
                    margin-bottom: 20px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                }

                .exam-name {
                    margin: 0;
                    font-size: 1.5rem;
                    font-weight: 800;
                }

                .exam-semester {
                    margin: 5px 0 0;
                    opacity: 0.8;
                    font-size: 0.9rem;
                }

                .exam-grade-badge {
                    background: rgba(255, 255, 255, 0.2);
                    padding: 5px 15px;
                    border-radius: 20px;
                    font-weight: 700;
                    font-size: 0.9rem;
                }

                .exam-list {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .exam-card {
                    background: #fff;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                    transition: all 0.2s;
                }

                .exam-card-header {
                    padding: 15px 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                    background: #fff;
                }

                .exam-card-header:hover {
                    background: #f8fafc;
                }

                .subject-info {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .subject-icon {
                    color: #3b82f6;
                }

                .subject-name {
                    font-weight: 700;
                    color: #1e293b;
                    font-size: 1.1rem;
                }

                .exam-meta {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }

                .exam-date {
                    font-weight: 600;
                    color: #64748b;
                    font-size: 0.9rem;
                }

                .toggle-icon {
                    transition: transform 0.3s;
                    color: #94a3b8;
                }

                .exam-card.active .toggle-icon {
                    transform: rotate(180deg);
                }

                .exam-card-content {
                    display: none;
                    padding: 20px;
                    background: #fdfdfd;
                    border-top: 1px solid #f1f5f9;
                }

                .exam-card.active .exam-card-content {
                    display: block;
                }

                .detail-row .label {
                    display: block;
                    font-weight: 700;
                    color: #475569;
                    font-size: 0.8rem;
                    margin-bottom: 4px;
                    text-transform: uppercase;
                }

                .detail-row .value {
                    color: #1e293b;
                    font-size: 0.95rem;
                    line-height: 1.6;
                }

                <?php if (Olama_School_Helpers::is_arabic()): ?>
                    .olama-exam-schedule-student {
                        direction: rtl;
                    }

                <?php endif; ?>
                @media (max-width: 600px) {
                    .exam-header {
                        flex-direction: column;
                        text-align: center;
                        gap: 15px;
                    }

                    .exam-card-header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }

                    .exam-meta {
                        width: 100%;
                        justify-content: space-between;
                        border-top: 1px solid #f1f5f9;
                        padding-top: 10px;
                    }
                }
            </style>
            <?php
            return ob_get_clean();
    }

    /**
     * Shortcode: [olama_attendance]
     */
    public function render_attendance_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'semester' => 'active',
            'grade' => 0,
            'year' => 0
        ), $atts);

        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate("Please log in to mark attendance.") . '</div>';
        }

        $active_year = Olama_School_Academic::get_active_year();
        $year_id = $active_year ? $active_year->id : 0;

        if ($atts['semester'] === 'active') {
            $semester = Olama_School_Academic::get_active_semester($year_id);
        } else {
            // Support passing semester ID directly
            $semester_id = intval($atts['semester']);
            $semester = Olama_School_Academic::get_semester($semester_id);
        }
        $semester_id = $semester ? $semester->id : 0;

        $teacher_id = get_current_user_id();
        $can_manage_all = Olama_School_Permissions::can('olama_manage_academic_assignment');

        $assignments = array();
        if ($can_manage_all) {
            // Admin/Supervisor can see all sections
            $sections = Olama_School_Section::get_sections_by_year($year_id);
            foreach ($sections as $sec) {
                // Map to assignment-like object for consistent UI
                $assignments[] = (object) array(
                    'section_id' => $sec->id,
                    'grade_name' => $sec->grade_name,
                    'section_name' => $sec->section_name
                );
            }
        } else {
            // Normal teacher sees only their assignments
            $raw_assignments = Olama_School_Teacher::get_teacher_academic_assignments($teacher_id, $year_id);
            // The get_teacher_academic_assignments might return different column names, let's normalize or use get_all_assignments
            // Re-checking class-teacher.php: get_all_assignments gives ids, get_teacher_academic_assignments gives names.
            // Let's use get_all_assignments and fetch names to be safe and consistent.
            $raw_assignments = Olama_School_Teacher::get_all_assignments($teacher_id, $year_id);
            foreach ($raw_assignments as $ra) {
                $sec = Olama_School_Section::get_section($ra->section_id);
                $grade = Olama_School_Grade::get_grade($ra->grade_id);
                $assignments[] = (object) array(
                    'section_id' => $ra->section_id,
                    'grade_name' => $grade ? $grade->grade_name : '',
                    'section_name' => $sec ? $sec->section_name : ''
                );
            }
        }

        $section_id = isset($_GET["section_id"]) ? intval($_GET["section_id"]) : 0;
        $attendance_date = isset($_GET["date"]) ? sanitize_text_field($_GET["date"]) : current_time("Y-m-d");

        // If no section selected, and assignments available, pick the first one
        if (!$section_id && !empty($assignments)) {
            $section_id = $assignments[0]->section_id;
        }

        $students = array();
        $attendance_records = array();
        $total_present = 0;
        $total_absent = 0;

        if ($section_id) {
            $students = Olama_School_Student::get_students(array(
                "academic_year_id" => $year_id,
                "section_id" => $section_id
            ));

            global $wpdb;
            $table = $wpdb->prefix . "olama_attendance";
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id as student_id, a.status 
                 FROM $table a
                 JOIN {$wpdb->prefix}olama_students s ON a.student_uid = s.student_uid
                 WHERE a.section_id = %d AND a.attendance_date = %s",
                $section_id,
                $attendance_date
            ));
            foreach ($results as $res) {
                $attendance_records[$res->student_id] = $res->status;
            }

            // Check if attendance sheet is completed
            $is_sheet_completed = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}olama_attendance_sheets 
                 WHERE section_id = %d AND attendance_date = %s",
                $section_id,
                $attendance_date
            ));

            // Calculate counts
            foreach ($students as $stu) {
                $status = $attendance_records[$stu->id] ?? "present";
                if ($status === 'present') {
                    $total_present++;
                } else {
                    $total_absent++;
                }
            }
        }

        ob_start();
        ?>
            <div class="olama-attendance-shortcode" dir="rtl">
                <div class="olama-attendance-header">
                    <h2>
                        <?php echo Olama_School_Helpers::translate("Daily Attendance"); ?>
                    </h2>
                    <div class="olama-attendance-meta">
                        <span class="olama-meta-item"><i class="material-icons">calendar_today</i>
                            <?php echo date_i18n("l, j F Y", strtotime($attendance_date)); ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($assignments)): ?>
                    <div class="olama-section-selector">
                        <?php
                        // Deduplicate sections if any (might happen if teacher assigned multiple subjects in same section)
                        $seen_sections = array();
                        foreach ($assignments as $asgn):
                            if (in_array($asgn->section_id, $seen_sections))
                                continue;
                            $seen_sections[] = $asgn->section_id;
                            ?>
                            <a href="<?php echo add_query_arg("section_id", $asgn->section_id); ?>" class="olama-section-chip
                <?php echo $section_id == $asgn->section_id ? "olama-active" : ""; ?>">
                                <?php echo esc_html($asgn->grade_name . " - " . $asgn->section_name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($section_id): ?>
                    <div class="olama-attendance-summary-cards">
                        <div class="olama-summary-card olama-present">
                            <div class="olama-card-label">
                                <span class="olama-label-ar">حاضر</span>
                                <span class="olama-label-en">(Present)</span>
                            </div>
                            <div class="olama-card-value" id="total-present">
                                <?php echo $total_present; ?>
                            </div>
                        </div>
                        <div class="olama-summary-card olama-absent">
                            <div class="olama-card-label">
                                <span class="olama-label-ar">غائب</span>
                                <span class="olama-label-en">(Absent)</span>
                            </div>
                            <div class="olama-card-value" id="total-absent">
                                <?php echo $total_absent; ?>
                            </div>
                        </div>
                    </div>

                    <div class="olama-attendance-actions" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" class="olama-btn-all-present" id="mark-all-present" 
                                data-section="<?php echo $section_id; ?>" data-year="<?php echo $year_id; ?>" data-date="<?php echo $attendance_date; ?>">
                            <i class="material-icons">done_all</i>
                            <?php echo Olama_School_Helpers::translate("All Present"); ?>
                        </button>

                        <?php if ($is_sheet_completed): ?>
                            <div class="olama-attendance-status-badge completed">
                                <i class="material-icons">check_circle</i>
                                <?php echo Olama_School_Helpers::translate("Attendance Completed"); ?>
                            </div>
                        <?php else: ?>
                            <div class="olama-attendance-status-badge pending">
                                <i class="material-icons">history</i>
                                <?php echo Olama_School_Helpers::translate("Attendance Pending"); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="olama-students-grid">
                        <?php foreach ($students as $stu):
                            $status = $attendance_records[$stu->id] ?? "present";
                            ?>
                            <div class="olama-attendance-btn olama-<?php echo $status; ?>" data-student="<?php echo $stu->id; ?>"
                                data-section="<?php echo $section_id; ?>" data-year="<?php echo $year_id; ?>"
                                data-semester="<?php echo $semester_id; ?>" data-date="<?php echo $attendance_date; ?>">
                                <div class="olama-student-name">
                                    <?php echo esc_html($stu->student_name); ?>
                                </div>
                                <div class="olama-student-status">
                                    <span class="olama-status-present">
                                        <?php echo Olama_School_Helpers::translate("Present"); ?>
                                    </span>
                                    <span class="olama-status-absent">
                                        <?php echo Olama_School_Helpers::translate("Absent"); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="olama-attendance-footer">
                        <p class="hint">💡
                            <?php echo Olama_School_Helpers::translate("Click on a student name to mark them as absent (Red) or present (Blue). Changes are saved instantly."); ?>
                        </p>
                    </div>

                    <script>
                        jQuery(document).ready(function ($) {
                            $(".olama-attendance-btn").on("click", function () {
                                var $btn = $(this);
                                var studentId = $btn.data("student");
                                var sectionId = $btn.data("section");
                                var yearId = $btn.data("year");
                                var semId = $btn.data("semester");
                                var date = $btn.data("date");
                                var currentStatus = $btn.hasClass("olama-present") ? "present" : "absent";
                                var newStatus = currentStatus === "present" ? "absent" : "present";

                                $btn.addClass("olama-loading");

                                $.post(olama_admin_ajax.ajax_url, {
                                    action: "olama_save_attendance",
                                    nonce: olama_admin_ajax.nonce,
                                    student_id: studentId,
                                    status: newStatus,
                                    section_id: sectionId,
                                    academic_year_id: yearId,
                                    semester_id: semId,
                                    date: date
                                }, function (response) {
                                    $btn.removeClass("olama-loading");
                                    if (response.success) {
                                        $btn.removeClass("olama-present olama-absent").addClass("olama-" + newStatus);
                                        
                                        // Update status badge dynamically
                                        var $badge = $(".olama-attendance-status-badge.pending");
                                        if ($badge.length) {
                                            $badge.removeClass("pending").addClass("completed")
                                                .html('<i class="material-icons">check_circle</i> <?php echo Olama_School_Helpers::translate("Attendance Completed"); ?>');
                                        }

                                        // Update counts
                                        var presentCount = $(".olama-attendance-btn.olama-present").length;
                                        var absentCount = $(".olama-attendance-btn.olama-absent").length;
                                        $("#total-present").text(presentCount);
                                        $("#total-absent").text(absentCount);
                                    } else {
                                        alert("Error: " + response.data);
                                    }
                                });
                            });

                            $("#mark-all-present").on("click", function () {
                                if (!confirm("<?php echo Olama_School_Helpers::translate("Are you sure everyone is present? This will reset all current marks for this section today."); ?>")) {
                                    return;
                                }

                                var $btn = $(this);
                                var sectionId = $btn.data("section");
                                var yearId = $btn.data("year");
                                var date = $btn.data("date");

                                $btn.prop("disabled", true).css("opacity", "0.5");

                                $.post(olama_admin_ajax.ajax_url, {
                                    action: "olama_mark_all_present",
                                    nonce: olama_admin_ajax.nonce,
                                    section_id: sectionId,
                                    academic_year_id: yearId,
                                    date: date
                                }, function (response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert("Error: " + response.data);
                                        $btn.prop("disabled", false).css("opacity", "1");
                                    }
                                });
                            });
                        });
                    </script>

                    <style>
                        .olama-attendance-shortcode {
                            font-family: "Tajawal", sans-serif;
                            max-width: 800px;
                            margin: 0 auto;
                            background: #f8fafc;
                            padding: 20px;
                            border-radius: 16px;
                        }

                        /* Attendance Summary Cards */
                        .olama-attendance-summary-cards {
                            display: flex;
                            gap: 15px;
                            margin-bottom: 25px;
                            direction: rtl;
                        }

                        .olama-summary-card {
                            flex: 1;
                            padding: 15px 20px;
                            border-radius: 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                            border: 1px solid rgba(0, 0, 0, 0.05);
                        }

                        .olama-summary-card.olama-present {
                            background: #e6fffa;
                            border-color: #b2f5ea;
                        }

                        .olama-summary-card.olama-absent {
                            background: #fff5f5;
                            border-color: #fed7d7;
                        }

                        .olama-summary-card .olama-card-label {
                            display: flex;
                            flex-direction: column;
                            gap: 2px;
                        }

                        .olama-summary-card .olama-label-ar {
                            font-size: 1.1rem;
                            font-weight: 800;
                        }

                        .olama-summary-card.olama-present .olama-label-ar {
                            color: #2c7a7b;
                        }

                        .olama-summary-card.olama-absent .olama-label-ar {
                            color: #c53030;
                        }

                        .olama-summary-card .olama-label-en {
                            font-size: 0.8rem;
                            font-weight: 600;
                            opacity: 0.7;
                        }

                        .olama-summary-card.olama-present .olama-label-en {
                            color: #319795;
                        }

                        .olama-summary-card.olama-absent .olama-label-en {
                            color: #e53e3e;
                        }

                        .olama-summary-card .olama-card-value {
                            font-size: 2.2rem;
                            font-weight: 900;
                        }

                        .olama-summary-card.olama-present .olama-card-value {
                            color: #234e52;
                        }

                        .olama-summary-card.olama-absent .olama-card-value {
                            color: #742a2a;
                        }

                        @media (max-width: 480px) {
                            .olama-attendance-summary-cards {
                                flex-direction: column;
                            }
                        }

                        .olama-attendance-header {
                            margin-bottom: 25px;
                            text-align: center;
                        }

                        .olama-attendance-header h2 {
                            margin: 0 0 10px;
                            color: #1e293b;
                            font-size: 1.8rem;
                        }

                        .olama-attendance-meta {
                            color: #64748b;
                            display: flex;
                            justify-content: center;
                            gap: 20px;
                        }

                        .olama-meta-item {
                            display: flex;
                            align-items: center;
                            gap: 5px;
                        }

                        .olama-section-selector {
                            display: flex;
                            gap: 10px;
                            overflow-x: auto;
                            padding-bottom: 15px;
                            margin-bottom: 25px;
                            justify-content: flex-start;
                        }

                        .olama-section-chip {
                            padding: 8px 16px;
                            background: #fff;
                            border: 1px solid #e2e8f0;
                            border-radius: 20px;
                            text-decoration: none;
                            color: #475569;
                            white-space: nowrap;
                            font-weight: 500;
                            transition: all 0.2s;
                        }

                        .olama-section-chip.olama-active {
                            background: #3b82f6;
                            color: #fff;
                            border-color: #3b82f6;
                        }

                        .olama-students-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                            gap: 12px;
                        }

                        .olama-attendance-btn {
                            padding: 15px 10px;
                            background: #fff;
                            border-radius: 12px;
                            border: 2px solid #e2e8f0;
                            cursor: pointer;
                            text-align: center;
                            transition: all 0.2s;
                            position: relative;
                        }

                        .olama-attendance-btn.olama-present {
                            border-color: #3b82f6;
                            background: #eff6ff;
                        }

                        .olama-attendance-btn.olama-absent {
                            border-color: #ef4444;
                            background: #fef2f2;
                        }

                        .olama-student-name {
                            font-weight: 600;
                            color: #1e293b;
                            margin-bottom: 8px;
                            font-size: 0.95rem;
                            line-height: 1.3;
                        }

                        .olama-student-status {
                            font-size: 0.8rem;
                            font-weight: 700;
                            text-transform: uppercase;
                        }

                        .olama-attendance-btn.present .olama-status-absent {
                            display: none;
                        }

                        .olama-attendance-btn.present .olama-status-present {
                            color: #3b82f6;
                        }

                        .olama-attendance-btn.absent .olama-status-present {
                            display: none;
                        }

                        .olama-attendance-btn.absent .olama-status-absent {
                            color: #ef4444;
                        }

                        .olama-attendance-btn.olama-loading {
                            opacity: 0.6;
                            pointer-events: none;
                        }

                        /* All Present Button */
                        .olama-btn-all-present {
                            display: flex;
                            align-items: center;
                            gap: 8px;
                            padding: 10px 20px;
                            background: #10b981;
                            color: #fff;
                            border: none;
                            border-radius: 12px;
                            font-weight: 700;
                            cursor: pointer;
                            transition: all 0.2s;
                            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
                        }

                        .olama-btn-all-present:hover {
                            background: #059669;
                            transform: translateY(-2px);
                        }

                        .olama-btn-all-present .material-icons {
                            font-size: 20px;
                        }

                        .olama-attendance-status-badge {
                            display: flex;
                            align-items: center;
                            gap: 6px;
                            padding: 8px 16px;
                            border-radius: 20px;
                            font-size: 0.9rem;
                            font-weight: 700;
                        }

                        .olama-attendance-status-badge.completed {
                            background: #d1fae5;
                            color: #065f46;
                        }

                        .olama-attendance-status-badge.pending {
                            background: #fef3c7;
                            color: #92400e;
                        }

                        .olama-attendance-status-badge .material-icons {
                            font-size: 18px;
                        }

                        .olama-attendance-footer {
                            margin-top: 30px;
                            text-align: center;
                            color: #64748b;
                            font-size: 0.9rem;
                        }

                        @media (max-width: 480px) {
                            .olama-students-grid {
                                grid-template-columns: repeat(2, 1fr);
                            }
                        }
                    </style>
                    <?php
                endif;
                return ob_get_clean();
    }

    /**
     * Shortcode: [olama_family_performance]
     * Displays evaluation results for a logged-in parent's children.
     * Attributes: semester (default: active)
     */
    public function render_family_performance_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'semester' => '',
        ), $atts, 'olama_family_performance');

        // Must be logged in
        if (!is_user_logged_in()) {
            return '<div class="olama-fp-login-msg" dir="rtl" style="text-align:center;padding:40px 20px;background:#fff1f2;border-radius:16px;color:#b91c1c;font-weight:700;font-size:1.1rem;font-family:Tajawal,sans-serif;">' .
                '<span class="material-icons" style="font-size:48px;display:block;margin:0 auto 12px;opacity:.6;">lock</span>' .
                Olama_School_Helpers::translate('Please log in to view your children\'s performance.') .
                '</div>';
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $family_uid = $user ? $user->user_login : '';

        if (empty($family_uid)) {
            return '<div class="olama-fp-login-msg" dir="rtl" style="text-align:center;padding:40px 20px;background:#fefce8;border-radius:16px;color:#854d0e;font-weight:700;font-size:1.1rem;font-family:Tajawal,sans-serif;">' .
                '<span class="material-icons" style="font-size:48px;display:block;margin:0 auto 12px;opacity:.6;">family_restroom</span>' .
                Olama_School_Helpers::translate('Your account is not linked to a family. Please contact the school administration.') .
                '</div>';
        }

        // Get family record — query by family_uid directly
        // (get_family() treats numeric strings as 'id' which causes mismatches)
        global $wpdb;
        $family = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
            $family_uid
        ));
        if (!$family) {
            return '<div class="olama-fp-login-msg" dir="rtl" style="text-align:center;padding:40px 20px;background:#fff1f2;border-radius:16px;color:#b91c1c;font-weight:700;">' .
                Olama_School_Helpers::translate('Family record not found.') . '</div>';
        }

        // Get active year & semester
        $active_year = Olama_School_Academic::get_active_year();
        $academic_year_id = $active_year ? $active_year->id : 0;
        $year_name = $active_year ? $active_year->year_name : '';

        $semester_id = $atts['semester'];
        if ($semester_id === 'active' || empty($semester_id)) {
            $active_semester = $academic_year_id ? Olama_School_Academic::get_active_semester($academic_year_id) : null;
            $semester_id = $active_semester ? $active_semester->id : 0;
        } else {
            $semester_id = intval($semester_id);
        }
        $semester_obj = $semester_id ? Olama_School_Academic::get_semester($semester_id) : null;
        $semester_name = $semester_obj ? $semester_obj->semester_name : '';

        // Get family's students
        $students = Olama_School_Family::get_family_students($family_uid);
        
        // Filter by student_uid if provided via attribute or query string
        $target_uid = $atts['uid'] ?? $atts['student_uid'] ?? ($_GET['student_uid'] ?? '');
        if (!empty($target_uid)) {
            $students = array_filter($students, function($s) use ($target_uid) {
                return $s->student_uid === $target_uid;
            });
        }

        if (empty($students)) {
            return '<div class="olama-fp-login-msg" dir="rtl" style="text-align:center;padding:40px 20px;background:#fefce8;border-radius:16px;color:#854d0e;font-weight:700;font-family:Tajawal,sans-serif;">' .
                '<span class="material-icons" style="font-size:48px;display:block;margin:0 auto 12px;opacity:.6;">school</span>' .
                Olama_School_Helpers::translate('No matching student found for this family.') . '</div>';
        }

        // Build data for each student
        $students_data = array();
        foreach ($students as $student) {
            $enrollment = Olama_School_Student::get_student_enrollment($student->id, $academic_year_id);
            if (!$enrollment)
                continue;

            $section = Olama_School_Section::get_section($enrollment->section_id);
            $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;
            if (!$grade)
                continue;

            // Get evaluation templates for this grade/year/semester
            $templates = Olama_School_EV_Template::get_templates($grade->id, $academic_year_id, $semester_id, 'student');
            // Also try templates with semester_id=0 (semester-agnostic)
            if (empty($templates)) {
                $templates = Olama_School_EV_Template::get_templates($grade->id, $academic_year_id, 0, 'student');
            }

            $evals = array();
            $overall_scores = array();

            foreach ($templates as $template) {
                $evaluation = Olama_School_EV_Record::get_evaluation(
                    $student->id,
                    $academic_year_id,
                    $semester_id,
                    $template->id,
                    'student'
                );

                if (!$evaluation)
                    continue;

                $scores = Olama_School_EV_Record::get_scores($evaluation->id);
                $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($template->id);
                $score_config = Olama_School_EV_Template::get_score_config($template->id);

                // Fetch subject name
                $subject_name = Olama_School_Helpers::translate('General');
                if ($template->subject_id) {
                    $subject = Olama_School_Subject::get_subject($template->subject_id);
                    if ($subject) {
                        $subject_name = $subject->subject_name;
                    }
                }

                // Build responses for scoring service
                $responses = array();
                foreach ($curriculum as $domain) {
                    foreach ($domain->categories as $category) {
                        foreach ($category->indicators as $indicator) {
                            if (isset($scores[$indicator->id]) && !is_null($scores[$indicator->id]->score)) {
                                $responses[] = array(
                                    'rating' => (int) $scores[$indicator->id]->score,
                                    'weight' => (float) $indicator->weight,
                                    'is_critical' => (bool) $indicator->is_critical
                                );
                            }
                        }
                    }
                }

                $result = \Olama\Services\EvaluationScoringService::calculate_score($responses);
                $percentage = $result['percentage'];
                $overall_scores[] = $percentage;

                // Get teacher name
                $teacher_name = '';
                if ($evaluation->teacher_id) {
                    $teacher_user = get_userdata($evaluation->teacher_id);
                    $teacher_name = $teacher_user ? $teacher_user->display_name : '';
                }

                $evals[] = array(
                    'template_name' => $template->template_name,
                    'subject_name' => $subject_name,
                    'template_id' => $template->id,
                    'percentage' => $percentage,
                    'status' => $evaluation->status,
                    'date' => $evaluation->updated_at ?? $evaluation->created_at,
                    'teacher_name' => $teacher_name,
                    'curriculum' => $curriculum,
                    'scores' => $scores,
                    'score_config' => $score_config,
                );
            }

            if (empty($evals))
                continue;

            // Compute average mastery
            $avg_mastery = count($overall_scores) > 0 ? round(array_sum($overall_scores) / count($overall_scores)) : 0;

            // Calculate age from dob
            $age = '';
            if (!empty($student->dob)) {
                $dob = new DateTime($student->dob);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
            }

            $students_data[] = array(
                'student' => $student,
                'grade_name' => $grade->grade_name,
                'section_name' => $section->section_name,
                'age' => $age,
                'avg_mastery' => $avg_mastery,
                'evaluations' => $evals,
            );
        }

        if (empty($students_data)) {
            return '<div class="olama-fp-login-msg" dir="rtl" style="text-align:center;padding:40px 20px;background:#fefce8;border-radius:16px;color:#854d0e;font-weight:700;font-family:Tajawal,sans-serif;">' .
                '<span class="material-icons" style="font-size:48px;display:block;margin:0 auto 12px;opacity:.6;">assignment</span>' .
                Olama_School_Helpers::translate('No evaluations found for this semester yet.') . '</div>';
        }

        // Helper: get mastery color and label
        $get_mastery_class = function ($pct) {
            if ($pct >= 85)
                return 'fp-excellent';
            if ($pct >= 70)
                return 'fp-good';
            if ($pct >= 50)
                return 'fp-fair';
            return 'fp-weak';
        };
        $get_mastery_label = function ($pct) {
            if ($pct >= 85)
                return 'ممتاز';
            if ($pct >= 70)
                return 'جيد';
            if ($pct >= 50)
                return 'مقبول';
            return 'يحتاج تحسين';
        };

        // Get score label from config
        $get_score_label = function ($score, $config) {
            if (is_null($score) || $score === '')
                return '—';
            return isset($config[(int) $score]) ? $config[(int) $score] : $score;
        };
        $get_score_class = function ($score, $max_score) {
            if (is_null($score) || $score === '')
                return 'fp-score-na';
            $ratio = $max_score > 0 ? ($score / $max_score) : 0;
            if ($ratio >= 0.8)
                return 'fp-score-high';
            if ($ratio >= 0.5)
                return 'fp-score-mid';
            return 'fp-score-low';
        };

        ob_start();
        ?>
                <style>
                    /* Family Performance Dashboard - Inline Styles */
                    .olama-family-perf {
                        font-family: 'Tajawal', 'Inter', sans-serif;
                        max-width: 900px;
                        margin: 0 auto;
                        padding: 16px;
                        direction: rtl;
                        background: #f0f4f8;
                        min-height: 100vh;
                        -webkit-font-smoothing: antialiased
                    }

                    .fp-hero {
                        background: linear-gradient(135deg, #0f766e 0%, #0d9488 30%, #14b8a6 60%, #2dd4bf 100%);
                        border-radius: 24px;
                        padding: 36px 28px 28px;
                        position: relative;
                        overflow: hidden;
                        margin-bottom: 20px;
                        box-shadow: 0 8px 32px rgba(13, 148, 136, .25)
                    }

                    .fp-hero::before {
                        content: '';
                        position: absolute;
                        top: -40px;
                        left: -40px;
                        width: 180px;
                        height: 180px;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, .08)
                    }

                    .fp-hero::after {
                        content: '';
                        position: absolute;
                        bottom: -30px;
                        right: -30px;
                        width: 140px;
                        height: 140px;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, .06)
                    }

                    .fp-hero-content {
                        display: flex;
                        align-items: center;
                        gap: 16px;
                        position: relative;
                        z-index: 2;
                        margin-bottom: 20px
                    }

                    .fp-hero-icon {
                        width: 56px;
                        height: 56px;
                        border-radius: 16px;
                        background: rgba(255, 255, 255, .2);
                        backdrop-filter: blur(10px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0
                    }

                    .fp-hero-icon .material-icons {
                        font-size: 30px;
                        color: #fff
                    }

                    .fp-hero-title {
                        font-size: 1.9rem;
                        font-weight: 900;
                        color: #fff;
                        margin: 0;
                        line-height: 1.2;
                        text-shadow: 0 2px 8px rgba(0, 0, 0, .1)
                    }

                    .fp-hero-subtitle {
                        font-family: 'Inter', sans-serif;
                        font-size: .85rem;
                        color: rgba(255, 255, 255, .75);
                        margin: 4px 0 0;
                        font-weight: 500;
                        letter-spacing: .5px
                    }

                    .fp-hero-meta {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px;
                        position: relative;
                        z-index: 2
                    }

                    .fp-meta-item {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        background: rgba(255, 255, 255, .18);
                        backdrop-filter: blur(8px);
                        padding: 6px 14px;
                        border-radius: 30px;
                        color: #fff;
                        font-size: .85rem;
                        font-weight: 600
                    }

                    .fp-meta-item .material-icons {
                        font-size: 16px;
                        opacity: .8
                    }

                    .fp-students-list {
                        display: flex;
                        flex-direction: column;
                        gap: 14px
                    }

                    .fp-student-card {
                        background: #fff;
                        border-radius: 20px;
                        overflow: hidden;
                        box-shadow: 0 2px 12px rgba(0, 0, 0, .04);
                        border: 1px solid #e5e9ef;
                        transition: box-shadow .3s ease, border-color .3s ease
                    }

                    .fp-student-card.active {
                        box-shadow: 0 8px 30px rgba(0, 0, 0, .08);
                        border-color: #d1d5db
                    }

                    .fp-student-header {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        padding: 16px 18px;
                        cursor: pointer;
                        transition: background .2s ease;
                        user-select: none
                    }

                    .fp-student-header:hover {
                        background: #f9fafb
                    }

                    .fp-student-avatar {
                        width: 46px;
                        height: 46px;
                        border-radius: 14px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 1.3rem;
                        font-weight: 800;
                        color: #fff;
                        flex-shrink: 0;
                        line-height: 1
                    }

                    .fp-student-avatar.fp-excellent {
                        background: linear-gradient(135deg, #059669, #10b981)
                    }

                    .fp-student-avatar.fp-good {
                        background: linear-gradient(135deg, #0284c7, #38bdf8)
                    }

                    .fp-student-avatar.fp-fair {
                        background: linear-gradient(135deg, #d97706, #fbbf24)
                    }

                    .fp-student-avatar.fp-weak {
                        background: linear-gradient(135deg, #dc2626, #f87171)
                    }

                    .fp-student-info {
                        flex: 1;
                        min-width: 0
                    }

                    .fp-student-name {
                        font-size: 1.05rem;
                        font-weight: 800;
                        color: #1e293b;
                        line-height: 1.3;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis
                    }

                    .fp-student-detail {
                        font-size: .8rem;
                        color: #64748b;
                        font-weight: 500;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        flex-wrap: wrap;
                        margin-top: 2px
                    }

                    .fp-age-badge {
                        background: #f1f5f9;
                        color: #475569;
                        padding: 1px 8px;
                        border-radius: 10px;
                        font-size: .72rem;
                        font-weight: 700
                    }

                    .fp-student-summary {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 4px;
                        flex-shrink: 0
                    }

                    .fp-mastery-ring {
                        width: 46px;
                        height: 46px;
                        position: relative
                    }

                    .fp-mastery-ring svg {
                        width: 100%;
                        height: 100%;
                        transform: rotate(-90deg)
                    }

                    .fp-ring-bg {
                        fill: none;
                        stroke: #e5e7eb;
                        stroke-width: 3
                    }

                    .fp-ring-fill {
                        fill: none;
                        stroke-width: 3;
                        stroke-linecap: round;
                        transition: stroke-dasharray .6s ease
                    }

                    .fp-mastery-ring.fp-excellent .fp-ring-fill {
                        stroke: #10b981
                    }

                    .fp-mastery-ring.fp-good .fp-ring-fill {
                        stroke: #38bdf8
                    }

                    .fp-mastery-ring.fp-fair .fp-ring-fill {
                        stroke: #fbbf24
                    }

                    .fp-mastery-ring.fp-weak .fp-ring-fill {
                        stroke: #f87171
                    }

                    .fp-ring-text {
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        font-size: .7rem;
                        font-weight: 800;
                        color: #334155;
                        font-family: 'Inter', sans-serif
                    }

                    .fp-mastery-badge {
                        font-size: .65rem;
                        font-weight: 700;
                        padding: 2px 8px;
                        border-radius: 8px;
                        white-space: nowrap
                    }

                    .fp-mastery-badge.fp-excellent {
                        background: #d1fae5;
                        color: #065f46
                    }

                    .fp-mastery-badge.fp-good {
                        background: #dbeafe;
                        color: #1e40af
                    }

                    .fp-mastery-badge.fp-fair {
                        background: #fef3c7;
                        color: #92400e
                    }

                    .fp-mastery-badge.fp-weak {
                        background: #fee2e2;
                        color: #991b1b
                    }

                    .fp-chevron {
                        color: #94a3b8;
                        font-size: 24px !important;
                        transition: transform .3s ease;
                        flex-shrink: 0
                    }

                    .fp-student-card.active .fp-chevron {
                        transform: rotate(180deg);
                        color: #0d9488
                    }

                    .fp-student-body {
                        display: none !important;
                        padding: 0 18px
                    }

                    .fp-student-card.active .fp-student-body {
                        display: block !important;
                        padding: 0 18px 20px
                    }

                    .fp-eval-pills {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        margin-bottom: 16px;
                        padding-top: 4px
                    }

                    .fp-eval-pill {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        padding: 6px 12px;
                        border-radius: 12px;
                        font-size: .78rem;
                        font-weight: 600;
                        cursor: pointer;
                        transition: transform .15s ease, box-shadow .15s ease
                    }

                    .fp-eval-pill:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 3px 8px rgba(0, 0, 0, .08)
                    }

                    .fp-eval-pill.fp-excellent {
                        background: #d1fae5;
                        color: #065f46
                    }

                    .fp-eval-pill.fp-good {
                        background: #dbeafe;
                        color: #1e40af
                    }

                    .fp-eval-pill.fp-fair {
                        background: #fef3c7;
                        color: #92400e
                    }

                    .fp-eval-pill.fp-weak {
                        background: #fee2e2;
                        color: #991b1b
                    }

                    .fp-pill-name {
                        white-space: nowrap
                    }

                    .fp-pill-pct {
                        font-family: 'Inter', sans-serif;
                        font-weight: 800;
                        font-size: .82rem
                    }

                    .fp-eval-tabs {
                        display: flex;
                        gap: 4px;
                        border-bottom: 2px solid #e5e7eb;
                        margin-bottom: 16px;
                        overflow-x: auto;
                        -webkit-overflow-scrolling: touch;
                        scrollbar-width: none;
                        padding-bottom: 0
                    }

                    .fp-eval-tabs::-webkit-scrollbar {
                        display: none
                    }

                    .fp-eval-tab {
                        padding: 10px 16px;
                        font-size: .82rem;
                        font-weight: 700;
                        font-family: 'Tajawal', sans-serif;
                        color: #64748b;
                        background: none;
                        border: none;
                        border-bottom: 3px solid transparent;
                        cursor: pointer;
                        white-space: nowrap;
                        transition: all .2s ease;
                        margin-bottom: -2px
                    }

                    .fp-eval-tab:hover {
                        color: #0d9488;
                        background: #f0fdfa;
                        border-radius: 8px 8px 0 0
                    }

                    .fp-eval-tab.active {
                        color: #0f766e;
                        border-bottom-color: #0d9488;
                        background: #f0fdfa;
                        border-radius: 8px 8px 0 0
                    }

                    .fp-eval-panel {
                        display: none
                    }

                    .fp-eval-panel.active {
                        display: block;
                        animation: fpFadeIn .3s ease
                    }

                    @keyframes fpFadeIn {
                        from {
                            opacity: 0;
                            transform: translateY(8px)
                        }

                        to {
                            opacity: 1;
                            transform: translateY(0)
                        }
                    }

                    .fp-eval-meta {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        margin-bottom: 16px
                    }

                    .fp-meta-chip {
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                        padding: 5px 12px;
                        border-radius: 10px;
                        font-size: .78rem;
                        font-weight: 600;
                        background: #f1f5f9;
                        color: #475569
                    }

                    .fp-meta-chip .material-icons {
                        font-size: 15px;
                        opacity: .7
                    }

                    .fp-meta-chip.fp-excellent {
                        background: #d1fae5;
                        color: #065f46
                    }

                    .fp-meta-chip.fp-good {
                        background: #dbeafe;
                        color: #1e40af
                    }

                    .fp-meta-chip.fp-fair {
                        background: #fef3c7;
                        color: #92400e
                    }

                    .fp-meta-chip.fp-weak {
                        background: #fee2e2;
                        color: #991b1b
                    }

                    .fp-domain-block {
                        margin-bottom: 16px;
                        background: #f8fafb;
                        border-radius: 16px;
                        border: 1px solid #e8ecf0;
                        overflow: hidden
                    }

                    .fp-domain-title {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        padding: 12px 16px;
                        background: linear-gradient(135deg, #0f766e, #14b8a6);
                        color: #fff;
                        font-size: .88rem;
                        font-weight: 700
                    }

                    .fp-domain-title .material-icons {
                        font-size: 18px;
                        opacity: .85
                    }

                    .fp-category-block {
                        border-bottom: 1px solid #e8ecf0;
                        padding: 0
                    }

                    .fp-category-block:last-child {
                        border-bottom: none
                    }

                    .fp-category-title {
                        padding: 10px 16px;
                        font-size: .82rem;
                        font-weight: 700;
                        color: #0f766e;
                        background: #eef7f6;
                        border-bottom: 1px dashed #d1e3e0
                    }

                    .fp-indicators {
                        padding: 0
                    }

                    .fp-indicator-row {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 10px 16px;
                        border-bottom: 1px solid #f1f4f6;
                        transition: background .15s ease
                    }

                    .fp-indicator-row:last-child {
                        border-bottom: none
                    }

                    .fp-indicator-row:hover {
                        background: #f0fdfa
                    }

                    .fp-indicator-text {
                        flex: 1;
                        font-size: .82rem;
                        color: #334155;
                        line-height: 1.5;
                        text-align: right
                    }

                    .fp-indicator-score {
                        flex-shrink: 0;
                        padding: 4px 12px;
                        border-radius: 10px;
                        font-size: .75rem;
                        font-weight: 700;
                        white-space: nowrap;
                        text-align: center;
                        min-width: 60px
                    }

                    .fp-indicator-score.fp-score-high {
                        background: #d1fae5;
                        color: #065f46
                    }

                    .fp-indicator-score.fp-score-mid {
                        background: #fef3c7;
                        color: #92400e
                    }

                    .fp-indicator-score.fp-score-low {
                        background: #fee2e2;
                        color: #991b1b
                    }

                    .fp-indicator-score.fp-score-na {
                        background: #f1f5f9;
                        color: #94a3b8
                    }

                    .fp-footer {
                        text-align: center;
                        padding: 24px 16px;
                        color: #94a3b8;
                        font-size: .8rem;
                        font-weight: 500;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px
                    }

                    .fp-footer .material-icons {
                        font-size: 16px;
                        color: #0d9488
                    }

                    @media(max-width:600px) {
                        .olama-family-perf {
                            padding: 10px
                        }

                        .fp-hero {
                            padding: 24px 18px 20px;
                            border-radius: 18px
                        }

                        .fp-hero-title {
                            font-size: 1.5rem
                        }

                        .fp-hero-icon {
                            width: 44px;
                            height: 44px;
                            border-radius: 12px
                        }

                        .fp-hero-icon .material-icons {
                            font-size: 24px
                        }

                        .fp-student-header {
                            padding: 12px 14px;
                            gap: 10px
                        }

                        .fp-student-avatar {
                            width: 40px;
                            height: 40px;
                            border-radius: 12px;
                            font-size: 1.1rem
                        }

                        .fp-student-name {
                            font-size: .95rem
                        }

                        .fp-mastery-ring {
                            width: 40px;
                            height: 40px
                        }

                        .fp-ring-text {
                            font-size: .62rem
                        }

                        .fp-mastery-badge {
                            font-size: .6rem;
                            padding: 1px 6px
                        }

                        .fp-student-body {
                            padding: 0 14px
                        }

                        .fp-student-card.active .fp-student-body {
                            display: block !important;
                            padding: 0 14px 16px
                        }

                        .fp-eval-pills {
                            gap: 6px
                        }

                        .fp-eval-pill {
                            padding: 5px 10px;
                            font-size: .72rem
                        }

                        .fp-eval-tab {
                            padding: 8px 12px;
                            font-size: .76rem
                        }

                        .fp-domain-title {
                            padding: 10px 12px;
                            font-size: .82rem
                        }

                        .fp-category-title {
                            padding: 8px 12px;
                            font-size: .78rem
                        }

                        .fp-indicator-row {
                            padding: 8px 12px;
                            flex-direction: column;
                            align-items: flex-start;
                            gap: 6px
                        }

                        .fp-indicator-text {
                            font-size: .78rem
                        }

                        .fp-indicator-score {
                            align-self: flex-start;
                            min-width: auto;
                            font-size: .7rem
                        }

                        .fp-eval-meta {
                            gap: 6px
                        }

                        .fp-meta-chip {
                            font-size: .72rem;
                            padding: 4px 10px
                        }
                    }
                </style>
                <div class="olama-family-perf" dir="rtl">
                    <!-- Hero Header -->
                    <div class="fp-hero">
                        <div class="fp-hero-content">
                            <div class="fp-hero-icon">
                                <span class="material-icons">family_restroom</span>
                            </div>
                            <div class="fp-hero-text">
                                <h1 class="fp-hero-title">أداء الطلاب</h1>
                                <p class="fp-hero-subtitle">Family Students Performance</p>
                            </div>
                        </div>
                        <div class="fp-hero-meta">
                            <div class="fp-meta-item">
                                <span class="material-icons">badge</span>
                                <span>
                                    <?php echo esc_html($family->family_name); ?>
                                </span>
                            </div>
                            <div class="fp-meta-item">
                                <span class="material-icons">calendar_today</span>
                                <span>
                                    <?php echo esc_html($year_name . ' — ' . $semester_name); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Students Accordion -->
                    <div class="fp-students-list">
                        <?php foreach ($students_data as $si => $sdata):
                            $s = $sdata['student'];
                            $mastery_class = $get_mastery_class($sdata['avg_mastery']);
                            $mastery_label = $get_mastery_label($sdata['avg_mastery']);
                            // Find weakest evaluation index
                            $weakest_idx = 0;
                            $weakest_pct = 999;
                            foreach ($sdata['evaluations'] as $ei => $ev) {
                                if ($ev['percentage'] < $weakest_pct) {
                                    $weakest_pct = $ev['percentage'];
                                    $weakest_idx = $ei;
                                }
                            }
                            ?>
                            <div class="fp-student-card" data-student-index="<?php echo $si; ?>">
                                <!-- Student Header (always visible) -->
                                <div class="fp-student-header">
                                    <div class="fp-student-avatar <?php echo $mastery_class; ?>">
                                        <?php echo mb_substr($s->student_name, 0, 1, 'UTF-8'); ?>
                                    </div>
                                    <div class="fp-student-info">
                                        <div class="fp-student-name">
                                            <?php echo esc_html($s->student_name); ?>
                                        </div>
                                        <div class="fp-student-detail">
                                            <?php echo esc_html($sdata['grade_name'] . ' — ' . $sdata['section_name']); ?>
                                            <?php if ($sdata['age']): ?>
                                                <span class="fp-age-badge">
                                                    <?php echo $sdata['age']; ?> سنة
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="fp-student-summary">
                                        <div class="fp-mastery-ring <?php echo $mastery_class; ?>">
                                            <svg viewBox="0 0 36 36">
                                                <path class="fp-ring-bg"
                                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                                <path class="fp-ring-fill"
                                                    stroke-dasharray="<?php echo $sdata['avg_mastery']; ?>, 100"
                                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                            </svg>
                                            <span class="fp-ring-text">
                                                <?php echo $sdata['avg_mastery']; ?>%
                                            </span>
                                        </div>
                                        <span class="fp-mastery-badge <?php echo $mastery_class; ?>">
                                            <?php echo $mastery_label; ?>
                                        </span>
                                    </div>
                                    <span class="material-icons fp-chevron">expand_more</span>
                                </div>

                                <!-- Expanded Content -->
                                <div class="fp-student-body">
                                    <!-- Evaluation mini summary pills -->
                                    <div class="fp-eval-pills">
                                        <?php foreach ($sdata['evaluations'] as $ei => $ev):
                                            $ev_class = $get_mastery_class($ev['percentage']);
                                            ?>
                                            <div class="fp-eval-pill <?php echo $ev_class; ?>" data-tab-index="<?php echo $ei; ?>"
                                                style="flex-direction: column; align-items: flex-start; gap: 2px;">
                                                <div
                                                    style="display: flex; justify-content: space-between; width: 100%; align-items: center; gap: 8px;">
                                                    <span class="fp-pill-name" style="font-weight: 800;">
                                                        <?php echo esc_html($ev['subject_name']); ?>
                                                    </span>
                                                    <span class="fp-pill-pct">
                                                        <?php echo round($ev['percentage']); ?>%
                                                    </span>
                                                </div>
                                                <div class="fp-pill-desc" style="font-size: 0.7rem; opacity: 0.8; font-weight: 500;">
                                                    <?php echo esc_html($ev['template_name']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Evaluation Tabs -->
                                    <div class="fp-eval-tabs">
                                        <?php foreach ($sdata['evaluations'] as $ei => $ev): ?>
                                            <button class="fp-eval-tab <?php echo ($ei === $weakest_idx) ? 'active' : ''; ?>"
                                                data-tab-index="<?php echo $ei; ?>">
                                                <?php echo esc_html($ev['subject_name']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Tab Panels -->
                                    <?php foreach ($sdata['evaluations'] as $ei => $ev): ?>
                                        <div class="fp-eval-panel <?php echo ($ei === $weakest_idx) ? 'active' : ''; ?>"
                                            data-panel-index="<?php echo $ei; ?>">

                                            <!-- Evaluation meta -->
                                            <div class="fp-eval-meta">
                                                <?php if ($ev['teacher_name']): ?>
                                                    <div class="fp-meta-chip">
                                                        <span class="material-icons">person</span>
                                                        <?php echo esc_html($ev['teacher_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="fp-meta-chip">
                                                    <span class="material-icons">event</span>
                                                    <?php echo date_i18n('j F Y', strtotime($ev['date'])); ?>
                                                </div>
                                                <div class="fp-meta-chip <?php echo $get_mastery_class($ev['percentage']); ?>">
                                                    <span class="material-icons">insights</span>
                                                    <?php echo round($ev['percentage']); ?>%
                                                </div>
                                            </div>

                                            <!-- Domains -->
                                            <?php foreach ($ev['curriculum'] as $domain): ?>
                                                <div class="fp-domain-block">
                                                    <div class="fp-domain-title">
                                                        <span class="material-icons">folder_open</span>
                                                        <?php echo esc_html($domain->title_ar); ?>
                                                    </div>
                                                    <?php foreach ($domain->categories as $category): ?>
                                                        <div class="fp-category-block">
                                                            <div class="fp-category-title">
                                                                <?php echo esc_html($category->title_ar); ?>
                                                            </div>
                                                            <div class="fp-indicators">
                                                                <?php foreach ($category->indicators as $indicator):
                                                                    $score_obj = isset($ev['scores'][$indicator->id]) ? $ev['scores'][$indicator->id] : null;
                                                                    $raw_score = $score_obj ? $score_obj->score : null;
                                                                    $label = $get_score_label($raw_score, $ev['score_config']);
                                                                    $max_keys = array_keys($ev['score_config']);
                                                                    $max_score_val = !empty($max_keys) ? max($max_keys) : 5;
                                                                    $score_cls = $get_score_class($raw_score, $max_score_val);
                                                                    ?>
                                                                    <div class="fp-indicator-row">
                                                                        <div class="fp-indicator-text">
                                                                            <?php echo esc_html($indicator->indicator_text); ?>
                                                                        </div>
                                                                        <div class="fp-indicator-score <?php echo $score_cls; ?>">
                                                                            <?php echo esc_html($label); ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer -->
                    <div class="fp-footer">
                        <span class="material-icons">verified</span>
                        <?php echo Olama_School_Helpers::translate('Last updated'); ?>:
                        <?php echo date_i18n('j F Y — H:i'); ?>
                    </div>
                </div>
                <?php
                return ob_get_clean();
    }

    /**
     * Shortcode: [olama_logged_teacher_schedule]
     */
    public function render_logged_teacher_schedule_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please log in to view your schedule.') . '</div>';
        }

        $user_id = get_current_user_id();
        if (!current_user_can('olama_create_plans')) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('This feature is only available for teachers.') . '</div>';
        }

        $teacher_schedule = Olama_School_Admin::get_teacher_daily_schedule($user_id);

        ob_start();
        ?>
                <div class="olama-logged-teacher-schedule-wrap"
                    style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                    <h2 class="olama-schedule-title"
                        style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px; font-family: 'Tajawal', sans-serif;">
                        <span class="dashicons dashicons-calendar-alt" style="color: #2271b1;"></span>
                        <?php echo Olama_School_Helpers::translate('Today\'s Teaching Schedule'); ?>
                        <span class="olama-date-badge"
                            style="font-size: 0.6em; background: #f0f6fb; color: #2271b1; padding: 4px 10px; border-radius: 15px; font-weight: 700;">
                            <?php echo date_i18n('l, M j'); ?>
                        </span>
                    </h2>
                    <div style="margin-top: 20px;">
                        <?php if ($teacher_schedule): ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($teacher_schedule as $period): ?>
                                    <div
                                        style="display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #f9f9f9; border-radius: 8px; border-right: 4px solid #2271b1;">
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <div style="background: #f0f6fb; padding: 8px; border-radius: 8px;">
                                                <span class="dashicons dashicons-book-alt"
                                                    style="color: #2271b1; font-size: 20px; width: 20px; height: 20px;"></span>
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: #1d2327; font-size: 1.1em;">
                                                    <?php echo esc_html($period->subject_name); ?>
                                                </div>
                                                <div style="font-size: 0.9em; color: #666; font-weight: 500;">
                                                    <?php echo esc_html($period->grade_name . ' - ' . $period->section_name); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div
                                                style="background: #2271b1; color: #fff; width: 45px; height: 45px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(34, 113, 177, 0.2);">
                                                <span
                                                    style="font-size: 0.7em; text-transform: uppercase; font-weight: 700; opacity: 0.9; margin-bottom: -4px;">
                                                    <?php echo Olama_School_Helpers::translate('Period'); ?>
                                                </span>
                                                <span style="font-size: 1.4em; font-weight: 900;">
                                                    <?php echo $period->period_number; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: #999;">
                                <span class="dashicons dashicons-calendar"
                                    style="font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 10px;"></span>
                                <p><?php echo Olama_School_Helpers::translate('No classes scheduled for today.'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                return ob_get_clean();
    }

    /**
     * Shortcode: [olama_logged_user_shifts]
     */
    public function render_logged_user_shifts_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please log in to view your shifts.') . '</div>';
        }

        $user_id = get_current_user_id();
        $teacher_shifts = Olama_School_Shifts::get_teacher_weekly_shifts($user_id);

        ob_start();
        ?>
                <div class="olama-logged-user-shifts-wrap"
                    style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                    <h2
                        style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px; font-family: 'Tajawal', sans-serif;">
                        <span class="dashicons dashicons-clock" style="color: #d63638;"></span>
                        <?php echo Olama_School_Helpers::translate('My Weekly Shifts'); ?>
                    </h2>
                    <div class="olama-shifts-table-container" style="margin-top: 20px; overflow-x: auto;">
                        <?php if ($teacher_shifts): ?>
                            <table class="olama-shifts-table wp-list-table widefat fixed striped"
                                style="box-shadow: none; border: 1px solid #f0f0f1; border-collapse: collapse; width: 100%;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e9ecef;">
                                            <?php echo Olama_School_Helpers::translate('Day'); ?>
                                        </th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e9ecef;">
                                            <?php echo Olama_School_Helpers::translate('Slot'); ?>
                                        </th>
                                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e9ecef;">
                                            <?php echo Olama_School_Helpers::translate('Location'); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $days = array(
                                        0 => Olama_School_Helpers::translate('Sunday'),
                                        1 => Olama_School_Helpers::translate('Monday'),
                                        2 => Olama_School_Helpers::translate('Tuesday'),
                                        3 => Olama_School_Helpers::translate('Wednesday'),
                                        4 => Olama_School_Helpers::translate('Thursday')
                                    );
                                    foreach ($teacher_shifts as $shift): ?>
                                        <tr>
                                            <td style="padding: 12px; font-weight: 600; border-bottom: 1px solid #f0f0f1;">
                                                <?php echo isset($days[$shift->day_of_week]) ? $days[$shift->day_of_week] : $shift->day_of_week; ?>
                                            </td>
                                            <td style="padding: 12px; border-bottom: 1px solid #f0f0f1;">
                                                <div style="font-weight: 600;"><?php echo esc_html($shift->slot_label); ?></div>
                                                <div style="font-size: 0.85em; color: #666;">
                                                    <?php echo date('H:i', strtotime($shift->start_time)) . ' - ' . date('H:i', strtotime($shift->end_time)); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 12px; border-bottom: 1px solid #f0f0f1;">
                                                <div style="font-weight: 600;"><?php echo esc_html($shift->location_name); ?></div>
                                                <div style="font-size: 0.85em; color: #666;"><?php echo esc_html($shift->area_floor); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: #999;">
                                <span class="dashicons dashicons-clock"
                                    style="font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 10px;"></span>
                                <p><?php echo Olama_School_Helpers::translate('No shifts assigned for this week.'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                return ob_get_clean();
    }

    /**
     * Shortcode: [olama_family_gateway]
     * Family Gateway – Central hub for parents.
     * Hybrid approach: inline summary cards + link-out service cards.
     */
    public function render_family_gateway_shortcode($atts)
    {
        $atts = shortcode_atts(array(), $atts, 'olama_family_gateway');

        // ── Auth ────────────────────────────────────────────────
        if (!is_user_logged_in()) {
            return '<div class="fg-msg fg-msg-error" dir="rtl">' .
                '<span class="material-icons">lock</span>' .
                Olama_School_Helpers::translate('Please log in to view your family portal.') .
                '</div>';
        }

        $user = get_userdata(get_current_user_id());
        $family_uid = $user ? $user->user_login : '';

        global $wpdb;
        $family = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_families WHERE family_uid = %s",
            $family_uid
        ));

        if (!$family) {
            return '<div class="fg-msg fg-msg-warn" dir="rtl">' .
                '<span class="material-icons">family_restroom</span>' .
                Olama_School_Helpers::translate('Your account is not linked to a family. Please contact the school administration.') .
                '</div>';
        }

        // ── Academic context ────────────────────────────────────
        $active_year = Olama_School_Academic::get_active_year();
        $year_id = $active_year ? $active_year->id : 0;
        $year_name = $active_year ? $active_year->year_name : '';
        $active_semester = $year_id ? Olama_School_Academic::get_active_semester($year_id) : null;
        $semester_id = $active_semester ? $active_semester->id : 0;
        $semester_name = $active_semester ? $active_semester->semester_name : '';

        // ── Students ────────────────────────────────────────────
        $students_raw = Olama_School_Family::get_family_students($family_uid);
        if (empty($students_raw)) {
            return '<div class="fg-msg fg-msg-warn" dir="rtl">' .
                '<span class="material-icons">school</span>' .
                Olama_School_Helpers::translate('No students found for this family.') .
                '</div>';
        }

        // ── Service page URLs (configurable via settings) ───────
        $settings = get_option('olama_school_settings', array());
        $page_evaluation = $settings['fg_page_evaluation'] ?? '/family-evaluation/';
        $page_exams      = $settings['fg_page_exams'] ?? '/online-exams/';
        $page_weekly_plan = $settings['fg_page_weekly_plan'] ?? '/weekly-plan/';
        $page_exam_schedule = $settings['fg_page_exam_schedule'] ?? '/exam-schedule/';

        // ── Day names mapping ───────────────────────────────────
        $day_map = array(
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday',
            3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'
        );
        $today_num = intval(current_time('w'));
        $today_name = $day_map[$today_num] ?? '';
        $day_names_ar = array(
            'Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'
        );

        // ── Build per-student data ──────────────────────────────
        $students_data = array();
        $avatar_colors = array('#0d9488', '#6366f1', '#ea580c', '#0284c7', '#d946ef', '#dc2626');

        foreach ($students_raw as $idx => $student) {
            $enrollment = Olama_School_Student::get_student_enrollment($student->id, $year_id);
            if (!$enrollment) continue;

            $section = Olama_School_Section::get_section($enrollment->section_id);
            $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;
            if (!$grade) continue;

            // -- Evaluation avg --
            $eval_avg = null;
            $templates = Olama_School_EV_Template::get_templates($grade->id, $year_id, $semester_id, 'student');
            if (empty($templates)) {
                $templates = Olama_School_EV_Template::get_templates($grade->id, $year_id, 0, 'student');
            }
            $eval_scores = array();
            foreach ($templates as $tmpl) {
                $evaluation = Olama_School_EV_Record::get_evaluation($student->id, $year_id, $semester_id, $tmpl->id, 'student');
                if (!$evaluation) continue;
                $scores = Olama_School_EV_Record::get_scores($evaluation->id);
                $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($tmpl->id);
                $responses = array();
                foreach ($curriculum as $domain) {
                    foreach ($domain->categories as $cat) {
                        foreach ($cat->indicators as $ind) {
                            if (isset($scores[$ind->id]) && !is_null($scores[$ind->id]->score)) {
                                $responses[] = array(
                                    'rating' => (int)$scores[$ind->id]->score,
                                    'weight' => (float)$ind->weight,
                                    'is_critical' => (bool)$ind->is_critical
                                );
                            }
                        }
                    }
                }
                if (!empty($responses)) {
                    $result = \Olama\Services\EvaluationScoringService::calculate_score($responses);
                    $eval_scores[] = $result['percentage'];
                }
            }
            if (!empty($eval_scores)) {
                $eval_avg = round(array_sum($eval_scores) / count($eval_scores));
            }

            // -- Last online exam --
            $last_exam = $wpdb->get_row($wpdb->prepare(
                "SELECT a.percentage, a.result, e.title
                 FROM {$wpdb->prefix}olama_exam_attempts a
                 JOIN {$wpdb->prefix}olama_exam_exams e ON a.exam_id = e.id
                 WHERE a.student_uid = %s AND a.is_preview = 0 AND a.submitted_at IS NOT NULL
                 ORDER BY a.submitted_at DESC LIMIT 1",
                $student->student_uid
            ));

            // -- Attendance counts --
            $attendance = $wpdb->get_results($wpdb->prepare(
                "SELECT status, COUNT(*) as cnt
                 FROM {$wpdb->prefix}olama_attendance
                 WHERE student_uid = %s AND semester_id = %d
                 GROUP BY status",
                $student->student_uid, $semester_id
            ));
            $att_present = 0; $att_absent = 0; $att_late = 0;
            foreach ($attendance as $att) {
                if ($att->status === 'present') $att_present = (int)$att->cnt;
                elseif ($att->status === 'absent') $att_absent = (int)$att->cnt;
                elseif ($att->status === 'late') $att_late = (int)$att->cnt;
            }
            $att_total = $att_present + $att_absent + $att_late;

            // -- Bus info --
            $bus = Olama_School_Bus::get_student_bus($student->id, $year_id);

            // -- Today's timetable --
            $timetable = array();
            if ($today_name && $semester_id) {
                $schedule = Olama_School_Schedule::get_schedule($enrollment->section_id, $semester_id);
                if (isset($schedule[$today_name])) {
                    ksort($schedule[$today_name]);
                    foreach ($schedule[$today_name] as $period => $slot) {
                        $timetable[] = array(
                            'period' => $period,
                            'subject' => $slot->subject_name,
                            'color' => $slot->color_code ?? '#6366f1'
                        );
                    }
                }
            }

            // -- Age --
            $age = '';
            if (!empty($student->dob) && $student->dob !== '0000-00-00') {
                try {
                    $dob = new DateTime($student->dob);
                    $now = new DateTime();
                    $age = $now->diff($dob)->y;
                } catch (Exception $e) {
                    $age = ''; // Ignore malformed dates
                }
            }

            $students_data[] = array(
                'student' => $student,
                'grade' => $grade,
                'section' => $section,
                'enrollment' => $enrollment,
                'age' => $age,
                'eval_avg' => $eval_avg,
                'last_exam' => $last_exam,
                'att_present' => $att_present,
                'att_absent' => $att_absent,
                'att_late' => $att_late,
                'att_total' => $att_total,
                'bus' => $bus,
                'timetable' => $timetable,
                'color' => $avatar_colors[$idx % count($avatar_colors)],
            );
        }

        if (empty($students_data)) {
            return '<div class="fg-msg fg-msg-warn" dir="rtl">' .
                '<span class="material-icons">school</span>' .
                Olama_School_Helpers::translate('No enrolled students found for this semester.') .
                '</div>';
        }

        // ── Services array ──────────────────────────────────────
        $service_palette = array(
            array('color' => '#0d9488', 'gradient' => 'linear-gradient(135deg, #0d9488, #14b8a6)'),
            array('color' => '#6366f1', 'gradient' => 'linear-gradient(135deg, #6366f1, #818cf8)'),
            array('color' => '#0284c7', 'gradient' => 'linear-gradient(135deg, #0284c7, #38bdf8)'),
            array('color' => '#ea580c', 'gradient' => 'linear-gradient(135deg, #ea580c, #fb923c)'),
            array('color' => '#7c3aed', 'gradient' => 'linear-gradient(135deg, #7c3aed, #a78bfa)'),
            array('color' => '#db2777', 'gradient' => 'linear-gradient(135deg, #db2777, #f472b6)'),
        );

        $configured_services = $settings['fg_services'] ?? Olama_School_Helpers::get_default_gateway_services();
        $services = array();
        $is_ar = Olama_School_Helpers::is_arabic();

        if (is_array($configured_services) && !empty($configured_services)) {
            foreach ($configured_services as $idx => $s) {
                if (!is_array($s)) continue;

                // Ignore services that haven't been configured properly or are placeholders
                $sc = $s['shortcode'] ?? '';
                if (empty($sc) || $sc === '[shortcode_tag]') continue;

                $style = $service_palette[$idx % count($service_palette)];
                
                // Title detection with fallback
                $title_ar = $s['title_ar'] ?? '';
                $title_en = $s['title_en'] ?? '';
                $title = $is_ar ? ($title_ar ?: $title_en) : ($title_en ?: $title_ar);
                
                // Auto-detect parameters based on shortcode
                $param = '';
                $param_key = '';
                if (!empty($sc)) {
                    // Extract tag to check existence
                    $tag = preg_replace('/\[([^\s\]]+).*\]/', '$1', $sc);
                    // On some production servers, shortcode_exists might be unreliable if called too early or in specific contexts
                    // We only skip if it's definitely NOT registered AND not one of our known internal tags
                    $our_tags = ['olama_family_performance', 'olama_online_exams', 'olama_weekly_plan', 'olama_exam_report', 'olama_online_exams_schedule'];
                    if (!shortcode_exists($tag) && !in_array($tag, $our_tags)) {
                        continue;
                    }

                    if (strpos($sc, 'weekly_plan') !== false) {
                        $param = 'section_id';
                        $param_key = 'section_id';
                    } elseif (strpos($sc, 'exam_report') !== false || strpos($sc, 'exam_schedule') !== false || strpos($sc, 'olama_exam') !== false || strpos($sc, 'online_exams_schedule') !== false || strpos($sc, 'family_performance') !== false) {
                        $param = 'grade_id'; // Fixed to use grade_id to match gateway output
                        $param_key = 'grade_id';
                    }
                }

                $services[] = array(
                    'icon'      => !empty($s['icon']) ? $s['icon'] : 'extension',
                    'title'     => $title ?: Olama_School_Helpers::translate('Service'),
                    'subtitle'  => $title_en,
                    'url'       => $s['url'] ?? '#',
                    'color'     => $style['color'],
                    'gradient'  => $style['gradient'],
                    'param'     => $param,
                    'param_key' => $param_key
                );
            }
        }

        // ── Render ──────────────────────────────────────────────
        ob_start();
        ?>
        <style>
            /* ═══ Family Gateway ═══ */
            .olama-family-gateway{font-family:'Tajawal','Inter',sans-serif;max-width:900px;margin:0 auto;padding:16px;direction:rtl;-webkit-font-smoothing:antialiased}
            .fg-msg{text-align:center;padding:40px 20px;border-radius:16px;font-weight:700;font-size:1.1rem;font-family:'Tajawal',sans-serif;direction:rtl}
            .fg-msg .material-icons{font-size:48px;display:block;margin:0 auto 12px;opacity:.6}
            .fg-msg-error{background:#fff1f2;color:#b91c1c}
            .fg-msg-warn{background:#fefce8;color:#854d0e}

            /* Hero */
            .fg-hero{background:linear-gradient(135deg,#0f766e 0%,#0d9488 30%,#14b8a6 60%,#2dd4bf 100%);border-radius:24px;padding:32px 24px 24px;position:relative;overflow:hidden;margin-bottom:16px;box-shadow:0 8px 32px rgba(13,148,136,.25)}
            .fg-hero::before{content:'';position:absolute;top:-40px;left:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.08)}
            .fg-hero::after{content:'';position:absolute;bottom:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.06)}
            .fg-hero-top{display:flex;align-items:center;gap:14px;position:relative;z-index:2;margin-bottom:16px}
            .fg-hero-icon{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);display:flex;align-items:center;justify-content:center;flex-shrink:0}
            .fg-hero-icon .material-icons{font-size:28px;color:#fff}
            .fg-hero-title{font-size:1.7rem;font-weight:900;color:#fff;margin:0;line-height:1.2}
            .fg-hero-subtitle{font-size:.82rem;color:rgba(255,255,255,.75);margin:4px 0 0;font-weight:500}
            .fg-hero-meta{display:flex;flex-wrap:wrap;gap:8px;position:relative;z-index:2}
            .fg-meta-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.18);backdrop-filter:blur(8px);padding:5px 12px;border-radius:30px;color:#fff;font-size:.82rem;font-weight:600}
            .fg-meta-pill .material-icons{font-size:15px;opacity:.8}

            /* Student Tabs */
            .fg-tabs{display:flex;gap:12px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:4px 0 16px;margin:0 -4px 12px}
            .fg-tabs::-webkit-scrollbar{display:none}
            .fg-tab{display:flex;align-items:center;gap:10px;padding:12px 20px;border-radius:18px;border:2px solid #e2e8f0;background:#fff;cursor:pointer;transition:all .3s cubic-bezier(0.4, 0, 0.2, 1);white-space:nowrap;flex-shrink:0;font-family:'Tajawal',sans-serif;box-shadow:0 2px 4px rgba(0,0,0,0.02)}
            .fg-tab:hover{border-color:#cbd5e1;background:#f8fafc;transform:translateY(-2px)}
            .fg-tab.active{border-color:#0d9488;background:#f0fdfa;box-shadow:0 10px 15px -3px rgba(13,148,136,0.15)}
            @media (max-width: 639px) {
                .fg-tabs { flex-direction: column; overflow: visible; padding: 0; }
                .fg-tab { width: 100%; box-sizing: border-box; }
                .fg-hero { padding: 24px 20px; border-radius: 20px; }
                .fg-hero-title { font-size: 1.4rem; }
            }
            .fg-tab-avatar{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;color:#fff;flex-shrink:0}
            .fg-tab-info{display:flex;flex-direction:column}
            .fg-tab-name{font-size:.92rem;font-weight:800;color:#1e293b;line-height:1.2}
            .fg-tab-grade{font-size:.75rem;color:#64748b;font-weight:500}

            /* Panel */
            .fg-panel{display:none}
            .fg-panel.active{display:block;animation:fgFadeIn .3s ease}
            @keyframes fgFadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

            /* Summary Row */
            .fg-summary{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:10px;margin-bottom:16px}
            .fg-stat{background:#fff;border-radius:16px;padding:14px;border:1px solid #e5e9ef;display:flex;align-items:center;gap:10px;transition:box-shadow .2s}
            .fg-stat:hover{box-shadow:0 4px 12px rgba(0,0,0,.06)}
            .fg-stat-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
            .fg-stat-icon .material-icons{font-size:22px;color:#fff}
            .fg-stat-body{flex:1;min-width:0}
            .fg-stat-value{font-size:1.15rem;font-weight:800;color:#1e293b;font-family:'Inter',sans-serif;line-height:1.2}
            .fg-stat-label{font-size:.72rem;color:#64748b;font-weight:600;margin-top:1px}

            /* Timetable */
            .fg-timetable-section{background:#fff;border-radius:16px;padding:16px;border:1px solid #e5e9ef;margin-bottom:16px}
            .fg-timetable-header{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:.88rem;font-weight:700;color:#334155}
            .fg-timetable-header .material-icons{font-size:20px;color:#0d9488}
            .fg-timetable-grid{display:flex;flex-wrap:wrap;gap:8px}
            .fg-period{display:flex;align-items:center;gap:6px;padding:8px 12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;font-size:.82rem;font-weight:600;color:#334155;transition:transform .15s}
            .fg-period:hover{transform:translateY(-1px)}
            .fg-period-num{width:22px;height:22px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#fff;flex-shrink:0}
            .fg-period-name{white-space:nowrap}
            .fg-no-schedule{text-align:center;padding:16px;color:#94a3b8;font-size:.85rem}
            .fg-no-schedule .material-icons{font-size:32px;display:block;margin:0 auto 6px;opacity:.4}

            /* Service Cards */
            .fg-services-title{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:.88rem;font-weight:700;color:#334155}
            .fg-services-title .material-icons{font-size:20px;color:#6366f1}
            .fg-services{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px}
            .fg-service{display:flex;flex-direction:column;align-items:center;gap:8px;padding:20px 12px;border-radius:18px;background:#fff;border:1px solid #e5e9ef;text-decoration:none;transition:all .25s ease;text-align:center;cursor:pointer}
            .fg-service:hover{box-shadow:0 8px 24px rgba(0,0,0,.08);border-color:#d1d5db;transform:translateY(-2px)}
            .fg-service-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center}
            .fg-service-icon .material-icons{font-size:26px;color:#fff}
            .fg-service-name{font-size:.88rem;font-weight:800;color:#1e293b;line-height:1.3}
            .fg-service-sub{font-size:.72rem;color:#94a3b8;font-weight:500}

            /* Responsive */
            @media(max-width:600px){
                .fg-hero{padding:24px 16px 18px}
                .fg-hero-title{font-size:1.4rem}
                .fg-summary{grid-template-columns:repeat(2,1fr)}
                .fg-services{grid-template-columns:repeat(2,1fr)}
                .fg-tab{padding:8px 12px}
            }
            @media(max-width:380px){
                .fg-summary{grid-template-columns:1fr}
            }
        </style>

        <div class="olama-family-gateway">
            <!-- Hero -->
            <div class="fg-hero">
                <div class="fg-hero-top">
                    <div class="fg-hero-icon"><span class="material-icons">home</span></div>
                    <div>
                        <h2 class="fg-hero-title"><?php echo esc_html($family->family_name); ?></h2>
                        <div class="fg-hero-subtitle">Family Gateway</div>
                    </div>
                </div>
                <div class="fg-hero-meta">
                    <span class="fg-meta-pill"><span class="material-icons">calendar_today</span> <?php echo esc_html($year_name); ?></span>
                    <?php if ($semester_name): ?>
                        <span class="fg-meta-pill"><span class="material-icons">schedule</span> <?php echo esc_html($semester_name); ?></span>
                    <?php endif; ?>
                    <span class="fg-meta-pill"><span class="material-icons">people</span> <?php echo count($students_data); ?> <?php echo Olama_School_Helpers::translate('Students'); ?></span>
                </div>
            </div>

            <!-- Student Tabs -->
            <?php if (count($students_data) > 1): ?>
            <div class="fg-tabs">
                <?php foreach ($students_data as $i => $sd): ?>
                    <button class="fg-tab <?php echo $i === 0 ? 'active' : ''; ?>" data-fg-student="<?php echo $i; ?>">
                        <div class="fg-tab-avatar" style="background:<?php echo esc_attr($sd['color']); ?>">
                            <?php echo mb_substr($sd['student']->student_name, 0, 1); ?>
                        </div>
                        <div class="fg-tab-info">
                            <span class="fg-tab-name"><?php echo esc_html($sd['student']->student_name); ?></span>
                            <span class="fg-tab-grade"><?php echo esc_html($sd['grade']->grade_name . ' - ' . $sd['section']->section_name); ?></span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Per-Student Panels -->
            <?php foreach ($students_data as $i => $sd): ?>
            <div class="fg-panel <?php echo $i === 0 ? 'active' : ''; ?>" data-fg-panel="<?php echo $i; ?>">

                <!-- Summary Cards -->
                <div class="fg-summary">
                    <!-- Evaluation -->
                    <div class="fg-stat">
                        <div class="fg-stat-icon" style="background:linear-gradient(135deg,#059669,#10b981)">
                            <span class="material-icons">assignment</span>
                        </div>
                        <div class="fg-stat-body">
                            <div class="fg-stat-value"><?php echo $sd['eval_avg'] !== null ? $sd['eval_avg'] . '%' : '—'; ?></div>
                            <div class="fg-stat-label"><?php echo Olama_School_Helpers::translate('Evaluation'); ?></div>
                        </div>
                    </div>
                    <!-- Last Exam -->
                    <div class="fg-stat">
                        <div class="fg-stat-icon" style="background:linear-gradient(135deg,#6366f1,#818cf8)">
                            <span class="material-icons">quiz</span>
                        </div>
                        <div class="fg-stat-body">
                            <div class="fg-stat-value"><?php
                                if ($sd['last_exam']) {
                                    echo round($sd['last_exam']->percentage) . '%';
                                } else {
                                    echo '—';
                                }
                            ?></div>
                            <div class="fg-stat-label"><?php echo Olama_School_Helpers::translate('Last Exam'); ?></div>
                        </div>
                    </div>
                    <!-- Attendance -->
                    <div class="fg-stat">
                        <div class="fg-stat-icon" style="background:linear-gradient(135deg,#0284c7,#38bdf8)">
                            <span class="material-icons">how_to_reg</span>
                        </div>
                        <div class="fg-stat-body">
                            <div class="fg-stat-value"><?php
                                if ($sd['att_total'] > 0) {
                                    echo $sd['att_present'] . '/' . $sd['att_total'];
                                } else {
                                    echo '—';
                                }
                            ?></div>
                            <div class="fg-stat-label"><?php echo Olama_School_Helpers::translate('Attendance'); ?>
                                <?php if ($sd['att_absent'] > 0): ?>
                                    <span style="color:#ef4444;margin-right:4px">(<?php echo $sd['att_absent']; ?> <?php echo Olama_School_Helpers::translate('absent'); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Bus -->
                    <div class="fg-stat">
                        <div class="fg-stat-icon" style="background:linear-gradient(135deg,#ea580c,#fb923c)">
                            <span class="material-icons">directions_bus</span>
                        </div>
                        <div class="fg-stat-body">
                            <div class="fg-stat-value"><?php echo $sd['bus'] ? esc_html($sd['bus']->bus_number) : '—'; ?></div>
                            <div class="fg-stat-label"><?php echo Olama_School_Helpers::translate('Bus'); ?>
                                <?php if ($sd['bus'] && !empty($sd['bus']->plate_number)): ?>
                                    <span style="color:#64748b"> (<?php echo esc_html($sd['bus']->plate_number); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Timetable -->
                <div class="fg-timetable-section">
                    <div class="fg-timetable-header">
                        <span class="material-icons">view_timeline</span>
                        <?php echo Olama_School_Helpers::translate("جدول اليوم"); ?> — <?php echo esc_html($day_names_ar[$today_name] ?? $today_name); ?>
                    </div>
                    <?php if (!empty($sd['timetable'])): ?>
                        <div class="fg-timetable-grid">
                            <?php foreach ($sd['timetable'] as $t): ?>
                                <div class="fg-period">
                                    <span class="fg-period-num" style="background:<?php echo esc_attr($t['color']); ?>"><?php echo $t['period']; ?></span>
                                    <span class="fg-period-name"><?php echo esc_html($t['subject']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="fg-no-schedule">
                            <span class="material-icons">weekend</span>
                            <?php echo Olama_School_Helpers::translate('No classes scheduled for today.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="fg-services-title">
                    <span class="material-icons">apps</span>
                    <?php echo Olama_School_Helpers::translate('Individual Student Reports'); ?>
                </div>
                <div class="fg-services">
                    <?php foreach ($services as $svc):
                        $url = $svc['url'];

                        // Always append student_uid for specific filtering
                        $url = add_query_arg('student_uid', $sd['student']->student_uid, $url);
                // Append student-specific params if defined
                if (!empty($svc['param_key'])) {
                    $val = '';
                    if ($svc['param_key'] === 'section_id' && isset($sd['enrollment'])) {
                        $val = $sd['enrollment']->section_id;
                    } elseif ($svc['param_key'] === 'grade_id' && isset($sd['grade'])) {
                        $val = $sd['grade']->id;
                    }
                    if ($val) {
                        $url = add_query_arg($svc['param'] ?: $svc['param_key'], $val, $url);
                    }
                }
                    ?>
                        <a class="fg-service" href="<?php echo esc_url($url); ?>">
                            <div class="fg-service-icon" style="background:<?php echo esc_attr($svc['gradient']); ?>">
                                <span class="material-icons"><?php echo esc_html($svc['icon']); ?></span>
                            </div>
                            <span class="fg-service-name"><?php echo esc_html($svc['title']); ?></span>
                            <span class="fg-service-sub"><?php echo esc_html($svc['subtitle']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <script>
        (function(){
            var tabs = document.querySelectorAll('.fg-tab');
            var panels = document.querySelectorAll('.fg-panel');
            if (!tabs.length) return;
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    var idx = this.getAttribute('data-fg-student');
                    tabs.forEach(function(t){ t.classList.remove('active'); });
                    panels.forEach(function(p){ p.classList.remove('active'); });
                    this.classList.add('active');
                    var panel = document.querySelector('.fg-panel[data-fg-panel="'+idx+'"]');
                    if(panel) panel.classList.add('active');
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [olama_supervisor_visits]
     */
    public function render_supervisor_visit_schedule_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please log in to view your visit schedule.') . '</div>';
        }

        $user_id = get_current_user_id();
        if (!current_user_can('olama_create_plans')) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('This feature is only available for teachers.') . '</div>';
        }

        $upcoming_visits = \Olama\Services\SupervisorVisitService::get_teacher_upcoming_visits($user_id);

        ob_start();
        ?>
        <div class="olama-supervisor-visits-wrap" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; gap: 10px; font-family: 'Tajawal', sans-serif;">
                <span class="dashicons dashicons-businessman" style="color: #2271b1;"></span>
                <?php echo Olama_School_Helpers::translate('Upcoming Supervisor Visits'); ?>
            </h2>
            <div style="margin-top: 20px;">
                <?php if ($upcoming_visits): ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($upcoming_visits as $visit): ?>
                            <div style="padding: 15px; background: #f9f9f9; border-radius: 8px; border-right: 4px solid #2271b1;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                    <div style="font-weight: 700; color: #1d2327;">
                                        <?php echo date_i18n('Y-m-d', strtotime($visit->visit_date)); ?> 
                                        <span style="font-weight: 400; color: #666; font-size: 0.9em;">(<?php echo Olama_School_Helpers::translate($visit->day_name); ?>)</span>
                                    </div>
                                    <div style="background: #e7ffef; color: #00a32a; font-size: 0.75em; padding: 3px 8px; border-radius: 10px; font-weight: 700;">
                                        <?php printf(Olama_School_Helpers::translate('Period %d'), $visit->period_number); ?>
                                    </div>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span class="dashicons dashicons-book" style="font-size: 16px; width: 16px; height: 16px; color: #999; vertical-align: middle;"></span>
                                    <span style="font-size: 0.9em; font-weight: 600; color: #2271b1;"><?php echo esc_html($visit->subject_name); ?></span>
                                    <span style="font-size: 0.8em; color: #666;"> - <?php echo esc_html($visit->grade_name . ' (' . $visit->section_name . ')'); ?></span>
                                </div>
                                <div style="font-size: 0.85em; color: #50575e;">
                                    <span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px; color: #999; vertical-align: middle;"></span>
                                    <?php printf(Olama_School_Helpers::translate('Supervisor: %s'), esc_html($visit->supervisor_name)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: #999;">
                        <span class="dashicons dashicons-calendar" style="font-size: 40px; width: 40px; height: 40px; opacity: 0.3; margin-bottom: 10px;"></span>
                        <p><?php echo Olama_School_Helpers::translate('No upcoming supervisor visits.'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [olama_online_exams]
     * Alias for [olama_exam] from the Exam Engine plugin.
     */
    public function render_online_exams_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please log in to access your exams.') . '</div>';
        }

        // This relies on the olama-exam-engine plugin being active.
        if (shortcode_exists('olama_exam')) {
            return do_shortcode('[olama_exam]');
        }

        return '<div class="olama-error">' . Olama_School_Helpers::translate('Exam Engine plugin is not active.') . '</div>';
    }

    /**
     * Shortcode: [olama_family_number_lookup]
     * Provides a form for parents to find their family number by name.
     */
    public function render_family_number_lookup_shortcode($atts)
    {
        ob_start();
?>
        <div class="olama-family-lookup-wrapper" dir="rtl">
            <div class="lookup-card">
                <div class="lookup-header">
                    <span class="material-icons lookup-icon">person_search</span>
                    <h2><?php echo Olama_School_Helpers::translate('Find Your Family Number'); ?></h2>
                    <p><?php echo Olama_School_Helpers::translate('Enter your name to retrieve your portal access number'); ?></p>
                </div>

                <div class="lookup-body">
                    <div class="input-group">
                        <label for="family_name_search"><?php echo Olama_School_Helpers::translate('Parent Name'); ?></label>
                        <div class="search-input-wrapper">
                            <input type="text" id="family_name_search" autocomplete="off" placeholder="<?php echo Olama_School_Helpers::translate('Start typing your name...'); ?>">
                            <div id="lookup-suggestions" class="suggestions-dropdown" style="display: none;"></div>
                        </div>
                    </div>

                    <div id="lookup-result" class="lookup-result-area" style="display: none;">
                        <div class="result-label"><?php echo Olama_School_Helpers::translate('Your Family Number is:'); ?></div>
                        <div class="result-number"></div>
                        <button type="button" class="copy-result-btn" id="copy-family-number">
                            <span class="material-icons">content_copy</span>
                            <?php echo Olama_School_Helpers::translate('Copy Number'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <style>
                .olama-family-lookup-wrapper {
                    font-family: 'Tajawal', 'Inter', sans-serif;
                    max-width: 500px;
                    margin: 20px auto;
                }

                .lookup-card {
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                    padding: 30px;
                    border: 1px solid #edf2f7;
                }

                .lookup-header {
                    text-align: center;
                    margin-bottom: 25px;
                }

                .lookup-icon {
                    font-size: 48px;
                    color: #2563eb;
                    margin-bottom: 15px;
                }

                .lookup-header h2 {
                    margin: 0 0 8px 0;
                    color: #1e293b;
                    font-size: 1.4rem;
                }

                .lookup-header p {
                    margin: 0;
                    color: #64748b;
                    font-size: 0.95rem;
                }

                .input-group {
                    margin-bottom: 20px;
                }

                .input-group label {
                    display: block;
                    font-weight: 600;
                    color: #475569;
                    margin-bottom: 10px;
                    font-size: 0.9rem;
                }

                .search-input-wrapper {
                    position: relative;
                }

                #family_name_search {
                    width: 100%;
                    padding: 12px 15px;
                    border: 2px solid #e2e8f0;
                    border-radius: 10px;
                    font-size: 1rem;
                    transition: all 0.2s;
                    box-sizing: border-box;
                }

                #family_name_search:focus {
                    outline: none;
                    border-color: #2563eb;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
                }

                .suggestions-dropdown {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    margin-top: 5px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    z-index: 100;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .suggestion-item {
                    padding: 10px 15px;
                    cursor: pointer;
                    border-bottom: 1px solid #f1f5f9;
                    transition: background 0.2s;
                }

                .suggestion-item:last-child {
                    border-bottom: none;
                }

                .suggestion-item:hover {
                    background: #f8fafc;
                }

                .suggestion-info {
                    display: flex;
                    flex-direction: column;
                }

                .suggestion-name {
                    font-weight: 600;
                    color: #1e293b;
                    font-size: 0.9rem;
                }

                .suggestion-uid {
                    font-size: 0.8rem;
                    color: #64748b;
                    margin-top: 2px;
                }

                .lookup-result-area {
                    background: #f0f7ff;
                    border: 2px dashed #2563eb;
                    border-radius: 12px;
                    padding: 20px;
                    text-align: center;
                    margin-top: 10px;
                    animation: olamaSlideUp 0.3s ease-out;
                }

                @keyframes olamaSlideUp {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .result-label {
                    color: #2563eb;
                    font-weight: 700;
                    margin-bottom: 10px;
                    font-size: 1rem;
                }

                .result-number {
                    font-size: 2rem;
                    font-weight: 800;
                    color: #1e293b;
                    margin-bottom: 15px;
                    letter-spacing: 2px;
                }

                .copy-result-btn {
                    background: white;
                    border: 1px solid #cbd5e1;
                    padding: 8px 16px;
                    border-radius: 8px;
                    color: #475569;
                    font-weight: 600;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    transition: all 0.2s;
                }

                .copy-result-btn:hover {
                    background: #f8fafc;
                    border-color: #94a3b8;
                }

                .copy-result-btn .material-icons {
                    font-size: 18px;
                }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    var $input = $('#family_name_search');
                    var $suggestions = $('#lookup-suggestions');
                    var $resultArea = $('#lookup-result');
                    var searchTimeout;

                    $input.on('input', function() {
                        var term = $(this).val();

                        clearTimeout(searchTimeout);

                        if (term.length < 2) {
                            $suggestions.hide();
                            return;
                        }

                        searchTimeout = setTimeout(function() {
                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                data: {
                                    action: 'olama_lookup_family_number',
                                    search: term
                                },
                                success: function(response) {
                                    if (response.success && response.data.length > 0) {
                                        var html = '';
                                        $.each(response.data, function(i, item) {
                                            html += '<div class="suggestion-item" data-uid="' + item.family_uid + '" data-name="' + item.family_name + '">';
                                            html += '<div class="suggestion-info">';
                                            html += '<span class="suggestion-name">' + item.family_name + '</span>';
                                            html += '<span class="suggestion-uid">' + item.family_uid + '</span>';
                                            html += '</div></div>';
                                        });
                                        $suggestions.html(html).show();
                                    } else {
                                        $suggestions.hide();
                                    }
                                }
                            });
                        }, 300);
                    });

                    $(document).on('click', '.suggestion-item', function() {
                        var uid = $(this).data('uid');
                        var name = $(this).data('name');

                        $input.val(name);
                        $suggestions.hide();

                        $resultArea.find('.result-number').text(uid);
                        $resultArea.show();
                    });

                    // Hide suggestions when clicking outside
                    $(document).on('click', function(e) {
                        if (!$(e.target).closest('.search-input-wrapper').length) {
                            $suggestions.hide();
                        }
                    });

                    $('#copy-family-number').on('click', function() {
                        var num = $resultArea.find('.result-number').text();
                        var $temp = $('<input>');
                        $('body').append($temp);
                        $temp.val(num).select();
                        document.execCommand('copy');
                        $temp.remove();

                        var $btn = $(this);
                        var originalHtml = $btn.html();
                        $btn.html('<span class="material-icons">check</span> <?php echo Olama_School_Helpers::translate('Copied!'); ?>');
                        $btn.css('color', '#10b981');
                        
                        setTimeout(function() {
                            $btn.html(originalHtml);
                            $btn.css('color', '#475569');
                        }, 2000);
                    });
                });
            </script>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Shortcode: [olama_cleaning_form]
     */
    public function render_cleaning_form_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('Please log in to access the cleaning form.') . '</div>';
        }

        if (!Olama_School_Permissions::can('olama_manage_cleaning')) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate('You do not have permission to access this form.') . '</div>';
        }

        $atts = shortcode_atts(array(
            'floor_id' => 0,
        ), $atts, 'olama_cleaning_form');

        global $wpdb;

        $user_id = get_current_user_id();
        
        // 1. Get floors assigned to this user (Supervisor)
        $assigned_floors = $wpdb->get_results($wpdb->prepare(
            "SELECT f.* FROM {$wpdb->prefix}olama_cleaning_floors f 
            JOIN {$wpdb->prefix}olama_cleaning_assignments a ON a.floor_id = f.id 
            WHERE a.supervisor_id = %d AND f.is_active = 1",
            $user_id
        ));

        // 2. Determine floor list based on assignment or admin status
        if (!empty($assigned_floors)) {
            // If user (even admin) is assigned specific floors, show ONLY those
            $floors_list = $assigned_floors;
        } elseif (current_user_can('manage_options')) {
            // Real admin with no specific floor assignments - show all for oversight
            $floors_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors WHERE is_active = 1");
        } else {
            // Not an admin and no assignments - no floors available
            $floors_list = array();
        }

        if (empty($floors_list)) {
            return '<div class="olama-error notice notice-error" style="padding: 15px; background: #fee2e2; border-right: 4px solid #ef4444; border-radius: 8px; color: #991b1b; margin: 20px 0;">' . Olama_School_Helpers::translate('Access Denied: You have no assigned floors.') . '</div>';
        }

        $slots_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_slots WHERE is_active = 1");
        $items_list = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}olama_cleaning_items WHERE is_active = 1");

        if (empty($slots_list) || empty($items_list)) {
            return '<div class="olama-info">' . Olama_School_Helpers::translate('Cleaning module is not fully configured.') . '</div>';
        }

        $floor_ids = wp_list_pluck($floors_list, 'id');
        $floor_id = isset($_GET['floor_id']) ? intval($_GET['floor_id']) : (isset($atts['floor_id']) ? intval($atts['floor_id']) : $floors_list[0]->id);

        if (!in_array($floor_id, $floor_ids)) {
            $floor_id = $floors_list[0]->id;
        }

        $slot_id = isset($_GET['slot_id']) ? intval($_GET['slot_id']) : $slots_list[0]->id;

        $cleaning_date = isset($_GET['cleaning_date']) ? sanitize_text_field($_GET['cleaning_date']) : current_time('Y-m-d');

        $current_floor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_cleaning_floors WHERE id = %d", $floor_id));
        $current_slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_cleaning_slots WHERE id = %d", $slot_id));

        $assigned_cleaner = $wpdb->get_row($wpdb->prepare(
            "SELECT c.* FROM {$wpdb->prefix}olama_cleaning_assignments a 
            JOIN {$wpdb->prefix}olama_cleaning_cleaners c ON a.cleaner_id = c.id 
            WHERE a.floor_id = %d",
            $floor_id
        ));

        $cleaning_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}olama_cleaning_logs WHERE floor_id = %d AND cleaning_date = %s AND slot_id = %d",
            $floor_id,
            $cleaning_date,
            $slot_id
        ));

        $checkpoints = $cleaning_log ? json_decode($cleaning_log->checkpoints_data, true) : array();
        $logged_staff_name = Olama_School_Helpers::get_user_display_name(get_current_user_id());
        $active_year = Olama_School_Academic::get_active_year();

        // Progress calculation for the current supervisor
        $total_assigned_floors = count($floors_list);
        $total_slots_count = count($slots_list);
        $total_assigned_visits = $total_assigned_floors * $total_slots_count;

        $assigned_floor_ids = wp_list_pluck($floors_list, 'id');
        $completed_visits_count = 0;
        if (!empty($assigned_floor_ids)) {
            $completed_visits_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}olama_cleaning_logs 
                WHERE cleaning_date = %s AND floor_id IN (" . implode(',', array_map('intval', $assigned_floor_ids)) . ")",
                $cleaning_date
            ));
        }
        $completion_ratio = $total_assigned_visits > 0 ? round(($completed_visits_count / $total_assigned_visits) * 100) : 0;

        ob_start();
        ?>
        <div class="olama-cleaning-shortcode-wrapper" dir="rtl">
            <?php if (isset($_GET['message']) && $_GET['message'] === 'cleaning_saved'): ?>
                <div class="olama-success-notice" style="background: #ecfdf5; border-right: 4px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #065f46; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span>
                    <span style="font-weight: 600;"><?php echo Olama_School_Helpers::translate('Cleaning log saved successfully.'); ?></span>
                </div>
            <?php endif; ?>

            <div class="cleaning-filters" style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0;">
                <form method="get" action="" id="cleaning-shortcode-filters">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: flex-end;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #475569;">
                                <?php echo Olama_School_Helpers::translate('Floor Selection'); ?>
                            </label>
                            <select name="floor_id" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 8px; border-color: #cbd5e1;">
                                <?php foreach ($floors_list as $f): ?>
                                    <option value="<?php echo $f->id; ?>" <?php selected($floor_id, $f->id); ?>><?php echo esc_html($f->floor_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #475569;">
                                <?php echo Olama_School_Helpers::translate('Time Slot'); ?>
                            </label>
                            <select name="slot_id" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 8px; border-color: #cbd5e1;">
                                <?php foreach ($slots_list as $s): ?>
                                    <option value="<?php echo $s->id; ?>" <?php selected($slot_id, $s->id); ?>><?php echo esc_html($s->slot_time); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; color: #475569;">
                                <?php echo Olama_School_Helpers::translate('Date'); ?>
                            </label>
                            <input type="date" name="cleaning_date" value="<?php echo esc_attr($cleaning_date); ?>" onchange="this.form.submit()" style="width: 100%; height: 40px; border-radius: 8px; border-color: #cbd5e1; padding: 0 10px;">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Progress Bar -->
            <div class="cleaning-progress-container" style="background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                        <span class="dashicons dashicons-performance" style="margin-top: 2px; margin-left: 5px; color: #3b82f6;"></span>
                        <?php echo Olama_School_Helpers::translate('Your Daily Progress'); ?>
                    </span>
                    <span style="font-weight: 800; color: #3b82f6; font-size: 1rem;">
                        <?php echo $completed_visits_count; ?> / <?php echo $total_assigned_visits; ?> 
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600; margin-right: 5px;">(<?php echo $completion_ratio; ?>%)</span>
                    </span>
                </div>
                <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                    <div style="width: <?php echo $completion_ratio; ?>%; height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); border-radius: 10px; transition: width 0.5s ease-out;"></div>
                </div>

                <?php 
                // Entry status calculation
                $selected_slot_obj = null;
                foreach($slots_list as $s) if ((int)$s->id === (int)$slot_id) $selected_slot_obj = $s;
                
                $now_ts = current_time('timestamp');
                $slot_ts = strtotime($cleaning_date . ' ' . ($selected_slot_obj ? $selected_slot_obj->slot_time : '00:00'));
                $actual_ts = $cleaning_log ? strtotime($cleaning_log->created_at) : $now_ts;
                
                $time_diff_mins = round(($actual_ts - $slot_ts) / 60);
                $is_delayed = ($time_diff_mins > 10);
                $status_label = $is_delayed ? Olama_School_Helpers::translate('Delay') : Olama_School_Helpers::translate('Ontime');
                $status_color = $is_delayed ? '#ef4444' : '#10b981';
                ?>
                <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600;">
                    <span style="color: #64748b;"><?php echo Olama_School_Helpers::translate('Entry Status:'); ?></span>
                    <span style="color: <?php echo $status_color; ?>; background: <?php echo $is_delayed ? '#fef2f2' : '#f0fdf4'; ?>; padding: 4px 12px; border-radius: 20px; border: 1px solid <?php echo $is_delayed ? '#fecaca' : '#bbf7d0'; ?>; display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; color: <?php echo $status_color; ?>;"></span>
                        <?php echo $status_label; ?> - 
                        <?php echo $is_delayed ? Olama_School_Helpers::translate('No') : Olama_School_Helpers::translate('Yes'); ?> 
                        [<?php echo date('H:i', $actual_ts); ?>]
                        <?php if ($is_delayed && $time_diff_mins > 0): ?>
                            <span style="font-weight: 800; margin-right: 5px;">(<?php echo $time_diff_mins; ?> <?php echo Olama_School_Helpers::translate('mins delay'); ?>)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="cleaning-form-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #eef2f7;">
                <form method="post" action="">
                    <?php wp_nonce_field('olama_save_cleaning_log'); ?>
                    <input type="hidden" name="olama_save_cleaning_log" value="1">
                    <input type="hidden" name="academic_year_id" value="<?php echo $active_year->id ?? 0; ?>">
                    <input type="hidden" name="floor_id" value="<?php echo $floor_id; ?>">
                    <input type="hidden" name="floor_name" value="<?php echo esc_attr($current_floor ? $current_floor->floor_name : ''); ?>">
                    <input type="hidden" name="slot_id" value="<?php echo $slot_id; ?>">
                    <input type="hidden" name="slot_time" value="<?php echo esc_attr($current_slot ? $current_slot->slot_time : ''); ?>">
                    <input type="hidden" name="cleaning_date" value="<?php echo esc_attr($cleaning_date); ?>">
                    <input type="hidden" name="redirect_to" value="<?php 
                        $current_url = home_url(add_query_arg(null, null)); 
                        // We must keep floor, slot, date to ensure we land back on SAME context
                        echo esc_url($current_url); 
                    ?>">

                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="margin: 0; color: #2563eb; font-size: 1.8rem; font-weight: 800;"><?php echo Olama_School_Helpers::translate('Toilet Cleaning Follow-up'); ?></h2>
                        <div style="margin-top: 15px; display: inline-flex; align-items: center; gap: 15px; background: #eff6ff; padding: 8px 20px; border-radius: 30px; color: #2563eb; font-weight: 600; font-size: 0.95rem;">
                            <span><span class="dashicons dashicons-location" style="margin-top: 2px;"></span> <?php echo esc_html($current_floor ? $current_floor->floor_name : ''); ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span><span class="dashicons dashicons-clock" style="margin-top: 2px;"></span> <?php echo esc_html($current_slot ? $current_slot->slot_time : ''); ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span><span class="dashicons dashicons-calendar-alt" style="margin-top: 2px;"></span> <?php echo $cleaning_date; ?></span>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <div>
                            <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 8px;"><?php echo Olama_School_Helpers::translate('Assigned Cleaner'); ?></label>
                            <input type="text" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->cleaner_name : Olama_School_Helpers::translate('Not Assigned')); ?>" readonly style="width: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px;">
                            <input type="hidden" name="cleaner_id" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->id : 0); ?>">
                            <input type="hidden" name="cleaner_name" value="<?php echo esc_attr($assigned_cleaner ? $assigned_cleaner->cleaner_name : ''); ?>">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 700; color: #1e293b; margin-bottom: 8px;"><?php echo Olama_School_Helpers::translate('Staff/Signature'); ?></label>
                            <input type="text" value="<?php echo esc_attr($logged_staff_name); ?>" readonly style="width: 100%; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; color: #64748b;">
                        </div>
                    </div>

                    <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc;">
                                    <th style="padding: 15px; text-align: right; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: #475569;"><?php echo Olama_School_Helpers::translate('Item'); ?></th>
                                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: #10b981;"><?php echo Olama_School_Helpers::translate('Done'); ?></th>
                                    <th style="padding: 15px; text-align: center; border-bottom: 2px solid #e2e8f0; font-weight: 800; color: #ef4444;"><?php echo Olama_School_Helpers::translate('Not Done'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_list as $item): 
                                    $val = $checkpoints[$item->id] ?? '';
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                    <td style="padding: 15px; font-weight: 600; color: #1e293b;">
                                        <span class="dashicons dashicons-clipboard" style="color: #3b82f6; margin-left: 10px;"></span>
                                        <?php echo esc_html($item->item_name); ?>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <label class="cleaning-checkbox-custom done">
                                            <input type="radio" name="checkpoints[<?php echo $item->id; ?>]" value="done" <?php checked($val, 'done'); ?>>
                                            <span></span>
                                        </label>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <label class="cleaning-checkbox-custom not-done">
                                            <input type="radio" name="checkpoints[<?php echo $item->id; ?>]" value="not_done" <?php checked($val, 'not_done'); ?>>
                                            <span></span>
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="text-align: center;">
                        <button type="submit" class="olama-btn-save-cleaning" style="background: #2563eb; color: #fff; border: none; padding: 15px 50px; font-size: 1.1rem; font-weight: 700; border-radius: 12px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3); transition: all 0.3s ease;">
                            <span class="dashicons dashicons-saved" style="margin-top: 5px; margin-left: 5px;"></span>
                            <?php echo Olama_School_Helpers::translate('Save Cleaning Log'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <style>
                .cleaning-checkbox-custom { position: relative; display: inline-block; width: 32px; height: 32px; cursor: pointer; }
                .cleaning-checkbox-custom input { display: none; }
                .cleaning-checkbox-custom span { position: absolute; top: 0; left: 0; height: 32px; width: 32px; background-color: #fff; border: 2px solid #cbd5e1; border-radius: 50%; transition: all 0.2s; }
                .cleaning-checkbox-custom.done input:checked + span { background-color: #10b981; border-color: #10b981; }
                .cleaning-checkbox-custom.not-done input:checked + span { background-color: #ef4444; border-color: #ef4444; }
                .cleaning-checkbox-custom span:after { content: ""; position: absolute; display: none; left: 10px; top: 5px; width: 6px; height: 12px; border: solid white; border-width: 0 3px 3px 0; transform: rotate(45deg); }
                .cleaning-checkbox-custom input:checked + span:after { display: block; }
                .olama-btn-save-cleaning:hover { transform: translateY(-2px); background: #1d4ed8; box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.4); }
                .olama-btn-save-cleaning:active { transform: translateY(0); }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }
}
