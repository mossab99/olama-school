<?php
/**
 * Academic Supervision - Plan Visit View
 */
if (!defined('ABSPATH')) exit;

// Reuse logic from weekly-plan-schedule but adapt for supervision
$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;

$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : ($grades[0]->id ?? 0);
$sections = Olama_School_Section::get_by_grade($selected_grade_id, $active_year_id);
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : ($sections[0]->id ?? 0);

$semesters = $active_year_id ? Olama_School_Academic::get_semesters($active_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($active_year_id);
$selected_semester_id = $active_semester ? intval($active_semester->id) : ($semesters[0]->id ?? 0);

// Fetch schedule
$schedule = [];


$all_weeks = Olama_School_Academic::get_academic_weeks($active_year_id, $selected_semester_id);

$months_weeks = array();
if (!empty($all_weeks)) {
    foreach ($all_weeks as $val => $label) {
        if (empty($val)) continue;
        $m_key_start = date('Y-m', strtotime($val));
        $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

        $week_range = Olama_School_Helpers::get_week_range($val);
        $m_key_end = date('Y-m', strtotime($week_range['end']));
        if ($m_key_end !== $m_key_start) {
            $months_weeks[$m_key_end][] = array('val' => $val, 'label' => $label);
        }
    }
    ksort($months_weeks);
}

$selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '';
if (empty($selected_month) || !isset($months_weeks[$selected_month])) {
    $today_month = date('Y-m');
    if (isset($months_weeks[$today_month])) {
        $selected_month = $today_month;
    } elseif (!empty($months_weeks)) {
        $m_keys = array_keys($months_weeks);
        $selected_month = $m_keys[0];
    }
}

$current_month_weeks = $months_weeks[$selected_month] ?? array();

$week_start = isset($_GET['week_start']) ? sanitize_text_field($_GET['week_start']) : '';
$valid_week = false;
if (!empty($week_start)) {
    foreach ($current_month_weeks as $w) {
        if ($w['val'] === $week_start) {
            $valid_week = true;
            break;
        }
    }
}
if (!$valid_week && !empty($current_month_weeks)) {
    $today = current_time('Y-m-d');
    $found_current = false;
    foreach ($current_month_weeks as $w) {
        $w_range = Olama_School_Helpers::get_week_range($w['val']);
        if ($today >= $w_range['start'] && $today <= $w_range['end']) {
            $week_start = $w['val'];
            $found_current = true;
            break;
        }
    }
    if (!$found_current) {
        $week_start = $current_month_weeks[0]['val'] ?? '';
    }
}

$week_range = Olama_School_Helpers::get_week_range($week_start);

// Determine schedule type based on the week being viewed
$schedule_type = Olama_School_Schedule::is_ramadan($week_start) ? 'ramadan' : 'normal';

if ($selected_section_id && $selected_semester_id) {
    $schedule = Olama_School_Schedule::get_schedule($selected_section_id, $selected_semester_id, $schedule_type);
}





// Fetch existing visits for this section/semester to display in grid
global $wpdb;
$existing_visits = $wpdb->get_results($wpdb->prepare(
    "SELECT v.*, s.day_name, s.period_number, u.display_name as supervisor_name 
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
     WHERE s.section_id = %d AND s.semester_id = %d
     AND v.visit_date >= %s AND v.visit_date <= %s",
    $selected_section_id,
    $selected_semester_id,
    $week_range['start'],
    $week_range['end']
));

// Helper for supervisor colors
$supervisor_colors = [];
function get_supervisor_color($id) {
    $palette = [
        ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e'], // Amber
        ['bg' => '#dcfce7', 'border' => '#10b981', 'text' => '#065f46'], // Green
        ['bg' => '#f3e8ff', 'border' => '#a855f7', 'text' => '#6b21a8'], // Purple
        ['bg' => '#e0f2fe', 'border' => '#0ea5e9', 'text' => '#075985'], // Sky
        ['bg' => '#ffedd5', 'border' => '#f97316', 'text' => '#9a3412'], // Orange
        ['bg' => '#ede9fe', 'border' => '#6366f1', 'text' => '#3730a3'], // Indigo
        ['bg' => '#fce7f3', 'border' => '#ec4899', 'text' => '#9d174d'], // Pink
    ];
    return $palette[$id % count($palette)];
}

$visits_map = [];
foreach ($existing_visits as $v) {
    $visits_map[$v->day_name][$v->period_number][] = $v;
}

$all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$settings = get_option('olama_school_settings', array());
$start_day = $settings['start_day'] ?? 'Sunday';
$last_day = $settings['last_day'] ?? 'Thursday';

$start_idx = array_search($start_day, $all_days);
$last_idx = array_search($last_day, $all_days);

if ($start_idx === false) $start_idx = 0;
if ($last_idx === false) $last_idx = 4;

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

$periods_count = 8;
if ($selected_grade_id) {
    $grade = Olama_School_Grade::get_grade($selected_grade_id);
    $periods_count = $grade->periods_count ?? 8;
}

// Get Supervisor Templates
$templates = Olama_School_EV_Template::get_templates($selected_grade_id, $active_year_id, $selected_semester_id, 'supervisor');

?>

<div class="olama-supervision-plan-wrap">
    <div class="olama-filter-section" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <form method="get" class="olama-filter-row">
            <input type="hidden" name="page" value="olama-school-supervision" />
            <input type="hidden" name="tab" value="plan_visit" />
            
            <?php 
            echo Olama_School_Helpers::locked_filter_render(
                Olama_School_Helpers::translate('Academic Year'), 
                Olama_School_Helpers::translate($active_year->year_name ?? ''), 
                'academic_year_id', 
                $active_year_id
            ); 

            echo Olama_School_Helpers::locked_filter_render(
                Olama_School_Helpers::translate('Semester'), 
                Olama_School_Helpers::translate($active_semester->semester_name ?? ''), 
                'semester_id', 
                $selected_semester_id
            ); 
            ?>

            <div class="olama-filter-item">
                <label><?php _e('Grade', 'olama-school'); ?></label>
                <select name="grade_id" onchange="this.form.submit()">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>><?php echo esc_html($g->grade_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Section', 'olama-school'); ?></label>
                <select name="section_id" onchange="this.form.submit()">
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo $s->id; ?>" <?php selected($selected_section_id, $s->id); ?>><?php echo esc_html($s->section_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Month', 'olama-school'); ?></label>
                <select name="plan_month" onchange="this.form.submit()">
                    <?php if (!empty($months_weeks)): foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php echo Olama_School_Helpers::translate('Week Start'); ?></label>
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

                <div class="olama-filter-item">
                    <?php echo Olama_School_Helpers::locked_filter_render(
                        Olama_School_Helpers::translate('Schedule Type'),
                        $schedule_type === 'ramadan' ? Olama_School_Helpers::translate('Ramadan Schedule') : Olama_School_Helpers::translate('Normal Schedule'),
                        'schedule_type',
                        $schedule_type
                    ); ?>
                </div>
        </form>
    </div>

    <div class="olama-schedule-grid-container" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); overflow-x: auto;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr style="background: #0f172a; color: #fff;">
                    <th style="width: 120px; text-align: center; color: #fff;"><?php _e('Day', 'olama-school'); ?></th>
                    <?php for ($p = 1; $p <= $periods_count; $p++): ?>
                        <th style="text-align: center; color: #fff;"><?php echo sprintf(__('Period %d', 'olama-school'), $p); ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($display_days as $day): ?>
                    <tr>
                        <td style="background: #f8fafc; font-weight: 700; text-align: center; color: #1e293b; border-right: 1px solid #e2e8f0;">
                            <?php _e($day, 'olama-school'); ?>
                        </td>
                        <?php for ($p = 1; $p <= $periods_count; $p++): 
                            $item = $schedule[$day][$p] ?? null;
                            $visits = $visits_map[$day][$p] ?? [];
                        ?>
                            <td style="height: 100px; vertical-align: top; padding: 8px; border-right: 1px solid #e2e8f0; position: relative;">
                                <?php if ($item): ?>
                                    <div class="olama-subject-tag" style="background: <?php echo $item->color_code ?: '#3b82f6'; ?>15; color: <?php echo $item->color_code ?: '#3b82f6'; ?>; border: 1px solid <?php echo $item->color_code ?: '#3b82f6'; ?>40; padding: 4px; border-radius: 4px; font-size: 11px; margin-bottom: 5px; font-weight: 600;">
                                        <?php echo esc_html($item->subject_name); ?>
                                    </div>
                                    
                                    <?php foreach ($visits_map[$day][$p] ?? [] as $visit): 
                                        $v_color = get_supervisor_color($visit->supervisor_id);
                                        ?>
                                        <div class="olama-visit-planned" 
                                             style="background: <?php echo $v_color['bg']; ?>; 
                                                    border: 1px solid <?php echo $v_color['border']; ?>;
                                                    color: <?php echo $v_color['text']; ?>;
                                                    padding: 5px; border-radius: 4px; margin-bottom: 5px; font-size: 11px; font-weight: 600;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;">
                                                <div style="display:flex; align-items:center; gap:3px;">
                                                    <span class="dashicons <?php echo $visit->status === 'completed' ? 'dashicons-yes-alt' : 'dashicons-visibility'; ?>" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                    <span style="padding: 1px 4px; border-radius: 2px; background: rgba(255,255,255,0.6); font-size: 8px; text-transform: uppercase;">
                                                        <?php echo $visit->status === 'completed' ? __('Completed', 'olama-school') : __('Planned', 'olama-school'); ?>
                                                    </span>
                                                </div>
                                                <span style="font-size:10px;"><?php echo date_i18n('M j', strtotime($visit->visit_date)); ?></span>
                                            </div>
                                            <div style="font-size: 10px; border-top: 1px solid <?php echo $v_color['border']; ?>50; padding-top: 3px; margin-top: 3px; opacity: 0.9; display: flex; align-items: center;">
                                                <span style="margin-right: 3px;">👤</span> <?php echo esc_html($visit->supervisor_name); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <button type="button" class="button button-small olama-plan-visit-btn" 
                                            data-schedule-id="<?php echo $item->id; ?>" 
                                            data-day="<?php echo $day; ?>" 
                                            data-subject="<?php echo esc_attr($item->subject_name); ?>"
                                            style="width: 100%; margin-top: 5px; font-size: 10px;">
                                        + <?php _e('Plan Visit', 'olama-school'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Plan Visit Modal -->
<div id="olama-plan-visit-modal" class="olama-modal" style="display:none;">
    <div class="olama-modal-content" style="max-width: 500px;">
        <div class="olama-modal-header">
            <h3><?php _e('Plan Supervisor Visit', 'olama-school'); ?></h3>
            <span class="olama-close">&times;</span>
        </div>
        <form id="olama-plan-visit-form">
            <?php wp_nonce_field('olama_plan_visit_nonce'); ?>
            <input type="hidden" name="schedule_id" id="modal-schedule-id">
            <input type="hidden" name="expected_day" id="modal-expected-day">

            <div class="olama-form-group">
                <label><?php _e('Subject', 'olama-school'); ?></label>
                <div id="modal-subject-name" style="font-weight: 600; padding: 10px; background: #f1f5f9; border-radius: 4px;"></div>
            </div>

            <div class="olama-form-group">
                <label><?php _e('Visit Date', 'olama-school'); ?></label>
                <input type="date" name="visit_date" id="modal-visit-date" required class="widefat">
                <p class="description" id="modal-date-error" style="color: #ef4444; display:none;"><?php _e('Selected date does not match the scheduled day.', 'olama-school'); ?></p>
            </div>

            <div class="olama-form-group">
                <label><?php _e('Evaluation Template', 'olama-school'); ?></label>
                <select name="template_id" required class="widefat">
                    <option value=""><?php _e('-- Select Template --', 'olama-school'); ?></option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?php echo $t->id; ?>"><?php echo esc_html($t->template_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-form-group">
                <label><?php _e('Notes (Optional)', 'olama-school'); ?></label>
                <textarea name="notes" rows="3" class="widefat"></textarea>
            </div>

            <div class="olama-modal-footer">
                <button type="submit" class="button button-primary"><?php _e('Confirm Plan', 'olama-school'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.olama-modal { position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; }
.olama-modal-content { background: #fff; padding: 0; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); width: 90%; }
.olama-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.olama-modal-header h3 { margin: 0; }
.olama-close { cursor: pointer; font-size: 24px; }
.olama-form-group { padding: 10px 20px; }
.olama-form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
.olama-modal-footer { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.olama-plan-visit-btn').on('click', function() {
        const data = $(this).data();
        $('#modal-schedule-id').val(data.scheduleId);
        $('#modal-expected-day').val(data.day);
        $('#modal-subject-name').text(data.subject);
        $('#olama-plan-visit-modal').show();
        $('#modal-date-error').hide();
    });

    $('.olama-close').on('click', function() {
        $('.olama-modal').hide();
    });

    $(window).on('click', function(event) {
        if ($(event.target).hasClass('olama-modal')) {
            $('.olama-modal').hide();
        }
    });

    $('#olama-plan-visit-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        // AJAX Save Visit (Will implement handler next)
        $.post(ajaxurl, {
            action: 'olama_save_supervisor_visit',
            data: formData,
            nonce: $('#_wpnonce').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || 'Error saving visit');
            }
        });
    });

    // Simple JS validation for day match (Server will re-validate)
    $('#modal-visit-date').on('change', function() {
        const selectedDate = new Date($(this).val());
        const expectedDay = $('#modal-expected-day').val();
        const actualDay = selectedDate.toLocaleDateString('en-US', { weekday: 'long' });
        
        if (actualDay !== expectedDay) {
            $('#modal-date-error').show();
        } else {
            $('#modal-date-error').hide();
        }
    });
});
</script>
