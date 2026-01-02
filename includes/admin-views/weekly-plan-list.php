<?php
/**
 * Weekly Plan List View
 */
if (!defined('ABSPATH')) {
    exit;
}

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
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Month', 'olama-school'); ?>
                </label>
                <select name="plan_month" onchange="this.form.submit()">
                    <?php foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Week Start', 'olama-school'); ?>
                </label>
                <select name="week_start" onchange="this.form.submit()">
                    <?php
                    $w_count = 1;
                    foreach ($current_month_weeks as $w): ?>
                        <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                            <?php echo sprintf(__('%s %d', 'olama-school'), __('Week', 'olama-school'), $w_count) . ' ' . esc_html($w['label']); ?>
                        </option>
                        <?php $w_count++; endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Grade', 'olama-school'); ?>
                </label>
                <select name="grade_id" onchange="this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Section', 'olama-school'); ?>
                </label>
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
                    data-section="<?php echo $selected_section_id; ?>" data-week="<?php echo esc_attr($week_start); ?>"
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
                    <strong style="display: block; color: #1d2327;">
                        <?php echo __($day_name, 'olama-school'); ?>
                    </strong>
                    <small style="color: #666;">
                        <?php echo date_i18n('M d', strtotime($date)); ?>
                    </small>
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
                                    <div style="font-size: 0.75em; color: #777; border-top: 1px solid #eee; pt: 6px; margin-top: 6px;">
                                        <i class="dashicons dashicons-book-alt"
                                            style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></i>
                                        <?php echo $plan->homework_sb ? __('SB:', 'olama-school') . ' ' . esc_html($plan->homework_sb) : ''; ?>
                                        <?php echo $plan->homework_eb ? ' ' . __('EB:', 'olama-school') . ' ' . esc_html($plan->homework_eb) : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #ccc; font-style: italic; font-size: 0.85em; margin-top: 20px;">
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