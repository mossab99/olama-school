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
if ($selected_section_id && $selected_semester_id) {
    $schedule = Olama_School_Schedule::get_schedule($selected_section_id, $selected_semester_id, 'normal');
}

// Fetch existing visits for this section/semester to display in grid
global $wpdb;
$existing_visits = $wpdb->get_results($wpdb->prepare(
    "SELECT v.*, s.day_name, s.period_number 
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     WHERE s.section_id = %d AND s.semester_id = %d",
    $selected_section_id,
    $selected_semester_id
));

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
                                    
                                    <?php foreach ($visits as $visit): ?>
                                        <div class="olama-visit-tag" style="background: #fef9c3; border: 1px solid #fde047; color: #854d0e; font-size: 10px; padding: 2px 4px; border-radius: 3px; margin-bottom: 2px;">
                                            <span class="dashicons dashicons-visibility" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                            <?php echo date('M j', strtotime($visit->visit_date)); ?>
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
