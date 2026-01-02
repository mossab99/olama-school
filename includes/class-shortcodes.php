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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_shortcode_assets'));
    }

    /**
     * Enqueue Shortcode Assets
     */
    public function enqueue_shortcode_assets()
    {
        wp_enqueue_style('olama-shortcodes-css', OLAMA_SCHOOL_URL . 'assets/css/shortcodes.css', array(), OLAMA_SCHOOL_VERSION);
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
        $plans = Olama_School_Plan::get_plans($section_id, $week_start, $week_end);

        // Filter to only show published plans in frontend
        $plans = array_filter($plans, function ($p) {
            return $p->status === 'published';
        });

        if (empty($plans)) {
            return '<div class="olama-no-plans">' . __('No published weekly plan found for the selected week.', 'olama-school') . '</div>';
        }

        // Group plans by date
        $grouped_plans = array();
        foreach ($plans as $plan) {
            $grouped_plans[$plan->plan_date][] = $plan;
        }

        // Get Section/Grade info for the header
        $section = Olama_School_Section::get_section($section_id);
        $grade = $section ? Olama_School_Grade::get_grade($section->grade_id) : null;

        ob_start();
        ?>
        <div class="olama-shortcode-weekly-plan-container">
            <div class="olama-plan-header">
                <div class="olama-plan-meta">
                    <h2 class="olama-grade-section"><?php echo $grade ? esc_html($grade->grade_name) : ''; ?> -
                        <?php echo $section ? esc_html($section->section_name) : ''; ?>
                    </h2>
                    <div class="olama-plan-week-range">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo date('M d', strtotime($week_start)); ?> - <?php echo date('M d, Y', strtotime($week_end)); ?>
                    </div>
                </div>
            </div>

            <div class="olama-days-grid">
                <?php
                $days_of_week = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday');
                foreach ($days_of_week as $day_name):
                    $current_date = date('Y-m-d', strtotime($week_start . ' +' . array_search($day_name, $days_of_week) . ' days'));
                    $day_plans = $grouped_plans[$current_date] ?? array();
                    ?>
                    <div class="olama-day-card <?php echo empty($day_plans) ? 'no-plans' : ''; ?>">
                        <div class="olama-day-header">
                            <span class="day-name"><?php _e($day_name, 'olama-school'); ?></span>
                            <span class="day-date"><?php echo date('M d', strtotime($current_date)); ?></span>
                        </div>
                        <div class="olama-day-plans">
                            <?php if (empty($day_plans)): ?>
                                <p class="no-content"><?php _e('No lessons planned', 'olama-school'); ?></p>
                            <?php else: ?>
                                <?php foreach ($day_plans as $plan): ?>
                                    <div class="olama-plan-item" style="border-left-color: <?php echo esc_attr($plan->color_code); ?>">
                                        <div class="plan-subject" style="color: <?php echo esc_attr($plan->color_code); ?>">
                                            <?php echo esc_html($plan->subject_name); ?>
                                        </div>
                                        <div class="plan-lesson">
                                            <?php if ($plan->unit_number): ?>
                                                <span
                                                    class="lesson-num"><?php echo esc_html($plan->unit_number . '.' . $plan->lesson_number); ?></span>
                                            <?php endif; ?>
                                            <span class="lesson-title"><?php echo esc_html($plan->lesson_title); ?></span>
                                        </div>
                                        <?php if ($plan->homework_sb || $plan->homework_eb || $plan->homework_nb || $plan->homework_ws): ?>
                                            <div class="plan-homework">
                                                <div class="homework-icon">
                                                    <span class="dashicons dashicons-welcome-edit-page"></span>
                                                    <span><?php _e('Homework', 'olama-school'); ?>:</span>
                                                </div>
                                                <div class="homework-details">
                                                    <?php if ($plan->homework_sb): ?>
                                                        <div class="hw-part"><strong>SB:</strong> <?php echo esc_html($plan->homework_sb); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_eb): ?>
                                                        <div class="hw-part"><strong>EB:</strong> <?php echo esc_html($plan->homework_eb); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_nb): ?>
                                                        <div class="hw-part"><strong>NB:</strong> <?php echo esc_html($plan->homework_nb); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($plan->homework_ws): ?>
                                                        <div class="hw-part"><strong>WS:</strong> <?php echo esc_html($plan->homework_ws); ?></div>
                                                    <?php endif; ?>
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
