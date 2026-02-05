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
        add_shortcode('olama_attendance', array($this, 'render_attendance_shortcode'));
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
        if (!$section_id) {
            return '<div class="olama-error">' . __('Please specify a valid section ID in the shortcode.', 'olama-school') . '</div>';
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

        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));
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
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ظٹط§طھ') !== false)
                return 'dashicons-calculator';
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'ط§ظ†ط¬ظ„ظٹط²ظٹ') !== false || strpos($subject_name, 'ط¥ظ†ط¬ظ„ظٹط²ظٹط©') !== false)
                return 'dashicons-admin-site-alt3';
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'ط¹ظ„ظˆظ…') !== false)
                return 'dashicons-rest-api';
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹ') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹط©') !== false)
                return 'dashicons-translation';
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'ط¯ظٹظ†') !== false || strpos($subject_name, 'ط¥ط³ظ„ط§ظ…ظٹط©') !== false)
                return 'dashicons-heart';
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'ط­ط§ط³ظˆط¨') !== false || strpos($subject_name, 'ظ…ظ‡ط§ط±ط§طھ ط±ظ‚ظ…ظٹط©') !== false)
                return 'dashicons-desktop';
            if (strpos($subject_name, 'art') !== false || strpos($subject_name, 'ظپظ†ظٹط©') !== false)
                return 'dashicons-art';
            if (strpos($subject_name, 'physic') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ط©') !== false)
                return 'dashicons-universal-access';
            return 'dashicons-book-alt';
        };

        // Helper to get subject background color (pastel based on subject type)
        $get_subject_bg = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹ') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹط©') !== false)
                return '#dcfce7'; // mint
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ظٹط§طھ') !== false)
                return '#dbeafe'; // light blue
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'ط¯ظٹظ†') !== false || strpos($subject_name, 'ط¥ط³ظ„ط§ظ…ظٹط©') !== false || strpos($subject_name, 'طھط±ط¨ظٹط©') !== false)
                return '#fef3c7'; // amber
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'ط§ظ†ط¬ظ„ظٹط²ظٹ') !== false || strpos($subject_name, 'ط¥ظ†ط¬ظ„ظٹط²ظٹط©') !== false)
                return '#e0e7ff'; // indigo
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'ط¹ظ„ظˆظ…') !== false)
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
                1 => 'ط§ظ„ط£ظˆظ„',
                2 => 'ط§ظ„ط«ط§ظ†ظٹ',
                3 => 'ط§ظ„ط«ط§ظ„ط«',
                4 => 'ط§ظ„ط±ط§ط¨ط¹',
                5 => 'ط§ظ„ط®ط§ظ…ط³',
                6 => 'ط§ظ„ط³ط§ط¯ط³',
                7 => 'ط§ظ„ط³ط§ط¨ط¹',
                8 => 'ط§ظ„ط«ط§ظ…ظ†',
                9 => 'ط§ظ„طھط§ط³ط¹',
                10 => 'ط§ظ„ط¹ط§ط´ط±',
                11 => 'ط§ظ„ط­ط§ط¯ظٹ ط¹ط´ط±',
                12 => 'ط§ظ„ط«ط§ظ†ظٹ ط¹ط´ط±',
                13 => 'ط§ظ„ط«ط§ظ„ط« ط¹ط´ط±',
                14 => 'ط§ظ„ط±ط§ط¨ط¹ ط¹ط´ط±',
                15 => 'ط§ظ„ط®ط§ظ…ط³ ط¹ط´ط±',
                16 => 'ط§ظ„ط³ط§ط¯ط³ ط¹ط´ط±',
                17 => 'ط§ظ„ط³ط§ط¨ط¹ ط¹ط´ط±',
                18 => 'ط§ظ„ط«ط§ظ…ظ† ط¹ط´ط±',
                19 => 'ط§ظ„طھط§ط³ط¹ ط¹ط´ط±',
                20 => 'ط§ظ„ط¹ط´ط±ظˆظ†'
            );
            $week_ordinal = isset($week_ordinals[$week_number]) ? $week_ordinals[$week_number] : $week_number;
            ?>
            <!-- Illustrated Header -->
            <div class="plan-header-v2">
                <div class="header-content">
                    <h1 class="header-title">ط§ظ„ط®ط·ط© ط§ظ„ط£ط³ط¨ظˆط¹ظٹط©</h1>
                    <div class="header-subtitle">
                        <?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                        <?php echo $section ? esc_html($section->section_name) : ''; ?>
                    </div>
                </div>
                <!-- Academic Year & Week Info Bar (Integrated) -->
                <div class="semester-bar">
                    <div class="semester-left">
                        <span class="week-label">
                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط£ط³ط¨ظˆط¹ ط§ظ„ط¯ط±ط§ط³ظٹ') . ' ' . $week_ordinal; ?>
                        </span>
                        <span class="week-dates">(
                            <?php echo date_i18n('j', strtotime($week_start)); ?>-
                            <?php echo date_i18n('j F', strtotime($week_end)); ?>)
                        </span>
                    </div>
                    <div class="semester-right">
                        <span class="academic-year-label">
                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط¹ط§ظ… ط§ظ„ط¯ط±ط§ط³ظٹ'); ?>
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
                $days_of_week = array('Sunday' => 'ط§ظ„ط£ط­ط¯', 'Monday' => 'ط§ظ„ط§ط«ظ†ظٹظ†', 'Tuesday' => 'ط§ظ„ط«ظ„ط§ط«ط§ط،', 'Wednesday' => 'ط§ظ„ط£ط±ط¨ط¹ط§ط،', 'Thursday' => 'ط§ظ„ط®ظ…ظٹط³');
                $day_index = 0;
                foreach ($days_of_week as $day_en => $day_ar):
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
                                                $parts[] = $homework_count . ' ' . Olama_School_Helpers::translate('ظˆط§ط¬ط¨ط§طھ');
                                            }
                                            if ($review_count > 0) {
                                                $parts[] = $review_count . ' ' . Olama_School_Helpers::translate('ظ…طھط§ط¨ط¹ط§طھ');
                                            }
                                            echo implode(' - ', $parts);
                                            ?>
                                        <?php else: ?>
                                            <?php echo Olama_School_Helpers::translate('ظ„ط§ ظˆط§ط¬ط¨ط§طھ'); ?>
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
                                        <?php echo Olama_School_Helpers::translate('ظ„ط§ طھظˆط¬ط¯ ط­طµطµ ظ…ط®ط·ط·ط© ظ„ظ‡ط°ط§ ط§ظ„ظٹظˆظ…'); ?>
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
                                                    echo Olama_School_Helpers::translate('ط­طµط© ط§ظ„ظٹظˆظ…');
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
                                                            <?php echo Olama_School_Helpers::translate('ط§ظ„ظˆط­ط¯ط©'); ?>:
                                                        </span>
                                                        <span class="detail-value" style="text-align: right;">
                                                            <?php echo esc_html($plan->unit_name); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-book-alt detail-icon"></span>
                                                    <span class="detail-label">
                                                        <?php echo Olama_School_Helpers::translate('ط§ظ„ط¯ط±ط³'); ?>:
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
                                                    <?php echo Olama_School_Helpers::translate('ط§ظ„ظˆط§ط¬ط¨ ط§ظ„ط¨ظٹطھظٹ'); ?>
                                                </div>
                                                <div class="homework-list">
                                                    <?php if ($plan->homework_sb): ?>
                                                        <div class="homework-item">
                                                            <div class="homework-item-header">
                                                                <span class="dashicons dashicons-book hw-icon"></span>
                                                                <span class="hw-label">
                                                                    <?php echo Olama_School_Helpers::translate('ظƒطھط§ط¨ ط§ظ„ط·ط§ظ„ط¨'); ?>:
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
                                                                    <?php echo Olama_School_Helpers::translate('ظƒطھط§ط¨ ط§ظ„طھظ…ط§ط±ظٹظ†'); ?>:
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
                                                                    <?php echo Olama_School_Helpers::translate('ط§ظ„ط¯ظپطھط±'); ?>:
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
                                                                    <?php echo Olama_School_Helpers::translate('ط§ظ„ط¯ظˆط³ظٹط©'); ?>:
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
                                                    <?php echo Olama_School_Helpers::translate('ظ…ظ„ط§ط­ط¸ط§طھ ط§ظ„ظ…ط¹ظ„ظ…'); ?>
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

        $grade_id = intval($atts['grade']);
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

        // Filter for approved/published only if not admin
        $is_admin = Olama_School_Permissions::can('olama_view_reports_summary');
        $approved_exams = array_filter($exams, function ($e) use ($is_admin) {
            return $is_admin || $e->status === 'approved' || $e->status === 'published';
        });

        if (empty($approved_exams)) {
            return '<div class="olama-no-plans" style="padding: 30px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No approved exams found for the selected criteria.') .
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
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ظٹط§طھ') !== false)
                return 'dashicons-calculator';
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'ط§ظ†ط¬ظ„ظٹط²ظٹ') !== false || strpos($subject_name, 'ط¥ظ†ط¬ظ„ظٹط²ظٹط©') !== false)
                return 'dashicons-admin-site-alt3';
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'ط¹ظ„ظˆظ…') !== false)
                return 'dashicons-rest-api';
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹ') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹط©') !== false)
                return 'dashicons-translation';
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'ط¯ظٹظ†') !== false || strpos($subject_name, 'ط¥ط³ظ„ط§ظ…ظٹط©') !== false)
                return 'dashicons-heart';
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'ط­ط§ط³ظˆط¨') !== false || strpos($subject_name, 'ظ…ظ‡ط§ط±ط§طھ ط±ظ‚ظ…ظٹط©') !== false)
                return 'dashicons-desktop';
            if (strpos($subject_name, 'social') !== false || strpos($subject_name, 'ط§ط¬طھظ…ط§ط¹ظٹط©') !== false || strpos($subject_name, 'ط¯ط±ط§ط³ط§طھ') !== false)
                return 'dashicons-admin-site';
            return 'dashicons-book-alt';
        };

        // Helper to get subject background color
        $get_subject_bg = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹ') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹط©') !== false)
                return '#f0fdf4'; // fresh green
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ظٹط§طھ') !== false)
                return '#eff6ff'; // blue
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'ط¯ظٹظ†') !== false || strpos($subject_name, 'ط¥ط³ظ„ط§ظ…ظٹط©') !== false)
                return '#fffbeb'; // amber
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'ط§ظ†ط¬ظ„ظٹط²ظٹ') !== false || strpos($subject_name, 'ط¥ظ†ط¬ظ„ظٹط²ظٹط©') !== false)
                return '#eef2ff'; // indigo
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'ط¹ظ„ظˆظ…') !== false)
                return '#f0fdfa'; // teal
            return '#f8fafc'; // slate
        };

        ob_start();
        ?>
        <div class="olama-weekly-plan-v2 olama-exam-report-v2">
            <!-- Illustrated Header -->
            <div class="plan-header-v2" style="background: linear-gradient(145deg, #818cf8 0%, #6366f1 100%);">
                <div class="header-content">
                    <h1 class="header-title" style="color: #ffffff;">
                        <?php echo Olama_School_Helpers::translate('ط¬ط¯ظˆظ„ ط§ظ„ط§ط®طھط¨ط§ط±ط§طھ'); ?>
                    </h1>
                    <div class="header-subtitle">
                        <?php echo $grade ? esc_html($grade->grade_name) : ''; ?>
                    </div>
                </div>
                <!-- Academic Year & Info Bar -->
                <div class="semester-bar" style="background: rgba(255, 255, 255, 0.15);">
                    <div class="semester-left" style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span class="week-label">
                                <?php echo Olama_School_Helpers::translate('ط§ظ„ط¹ط§ظ… ط§ظ„ط¯ط±ط§ط³ظٹ'); ?>
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
                                                    <?php echo Olama_School_Helpers::translate('ط§ظ„ظ‚ط§ط¹ط©/ط§ظ„ط؛ط±ظپط©'); ?>:
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
                                            <?php echo Olama_School_Helpers::translate('ظ…ط§ط¯ط© ط§ظ„ط§ط®طھط¨ط§ط±'); ?>
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
                                                foreach ($material['curriculum_items'] as $item):
                                                    $unit = !empty($item['unit_id']) ? Olama_School_Unit::get_unit($item['unit_id']) : null;
                                                    $lesson = !empty($item['lesson_id']) ? Olama_School_Lesson::get_lesson($item['lesson_id']) : null;
                                                    $unit_name = $unit ? $unit->unit_name : (isset($item['unit']) ? $item['unit'] : '');
                                                    $lesson_name = $lesson ? $lesson->lesson_title : (isset($item['lesson']) ? $item['lesson'] : '');

                                                    if (empty($unit_name) && empty($lesson_name))
                                                        continue;
                                                    ?>
                                                    <div class="detail-item"
                                                        style="padding: 5px; background: rgba(255,255,255,0.5); border-radius: 6px; margin-bottom: 4px;">
                                                        <span class="dashicons dashicons-arrow-left-alt2 detail-icon"
                                                            style="font-size: 12px;"></span>
                                                        <span class="detail-value">
                                                            <strong>
                                                                <?php echo esc_html($unit_name . ($lesson_name ? ' - ' . $lesson_name : '')); ?>
                                                            </strong>
                                                            <?php if (!empty($item['material'])): ?>
                                                                <div
                                                                    style="font-size: 0.85rem; color: #475569; margin-top: 2px; padding-right: 20px;">
                                                                    <?php echo esc_html($item['material']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach;
                                            endif; ?>

                                            <?php if ($exam->student_book_material): ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-book detail-icon"></span>
                                                    <span class="detail-label">
                                                        <?php echo Olama_School_Helpers::translate('Student Book'); ?>:
                                                    </span>
                                                    <span class="detail-value">
                                                        <?php echo esc_html($exam->student_book_material); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($exam->workbook_material): ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-edit detail-icon"></span>
                                                    <span class="detail-label">
                                                        <?php echo Olama_School_Helpers::translate('Workbook'); ?>:
                                                    </span>
                                                    <span class="detail-value">
                                                        <?php echo esc_html($exam->workbook_material); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            $booklets_val = !empty($material['booklets_notebooks']) ? $material['booklets_notebooks'] : (!empty($exam->notebook_material) ? $exam->notebook_material : '');
                                            if (!empty($booklets_val)): ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-media-text detail-icon"></span>
                                                    <span class="detail-label">
                                                        <?php echo Olama_School_Helpers::translate('Booklets & Notebooks'); ?>:
                                                    </span>
                                                    <span class="detail-value">
                                                        <?php echo esc_html($booklets_val); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
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
                                                <?php echo Olama_School_Helpers::translate('ظ…ظ„ط§ط­ط¸ط§طھ ظ‡ط§ظ…ط©'); ?>
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
     * Attributes: semester, section
     */
    public function render_weekly_schedule_shortcode($atts)
    {
        global $wpdb;
        $atts = shortcode_atts(array(
            'semester' => '',
            'section' => '',
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

        $schedule = Olama_School_Schedule::get_schedule($section_id, $semester_id);
        $section = Olama_School_Section::get_section($section_id);
        $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;
        $semester = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_semesters WHERE id = %d", $semester_id));

        if (empty($schedule)) {
            return '<div class="olama-no-plans">' . __('No master schedule found for the selected section and semester.', 'olama-school') . '</div>';
        }

        // Arabic day names
        $days_ar = array(
            'Sunday' => 'ط§ظ„ط£ط­ط¯',
            'Monday' => 'ط§ظ„ط§ط«ظ†ظٹظ†',
            'Tuesday' => 'ط§ظ„ط«ظ„ط§ط«ط§ط،',
            'Wednesday' => 'ط§ظ„ط£ط±ط¨ط¹ط§ط،',
            'Thursday' => 'ط§ظ„ط®ظ…ظٹط³',
        );

        // Arabic ordinal period names
        $periods_ar = array(
            1 => 'ط§ظ„ط£ظˆظ„ظ‰',
            2 => 'ط§ظ„ط«ط§ظ†ظٹط©',
            3 => 'ط§ظ„ط«ط§ظ„ط«ط©',
            4 => 'ط§ظ„ط±ط§ط¨ط¹ط©',
            5 => 'ط§ظ„ط®ط§ظ…ط³ط©',
            6 => 'ط§ظ„ط³ط§ط¯ط³ط©',
            7 => 'ط§ظ„ط³ط§ط¨ط¹ط©',
            8 => 'ط§ظ„ط«ط§ظ…ظ†ط©',
            9 => 'ط§ظ„طھط§ط³ط¹ط©',
            10 => 'ط§ظ„ط¹ط§ط´ط±ط©',
        );

        $max_periods = $grade ? intval($grade->periods_count) : 8;

        // Get subject color based on name
        $get_subject_color = function ($subject_name) {
            $subject_name = strtolower($subject_name);
            if (strpos($subject_name, 'math') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ظٹط§طھ') !== false)
                return array('bg' => '#dbeafe', 'text' => '#1e40af');
            if (strpos($subject_name, 'arabic') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹ') !== false || strpos($subject_name, 'ط¹ط±ط¨ظٹط©') !== false)
                return array('bg' => '#dcfce7', 'text' => '#166534');
            if (strpos($subject_name, 'english') !== false || strpos($subject_name, 'ط§ظ†ط¬ظ„ظٹط²ظٹ') !== false || strpos($subject_name, 'ط¥ظ†ط¬ظ„ظٹط²ظٹط©') !== false)
                return array('bg' => '#e0e7ff', 'text' => '#4338ca');
            if (strpos($subject_name, 'science') !== false || strpos($subject_name, 'ط¹ظ„ظˆظ…') !== false)
                return array('bg' => '#ccfbf1', 'text' => '#0f766e');
            if (strpos($subject_name, 'islamic') !== false || strpos($subject_name, 'ط¯ظٹظ†') !== false || strpos($subject_name, 'ط¥ط³ظ„ط§ظ…ظٹط©') !== false || strpos($subject_name, 'طھط±ط¨ظٹط©') !== false)
                return array('bg' => '#fef3c7', 'text' => '#92400e');
            if (strpos($subject_name, 'social') !== false || strpos($subject_name, 'ط§ط¬طھظ…ط§ط¹ظٹط©') !== false || strpos($subject_name, 'ط¯ط±ط§ط³ط§طھ') !== false)
                return array('bg' => '#fce7f3', 'text' => '#9d174d');
            if (strpos($subject_name, 'digital') !== false || strpos($subject_name, 'ط­ط§ط³ظˆط¨') !== false || strpos($subject_name, 'ط±ظ‚ظ…ظٹط©') !== false)
                return array('bg' => '#f3e8ff', 'text' => '#7c3aed');
            if (strpos($subject_name, 'art') !== false || strpos($subject_name, 'ظپظ†ظٹط©') !== false)
                return array('bg' => '#fff7ed', 'text' => '#c2410c');
            if (strpos($subject_name, 'physic') !== false || strpos($subject_name, 'ط±ظٹط§ط¶ط©') !== false || strpos($subject_name, 'ط¨ط¯ظ†ظٹط©') !== false)
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
                        <span class="badge-icon">ًں“…</span>
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
                                    <?php echo Olama_School_Helpers::translate('ط§ظ„ظٹظˆظ…'); ?>
                                </th>
                                <?php for ($i = 1; $i <= $max_periods; $i++): ?>
                                    <th class="period-col-header">
                                        <span class="period-label-text">
                                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط­طµط©'); ?>
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
                                <span class="day-icon">ًں“†</span>
                                <span class="day-title">
                                    <?php echo esc_html($day_ar); ?>
                                </span>
                                <span class="toggle-arrow">â–¼</span>
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
                                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط­طµط©'); ?>
                                            <?php echo isset($periods_ar[$i]) ? $periods_ar[$i] : $i; ?>
                                        </span>
                                        <span class="subject-name-mobile" style="color: <?php echo esc_attr($colors['text']); ?>;">
                                            <?php echo esc_html($item->subject_name); ?>
                                        </span>
                                    </div>
                                <?php endfor; ?>
                                <?php if (!$has_periods): ?>
                                    <div class="no-periods">
                                        <?php echo Olama_School_Helpers::translate('ظ„ط§ طھظˆط¬ط¯ ط­طµطµ'); ?>
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
                    <div class="header-icon">ًں“ڑ</div>
                    <div class="header-content">
                        <h1>
                            <?php echo Olama_School_Helpers::translate('ط§ظ„ظ‚ط±ط·ط§ط³ظٹط© ط§ظ„ظ…ط¯ط±ط³ظٹط©'); ?>
                        </h1>
                        <p>
                            <?php echo Olama_School_Helpers::translate('ظ‚ط§ط¦ظ…ط© ط§ظ„ظ…ط³طھظ„ط²ظ…ط§طھ ط§ظ„ظ…ط¯ط±ط³ظٹط© ظ„ظƒظ„ طµظپ'); ?>
                        </p>
                    </div>
                    <div class="header-year">
                        <span class="year-label">
                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط¹ط§ظ… ط§ظ„ط¯ط±ط§ط³ظٹ'); ?>
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
                        <div class="accordion-item <?php echo $is_first ? 'active' : ''; ?>">
                            <div class="accordion-header" style="background: <?php echo $gradient; ?>;">
                                <div class="header-left">
                                    <span class="grade-icon">ًںژ’</span>
                                    <span class="grade-name">
                                        <?php echo esc_html($item->grade_name); ?>
                                    </span>
                                </div>
                                <div class="header-right">
                                    <span class="toggle-icon">â–¼</span>
                                </div>
                            </div>
                            <div class="accordion-content" <?php echo $is_first ? 'style="display: block;"' : ''; ?>>
                                <?php if (!empty($item->notebooks)): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <span class="section-icon">ًں““</span>
                                            <?php echo Olama_School_Helpers::translate('ط§ظ„ط¯ظپط§طھط± ط§ظ„ظ…ط·ظ„ظˆط¨ط©'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->notebooks)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item->stationary)): ?>
                                    <div class="content-section">
                                        <div class="section-title">
                                            <span class="section-icon">ًں“ژ</span>
                                            <?php echo Olama_School_Helpers::translate('ط§ظ„ظ‚ط±ط·ط§ط³ظٹط© ط§ظ„ظ…ط·ظ„ظˆط¨ط©'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->stationary)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item->teacher_notes)): ?>
                                    <div class="content-section notes">
                                        <div class="section-title">
                                            <span class="section-icon">ًں“‌</span>
                                            <?php echo Olama_School_Helpers::translate('ظ…ظ„ط§ط­ط¸ط§طھ ط§ظ„ظ…ط¹ظ„ظ…'); ?>
                                        </div>
                                        <div class="section-content">
                                            <?php echo nl2br(esc_html($item->teacher_notes)); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($item->notebooks) && empty($item->stationary) && empty($item->teacher_notes)): ?>
                                    <div class="empty-state">
                                        <span class="empty-icon">ًں“­</span>
                                        <p>
                                            <?php echo Olama_School_Helpers::translate('ظ„ظ… ظٹطھظ… طھط­ط¯ظٹط¯ ظ‚ط±ط·ط§ط³ظٹط© ظ„ظ‡ط°ط§ ط§ظ„طµظپ ط¨ط¹ط¯.'); ?>
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
                        <?php echo Olama_School_Helpers::translate('ظٹط±ط¬ظ‰ ط¥ط­ط¶ط§ط± ط¬ظ…ظٹط¹ ط§ظ„ظ…ط³طھظ„ط²ظ…ط§طھ ظپظٹ ط§ظ„ظٹظˆظ… ط§ظ„ط£ظˆظ„ ظ…ظ† ط§ظ„ط¯ط±ط§ط³ط©'); ?>
                        ًں“–
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

                .accordion-item {
                    margin-bottom: 15px;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }

                .accordion-header {
                    padding: 18px 24px;
                    cursor: pointer;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    color: #fff;
                    transition: all 0.3s ease;
                }

                .accordion-header:hover {
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

                .accordion-item.active .toggle-icon {
                    transform: rotate(180deg);
                }

                .accordion-content {
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

                    .accordion-header {
                        padding: 15px 18px;
                    }

                    .grade-name {
                        font-size: 1rem;
                    }

                    .accordion-content {
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
        if (!is_user_logged_in()) {
            return '<div class="olama-error">' . Olama_School_Helpers::translate("Please log in to mark attendance.") . '</div>';
        }

        $active_year = Olama_School_Academic::get_active_year();
        $year_id = $active_year ? $active_year->id : 0;
        $semester = Olama_School_Academic::get_active_semester($year_id);
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
                "SELECT student_id, status FROM $table WHERE section_id = %d AND attendance_date = %s",
                $section_id,
                $attendance_date
            ));
            foreach ($results as $res) {
                $attendance_records[$res->student_id] = $res->status;
            }

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
                <div class="attendance-header">
                    <h2><?php echo Olama_School_Helpers::translate("Daily Attendance"); ?></h2>
                    <div class="attendance-meta">
                        <span class="meta-item"><i class="material-icons">calendar_today</i>
                            <?php echo date_i18n("l, j F Y", strtotime($attendance_date)); ?></span>
                    </div>
                </div>

                <?php if (!empty($assignments)): ?>
                    <div class="section-selector">
                        <?php
                        // Deduplicate sections if any (might happen if teacher assigned multiple subjects in same section)
                        $seen_sections = array();
                        foreach ($assignments as $asgn):
                            if (in_array($asgn->section_id, $seen_sections))
                                continue;
                            $seen_sections[] = $asgn->section_id;
                            ?>
                            <a href="<?php echo add_query_arg("section_id", $asgn->section_id); ?>"
                                class="section-chip <?php echo $section_id == $asgn->section_id ? "active" : ""; ?>">
                                <?php echo esc_html($asgn->grade_name . " - " . $asgn->section_name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($section_id): ?>
                    <div class="attendance-summary-cards">
                        <div class="summary-card present">
                            <div class="card-label">
                                <span class="label-ar">حاضر</span>
                                <span class="label-en">(Present)</span>
                            </div>
                            <div class="card-value" id="total-present"><?php echo $total_present; ?></div>
                        </div>
                        <div class="summary-card absent">
                            <div class="card-label">
                                <span class="label-ar">غائب</span>
                                <span class="label-en">(Absent)</span>
                            </div>
                            <div class="card-value" id="total-absent"><?php echo $total_absent; ?></div>
                        </div>
                    </div>

                    <div class="students-grid">
                        <?php foreach ($students as $stu):
                            $status = $attendance_records[$stu->id] ?? "present";
                            ?>
                            <div class="student-attendance-btn <?php echo $status; ?>" data-student="<?php echo $stu->id; ?>"
                                data-section="<?php echo $section_id; ?>" data-year="<?php echo $year_id; ?>"
                                data-semester="<?php echo $semester_id; ?>" data-date="<?php echo $attendance_date; ?>">
                                <div class="student-name"><?php echo esc_html($stu->student_name); ?></div>
                                <div class="student-status">
                                    <span class="status-present"><?php echo Olama_School_Helpers::translate("Present"); ?></span>
                                    <span class="status-absent"><?php echo Olama_School_Helpers::translate("Absent"); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="attendance-footer">
                        <p class="hint">ًں’،
                            <?php echo Olama_School_Helpers::translate("Click on a student name to mark them as absent (Red) or present (Blue). Changes are saved instantly."); ?>
                        </p>
                    </div>

                    <script>
                                    jQuery(do                
                                        cument).ready(function ($) {
                                        $(".student-attendance-btn").on("click", function () {
                                            var $btn = $(this);
                                            var studentId = $btn.data("student");
                                            var sectionId = $btn.data("section");
                                            var yearId = $btn.data("year");
                                            var semId = $btn.data("semester");
                                            var date = $btn.data("date");
                                            var currentStatus = $btn.hasClass("present") ? "present" : "absent";
                                            var newStatus = currentStatus === "present" ? "absent" : "present";

                                            $btn.addClass("loading");

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
                                                $btn.removeClass("loading");
                                                if (response.success) {
                                                    $btn.removeClass("present absent").addClass(newStatus);
                                                } else {
                                                    alert("Error: " + response.data);
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

                                    .attendance-header {
                                        margin-bottom: 25px;
                                        text-align: center;
                                    }

                                    .attendance-header h2 {
                                        margin: 0 0 10px;
                                        color: #1e293b;
                                        font-size: 1.8rem;
                                    }

                                    .attendance-meta {
                                        color: #64748b;
                                        display: flex;
                                        justify-content: center;
                                        gap: 20px;
                                    }

                                    .meta-item {
                                        display: flex;
                                        align-items: center;
                                        gap: 5px;
                                    }

                                    .section-selector {
                                        display: flex;
                                        gap: 10px;
                                        overflow-x: auto;
                                        padding-bottom: 15px;
                                        margin-bottom: 25px;
                                        justify-content: center;
                                    }

                                    .section-chip {
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

                                    .section-chip.active {
                                        background: #3b82f6;
                                        color: #fff;
                                        border-color: #3b82f6;
                                    }

                                    .students-grid {
                                        display: grid;
                                        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                                        gap: 12px;
                                    }

                                    .student-attendance-btn {
                                        padding: 15px 10px;
                                        background: #fff;
                                        border-radius: 12px;
                                        border: 2px solid #e2e8f0;
                                        cursor: pointer;
                                        text-align: center;
                                        transition: all 0.2s;
                                        position: relative;
                                    }

                                    .student-attendance-btn.present {
                                        border-color: #3b82f6;
                                        background: #eff6ff;
                                    }

                                    .student-attendance-btn.absent {
                                        border-color: #ef4444;
                                        background: #fef2f2;
                                    }

                                    .student-name {
                                        font-weight: 600;
                                        color: #1e293b;
                                        margin-bottom: 8px;
                                        font-size: 0.95rem;
                                        line-height: 1.3;
                                    }

                                    .student-status {
                                        font-size: 0.8rem;
                                        font-weight: 700;
                                        text-transform: uppercase;
                                    }

                                    .student-attendance-btn.present .status-absent {
                                        display: none;
                                    }

                                    .student-attendance-btn.present .status-present {
                                        color: #3b82f6;
                                    }

                                    .student-attendance-btn.absent .status-present {
                                        display: none;
                                    }

                                    .student-attendance-btn.absent .status-absent {
                                        color: #ef4444;
                                    }

                                    .student-attendance-btn.loading {
                                        opacity: 0.6;
                                        pointer-events: none;
                                    }

                                    .attendance-footer {
                                        margin-top: 30px;
                                        text-align: center;
                                        color: #64748b;
                                        font-size: 0.9rem;
                                    }

                                    @media (max-width: 480px) {
                                        .students-grid {
                                            grid-template-columns: repeat(2, 1fr);
                                        }
                                    }
                                </style>
                                <?php
                endif;
                return ob_get_clean();
    }
}
