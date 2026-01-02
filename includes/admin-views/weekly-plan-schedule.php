<?php
/**
 * Weekly Plan Schedule View
 */
if (!defined('ABSPATH')) exit;

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

if ($start_idx === false) $start_idx = 0;
if ($last_idx === false) $last_idx = 4; // Default to Thursday

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
<div class="olama-card" style="margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
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
                        <td><?php echo esc_html(__($ss->semester_name, 'olama-school')); ?></td>
                        <td><?php echo esc_html(__($ss->grade_name, 'olama-school')); ?></td>
                        <td><?php echo esc_html(__($ss->section_name, 'olama-school')); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=schedule&grade_id=' . $ss->grade_id . '&section_id=' . $ss->section_id . '&semester_id=' . $ss->semester_id); ?>" class="button button-small"><?php _e('Edit', 'olama-school'); ?></a>
                            <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'delete_full_schedule', 'section_id' => $ss->section_id, 'semester_id' => $ss->semester_id]), 'olama_delete_full_schedule'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Delete this entire schedule?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
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

<div class="olama-filter-section" style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
    <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="page" value="olama-school-plans" />
        <input type="hidden" name="tab" value="schedule" />

        <div>
            <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
            <select name="grade_id" onchange="this.form.submit()">
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                        <?php echo esc_html($grade->grade_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
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
            <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Semester', 'olama-school'); ?></label>
            <select name="semester_id" onchange="this.form.submit()">
                <?php foreach ($semesters as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($selected_semester_id, $s->id); ?>>
                        <?php echo esc_html(__($s->semester_name, 'olama-school')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-left: auto;">
            <button type="button" class="button button-secondary" onclick="window.print()"><span class="dashicons dashicons-printer" style="margin-top: 4px;"></span> <?php _e('Print Schedule', 'olama-school'); ?></button>
        </div>
    </form>
</div>

<div class="olama-schedule-container" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); padding: 5px; overflow-x: auto;">
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
                    <th style="width: 150px; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); color: #fff !important;">
                        <?php _e('Day', 'olama-school'); ?>
                    </th>
                    <?php 
                    $period_labels = [
                        1 => '1 - First', 2 => '2 - Second', 3 => '3 - Third', 4 => '4 - Fourth',
                        5 => '5 - Fifth', 6 => '6 - Sixth', 7 => '7 - Seventh', 8 => '8 - Eighth'
                    ];
                    for ($period = 1; $period <= $periods_to_show; $period++): 
                        $label = $period_labels[$period] ?? $period;
                    ?>
                        <th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); color: #fff !important;">
                            <div style="font-size: 1.1em; font-weight: 700;"><?php echo esc_html(__($label, 'olama-school')); ?></div>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($display_days as $day): ?>
                    <tr>
                        <td style="text-align: center; font-weight: 700; background: #f8f9fa; border-right: 1px solid #eee; color: #2271b1; font-size: 1.1em;">
                            <?php echo esc_html(__($day, 'olama-school')); ?>
                        </td>
                        <?php for ($period = 1; $period <= $periods_to_show; $period++):
                            $item = $schedule[$day][$period] ?? null;
                            $item_subject_id = $item ? $item->subject_id : 0;
                            ?>
                            <td style="padding: 15px; border-right: 1px solid #eee; vertical-align: top;">
                                <select name="schedule[<?php echo esc_attr($day); ?>][<?php echo $period; ?>]" style="width: 100%; font-size: 12px;">
                                    <option value=""><?php _e('-- Select Subject --', 'olama-school'); ?></option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject->id; ?>" <?php selected($item_subject_id, $subject->id); ?>>
                                            <?php echo esc_html($subject->subject_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($item): ?>
                                    <div style="margin-top: 5px; font-size: 11px; color: <?php echo esc_attr($item->color_code ?: '#2271b1'); ?>; font-weight: 600;">
                                        ● <?php _e('Scheduled', 'olama-school'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; padding: 20px;">
            <button type="submit" name="olama_save_bulk_schedule" value="1" class="button button-primary button-large">
                <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                <?php _e('Save Master Schedule', 'olama-school'); ?>
            </button>
        </div>
    </form>
</div>
