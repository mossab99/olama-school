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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }

    /**
     * Enqueue Shortcode Assets
     */
    public function enqueue_shortcode_assets()
    {
        wp_enqueue_style('olama-google-fonts', 'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&family=Almarai:wght@300;400;700;800&display=swap', array(), null);
        wp_enqueue_style('olama-shortcodes-css', OLAMA_SCHOOL_URL . 'assets/css/shortcodes.css', array(), OLAMA_SCHOOL_VERSION);
    }

    /**
     * Shortcode: [olama_teachers_office_hours]
     */
    public function render_teachers_office_hours_shortcode($atts)
    {
        $teachers = Olama_School_Teacher::get_teachers();

        if (empty($teachers)) {
            return '<div class="olama-no-plans">' . __('No teachers found.', 'olama-school') . '</div>';
        }

        ob_start();
        ?>
        <div class="olama-teachers-office-hours-container">
            <div class="olama-search-box">
                <span class="dashicons dashicons-search"></span>
                <input type="text" id="olama-teacher-search"
                    placeholder="<?php _e('Search by teacher name...', 'olama-school'); ?>">
            </div>

            <div class="olama-teachers-grid" id="olama-teachers-list">
                <?php foreach ($teachers as $teacher):
                    $office_hours = Olama_School_Teacher::get_office_hours($teacher->ID);
                    if (empty($office_hours))
                        continue;
                    ?>
                    <div class="olama-teacher-card" data-name="<?php echo esc_attr(strtolower($teacher->display_name)); ?>">
                        <div class="olama-teacher-info">
                            <div class="teacher-avatar">
                                <?php echo get_avatar($teacher->ID, 64); ?>
                            </div>
                            <div class="teacher-details">
                                <h3 class="teacher-name"><?php echo esc_html($teacher->display_name); ?></h3>
                                <div class="teacher-title"><?php _e('Teacher', 'olama-school'); ?></div>
                            </div>
                        </div>
                        <div class="olama-office-hours-list">
                            <?php foreach ($office_hours as $oh): ?>
                                <div class="olama-oh-item">
                                    <span class="oh-day"><?php _e($oh->day_name, 'olama-school'); ?>:</span>
                                    <span class="oh-time"><?php echo esc_html($oh->available_time); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#olama-teacher-search').on('input', function () {
                    var searchTerm = $(this).val().toLowerCase();
                    $('#olama-teachers-list .olama-teacher-card').each(function () {
                        var name = $(this).data('name');
                        if (name.includes(searchTerm)) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
            });
        </script>

        <style>
            .olama-teachers-office-hours-container {
                max-width: 1000px;
                margin: 0 auto;
            }

            .olama-search-box {
                position: relative;
                margin-bottom: 30px;
            }

            .olama-search-box .dashicons {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #64748b;
            }

            .olama-search-box input {
                width: 100%;
                padding: 12px 12px 12px 40px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 1rem;
            }

            .olama-teachers-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }

            .olama-teacher-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s;
            }

            .olama-teacher-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .olama-teacher-info {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 15px;
            }

            .teacher-avatar img {
                border-radius: 50%;
            }

            .teacher-name {
                margin: 0;
                font-size: 1.1rem;
                font-weight: 700;
                color: #1e293b;
            }

            .teacher-title {
                font-size: 0.85rem;
                color: #64748b;
            }

            .olama-office-hours-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .olama-oh-item {
                display: flex;
                justify-content: space-between;
                font-size: 0.9rem;
            }

            .oh-day {
                font-weight: 600;
                color: #475569;
            }

            .oh-time {
                color: #2563eb;
            }

            @media (max-width: 600px) {
                .olama-teachers-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
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
                        <span
                            class="week-label"><?php echo Olama_School_Helpers::translate('الأسبوع الدراسي') . ' ' . $week_ordinal; ?></span>
                        <span
                            class="week-dates">(<?php echo date_i18n('j', strtotime($week_start)); ?>-<?php echo date_i18n('j F', strtotime($week_end)); ?>)</span>
                    </div>
                    <div class="semester-right">
                        <span class="academic-year-label"><?php echo Olama_School_Helpers::translate('العام الدراسي'); ?></span>
                        <span class="academic-year"><?php echo esc_html($academic_year_display); ?></span>
                    </div>
                </div>
            </div>

            <!-- Days Accordion -->
            <div class="days-accordion">
                <?php
                $days_of_week = array('Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس');
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
                                <span class="toggle-chevron dashicons dashicons-arrow-down-alt2"></span>
                                <div class="day-text">
                                    <span class="day-name-ar"><?php echo esc_html($day_ar); ?></span>
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
                                        </div>
                                        <div class="day-date-badge">
                                            <span
                                                class="date-month"><?php echo strtoupper(Olama_School_Helpers::format_date($current_date, false, 'M')); ?></span>
                                            <span
                                                class="date-day"><?php echo Olama_School_Helpers::format_date($current_date, false, 'd'); ?></span>
                                        </div>
                                    </div>
                                    <div class="day-content">
                                        <?php if (empty($day_plans)): ?>
                                                <div class="empty-day">
                                                    <span class="dashicons dashicons-calendar-alt"></span>
                                                    <p><?php echo Olama_School_Helpers::translate('لا توجد حصص مخططة لهذا اليوم'); ?></p>
                                                </div>
                                        <?php else: ?>
                                                <?php foreach ($day_plans as $plan):
                                                    $icon = $get_icon($plan->subject_name);
                                                    $bg_color = $get_subject_bg($plan->subject_name);
                                                    ?>
                                                        <div class="subject-card" style="background: <?php echo esc_attr($bg_color); ?>;">
                                                            <div class="subject-header">
                                                                <span class="dashicons <?php echo $icon; ?> subject-icon"></span>
                                                                <span class="subject-name"><?php echo esc_html($plan->subject_name); ?></span>
                                                                <?php if (isset($plan->plan_type) && $plan->plan_type === 'review'): ?>
                                                                        <span class="review-badge"
                                                                            style="background: #f3e8ff; color: #7c3aed; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; font-weight: 700; margin-right: 8px;">
                                                                            🔄 <?php echo Olama_School_Helpers::translate('Review'); ?>
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
                                                                                <span
                                                                                    class="detail-label"><?php echo Olama_School_Helpers::translate('الوحدة'); ?>:</span>
                                                                                <span class="detail-value"
                                                                                    style="text-align: right;"><?php echo esc_html($plan->unit_name); ?></span>
                                                                            </div>
                                                                    <?php endif; ?>
                                                                    <div class="detail-item">
                                                                        <span class="dashicons dashicons-book-alt detail-icon"></span>
                                                                        <span
                                                                            class="detail-label"><?php echo Olama_School_Helpers::translate('الدرس'); ?>:</span>
                                                                        <span class="detail-value"
                                                                            style="text-align: right;"><?php echo esc_html($plan->lesson_title); ?></span>
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
                                                                                            <span
                                                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('كتاب الطالب'); ?>:</span>
                                                                                        </div>
                                                                                        <span class="hw-value"
                                                                                            style="text-align: right; display: block;"><?php echo esc_html($plan->homework_sb); ?></span>
                                                                                    </div>
                                                                            <?php endif; ?>
                                                                            <?php if ($plan->homework_eb): ?>
                                                                                    <div class="homework-item">
                                                                                        <div class="homework-item-header">
                                                                                            <span class="dashicons dashicons-edit hw-icon"></span>
                                                                                            <span
                                                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('كتاب التمارين'); ?>:</span>
                                                                                        </div>
                                                                                        <span class="hw-value"
                                                                                            style="text-align: right; display: block;"><?php echo esc_html($plan->homework_eb); ?></span>
                                                                                    </div>
                                                                            <?php endif; ?>
                                                                            <?php if ($plan->homework_nb): ?>
                                                                                    <div class="homework-item">
                                                                                        <div class="homework-item-header">
                                                                                            <span class="dashicons dashicons-media-text hw-icon"></span>
                                                                                            <span
                                                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('الدفتر'); ?>:</span>
                                                                                        </div>
                                                                                        <span class="hw-value"
                                                                                            style="text-align: right; display: block;"><?php echo esc_html($plan->homework_nb); ?></span>
                                                                                    </div>
                                                                            <?php endif; ?>
                                                                            <?php if ($plan->homework_ws): ?>
                                                                                    <div class="homework-item">
                                                                                        <div class="homework-item-header">
                                                                                            <span class="dashicons dashicons-media-document hw-icon"></span>
                                                                                            <span
                                                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('الدوسية'); ?>:</span>
                                                                                        </div>
                                                                                        <span class="hw-value"
                                                                                            style="text-align: right; display: block;"><?php echo esc_html($plan->homework_ws); ?></span>
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

                    <!-- Footer -->
                    <div class="plan-footer">
                        <p>&copy; <?php echo date('Y'); ?>                 <?php echo Olama_School_Helpers::translate('مدرستي - جميع الحقوق محفوظة'); ?>
                        </p>
                        <small>Designed for Students & Parents</small>
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
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
        );

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
                            <h1 class="header-title"><?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                                <?php echo $section ? esc_html($section->section_name) : ''; ?>
                            </h1>
                        </div>
                        <div class="header-badge">
                            <span class="badge-icon">📅</span>
                            <span class="badge-text"><?php
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
                            ?></span>
                        </div>
                    </div>

                    <!-- Desktop Grid -->
                    <div class="schedule-grid-desktop">
                        <table class="schedule-table-v2">
                            <thead>
                                <tr>
                                    <th class="day-col-header"><?php echo Olama_School_Helpers::translate('اليوم'); ?></th>
                                    <?php for ($i = 1; $i <= $max_periods; $i++): ?>
                                            <th class="period-col-header">
                                                <span class="period-label-text"><?php echo Olama_School_Helpers::translate('الحصة'); ?></span>
                                                <span class="period-ordinal"><?php echo isset($periods_ar[$i]) ? $periods_ar[$i] : $i; ?></span>
                                            </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days_ar as $day_en => $day_ar): ?>
                                        <tr>
                                            <td class="day-cell">
                                                <span class="day-name"><?php echo esc_html($day_ar); ?></span>
                                            </td>
                                            <?php for ($i = 1; $i <= $max_periods; $i++):
                                                $item = $schedule[$day_en][$i] ?? null;
                                                $colors = $item ? $get_subject_color($item->subject_name) : array('bg' => '#f8fafc', 'text' => '#94a3b8');
                                                ?>
                                                    <td class="subject-cell">
                                                        <?php if ($item): ?>
                                                                <div class="subject-card-v2"
                                                                    style="background: <?php echo esc_attr($colors['bg']); ?>; color: <?php echo esc_attr($colors['text']); ?>;">
                                                                    <span class="subject-text"><?php echo esc_html($item->subject_name); ?></span>
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
                                        <span class="day-icon">📆</span>
                                        <span class="day-title"><?php echo esc_html($day_ar); ?></span>
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
                                                        <?php echo Olama_School_Helpers::translate('الحصة'); ?>
                                                        <?php echo isset($periods_ar[$i]) ? $periods_ar[$i] : $i; ?>
                                                    </span>
                                                    <span class="subject-name-mobile" style="color: <?php echo esc_attr($colors['text']); ?>;">
                                                        <?php echo esc_html($item->subject_name); ?>
                                                    </span>
                                                </div>
                                        <?php endfor; ?>
                                        <?php if (!$has_periods): ?>
                                                <div class="no-periods"><?php echo Olama_School_Helpers::translate('لا توجد حصص'); ?></div>
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
                            <h1><?php echo Olama_School_Helpers::translate('القرطاسية المدرسية'); ?></h1>
                            <p><?php echo Olama_School_Helpers::translate('قائمة المستلزمات المدرسية لكل صف'); ?></p>
                        </div>
                        <div class="header-year">
                            <span class="year-label"><?php echo Olama_School_Helpers::translate('العام الدراسي'); ?></span>
                            <span class="year-value"><?php echo esc_html($year_name); ?></span>
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
                                            <span class="grade-icon">🎒</span>
                                            <span class="grade-name"><?php echo esc_html($item->grade_name); ?></span>
                                        </div>
                                        <div class="header-right">
                                            <span class="toggle-icon">▼</span>
                                        </div>
                                    </div>
                                    <div class="accordion-content" <?php echo $is_first ? 'style="display: block;"' : ''; ?>>
                                        <?php if (!empty($item->notebooks)): ?>
                                                <div class="content-section">
                                                    <div class="section-title">
                                                        <span class="section-icon">📓</span>
                                                        <?php echo Olama_School_Helpers::translate('الدفاتر المطلوبة'); ?>
                                                    </div>
                                                    <div class="section-content">
                                                        <?php echo nl2br(esc_html($item->notebooks)); ?>
                                                    </div>
                                                </div>
                                        <?php endif; ?>

                                        <?php if (!empty($item->stationary)): ?>
                                                <div class="content-section">
                                                    <div class="section-title">
                                                        <span class="section-icon">📎</span>
                                                        <?php echo Olama_School_Helpers::translate('القرطاسية المطلوبة'); ?>
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
                                                        <?php echo Olama_School_Helpers::translate('ملاحظات المعلم'); ?>
                                                    </div>
                                                    <div class="section-content">
                                                        <?php echo nl2br(esc_html($item->teacher_notes)); ?>
                                                    </div>
                                                </div>
                                        <?php endif; ?>

                                        <?php if (empty($item->notebooks) && empty($item->stationary) && empty($item->teacher_notes)): ?>
                                                <div class="empty-state">
                                                    <span class="empty-icon">📭</span>
                                                    <p><?php echo Olama_School_Helpers::translate('لم يتم تحديد قرطاسية لهذا الصف بعد.'); ?></p>
                                                </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer -->
                    <div class="olama-stationary-footer">
                        <p><?php echo Olama_School_Helpers::translate('يرجى إحضار جميع المستلزمات في اليوم الأول من الدراسة'); ?> 📖</p>
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

                <script>
                    document.querySelectorAll('.olama-stationary-accordion .accordion-header').forEach(header => {
                        header.addEventListener('click', () => {
                            const item = header.parentElement;
                            const content = item.querySelector('.accordion-content');
                            const wasActive = item.classList.contains('active');

                            // Close all others
                            document.querySelectorAll('.olama-stationary-accordion .accordion-item').forEach(i => {
                                i.classList.remove('active');
                                i.querySelector('.accordion-content').style.display = 'none';
                            });

                            // Toggle current
                            if (!wasActive) {
                                item.classList.add('active');
                                content.style.display = 'block';
                            }
                        });
                    });
                </script>
                <?php
                return ob_get_clean();
    }
}