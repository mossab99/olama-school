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

        $week_start = $atts['week'];
        if (!$week_start) {
            // Default to current week start (Sunday)
            $today = time();
            $week_start = date('Y-m-d', $today - ((int) date('w', $today) * 86400));
        }

        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));
        $all_plans = Olama_School_Plan::get_plans($section_id, $week_start, $week_end);

        $is_admin = current_user_can('manage_options') || current_user_can('olama_view_reports');

        // Filter plans based on status and user role
        $plans = array_filter($all_plans, function ($p) use ($is_admin) {
            if ($is_admin) {
                return true; // Admins can see everything
            }
            return $p->status === 'published';
        });

        if (empty($all_plans)) {
            return '<div class="olama-no-plans" style="padding: 30px; background: #fff1f2; border: 1px solid #fecaca; border-radius: 8px; color: #b91c1c; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No weekly plans found for the selected week.') .
                '</div>';
        }

        if (empty($plans)) {
            return '<div class="olama-no-plans" style="padding: 30px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; color: #92400e; text-align: center; font-weight: 600;">' .
                Olama_School_Helpers::translate('No published plans found for this week, but drafts exist.') .
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
        if ($atts['semester']) {
            global $wpdb;
            $semester_name = $wpdb->get_var($wpdb->prepare("SELECT semester_name FROM {$wpdb->prefix}olama_semesters WHERE id = %d", intval($atts['semester'])));
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
            <!-- Illustrated Header -->
            <div class="plan-header-v2">
                <div class="header-content">
                    <h1 class="header-title">الخطة الأسبوعية</h1>
                    <div class="header-subtitle">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                        <?php echo $section ? esc_html($section->section_name) : ''; ?>
                    </div>
                </div>
            </div>

            <!-- Academic Year & Week Info Bar -->
            <div class="semester-bar">
                <div class="semester-left">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <span class="week-label"><?php echo Olama_School_Helpers::translate('الأسبوع الدراسي الأول'); ?></span>
                    <span
                        class="week-dates">(<?php echo date_i18n('j', strtotime($week_start)); ?>-<?php echo date_i18n('j F', strtotime($week_end)); ?>)</span>
                </div>
                <div class="semester-right">
                    <span class="dashicons dashicons-portfolio"></span>
                    <span class="academic-year-label"><?php echo Olama_School_Helpers::translate('العام الدراسي'); ?></span>
                    <span class="academic-year"><?php
                    $start_year = date('Y', strtotime($week_start));
                    $end_year = $start_year + 1;
                    echo $start_year . '-' . $end_year;
                    ?></span>
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
                                    <span class="day-count"><?php echo Olama_School_Helpers::translate('عدد الواجبات'); ?> -
                                        <?php echo count($day_plans); ?></span>
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
                                        </div>

                                        <!-- Classwork Section -->
                                        <div class="section-block classwork">
                                            <div class="section-label">
                                                <span class="dashicons dashicons-clipboard"></span>
                                                <?php echo Olama_School_Helpers::translate('حصة اليوم'); ?> <span
                                                    class="label-en">(Classwork)</span>
                                            </div>
                                            <div class="detail-list">
                                                <?php if ($plan->unit_name): ?>
                                                    <div class="detail-item">
                                                        <span class="dashicons dashicons-category detail-icon"></span>
                                                        <span
                                                            class="detail-label"><?php echo Olama_School_Helpers::translate('الوحدة'); ?>:</span>
                                                        <span class="detail-value"><?php echo esc_html($plan->unit_name); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="detail-item">
                                                    <span class="dashicons dashicons-book-alt detail-icon"></span>
                                                    <span
                                                        class="detail-label"><?php echo Olama_School_Helpers::translate('الدرس'); ?>:</span>
                                                    <span class="detail-value"><?php echo esc_html($plan->lesson_title); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Homework Section -->
                                        <?php if ($plan->homework_sb || $plan->homework_eb || $plan->homework_nb || $plan->homework_ws): ?>
                                            <div class="section-block homework">
                                                <div class="section-label">
                                                    <span class="dashicons dashicons-admin-home"></span>
                                                    <?php echo Olama_School_Helpers::translate('الواجب البيتي'); ?>
                                                </div>
                                                <div class="homework-list">
                                                    <?php if ($plan->homework_sb): ?>
                                                        <div class="homework-item">
                                                            <span class="dashicons dashicons-book hw-icon"></span>
                                                            <span
                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('كتاب الطالب'); ?>:</span>
                                                            <span class="hw-value"><?php echo esc_html($plan->homework_sb); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_eb): ?>
                                                        <div class="homework-item">
                                                            <span class="dashicons dashicons-edit hw-icon"></span>
                                                            <span
                                                                class="hw-label"><?php echo Olama_School_Helpers::translate('كتاب التمارين'); ?>:</span>
                                                            <span class="hw-value"><?php echo esc_html($plan->homework_eb); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_nb): ?>
                                                        <div class="homework-item">
                                                            <span class="dashicons dashicons-media-text hw-icon"></span>
                                                            <span class="hw-label"><?php echo Olama_School_Helpers::translate('الدفتر'); ?>:</span>
                                                            <span class="hw-value"><?php echo esc_html($plan->homework_nb); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_ws): ?>
                                                        <div class="homework-item">
                                                            <span class="dashicons dashicons-media-document hw-icon"></span>
                                                            <span class="hw-label"><?php echo Olama_School_Helpers::translate('الدوسية'); ?>:</span>
                                                            <span class="hw-value"><?php echo esc_html($plan->homework_ws); ?></span>
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
                                                <div class="notes-content">
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
                <p>&copy; <?php echo date('Y'); ?>         <?php echo Olama_School_Helpers::translate('مدرستي - جميع الحقوق محفوظة'); ?>
                </p>
                <small>Designed for Students & Parents</small>
            </div>

            <script>
                document.querySelectorAll('.day-header').forEach(header => {
                    header.addEventListener('click', () => {
                        const item = header.parentElement;
                        const wasActive = item.classList.contains('active');

                        document.querySelectorAll('.day-item').forEach(i => i.classList.remove('active'));

                        if (!wasActive) {
                            item.classList.add('active');
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
        $semester_id = intval($atts['semester']);

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

        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday');
        $max_periods = $grade ? intval($grade->periods_count) : 8;

        ob_start();
        ?>
        <div class="olama-shortcode-schedule-container">
            <div class="olama-schedule-header">
                <h2><?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                    <?php echo $section ? esc_html($section->section_name) : ''; ?>
                </h2>
                <div class="olama-schedule-meta">
                    <span class="dashicons dashicons-calendar"></span>
                    <?php echo $semester ? esc_html($semester->semester_name) : ''; ?>
                </div>
            </div>

            <div class="olama-schedule-desktop">
                <table class="olama-schedule-table">
                    <thead>
                        <tr>
                            <th><?php _e('Day / Period', 'olama-school'); ?></th>
                            <?php for ($i = 1; $i <= $max_periods; $i++): ?>
                                <th><?php echo $i; ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td class="day-label"><strong><?php _e($day, 'olama-school'); ?></strong></td>
                                <?php for ($i = 1; $i <= $max_periods; $i++):
                                    $item = $schedule[$day][$i] ?? null;
                                    ?>
                                    <td class="schedule-cell <?php echo $item ? 'has-subject' : ''; ?>"
                                        style="<?php echo $item ? 'border-left: 4px solid ' . esc_attr($item->color_code) . ';' : ''; ?>">
                                        <?php if ($item): ?>
                                            <div class="subject-name" style="color: <?php echo esc_attr($item->color_code); ?>">
                                                <?php echo esc_html($item->subject_name); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="olama-schedule-mobile">
                <?php foreach ($days as $day): ?>
                    <div class="olama-mobile-day">
                        <h3><?php _e($day, 'olama-school'); ?></h3>
                        <div class="olama-mobile-periods">
                            <?php for ($i = 1; $i <= $max_periods; $i++):
                                $item = $schedule[$day][$i] ?? null;
                                if (!$item)
                                    continue;
                                ?>
                                <div class="olama-mobile-period-item"
                                    style="border-left: 4px solid <?php echo esc_attr($item->color_code); ?>">
                                    <span class="period-label"><?php printf(__('Period %d', 'olama-school'), $i); ?>:</span>
                                    <span class="subject-label"
                                        style="color: <?php echo esc_attr($item->color_code); ?>"><?php echo esc_html($item->subject_name); ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}