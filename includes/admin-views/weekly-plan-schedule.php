<?php
/**
 * Weekly Plan Schedule View
 */
if (!defined('ABSPATH')) exit;

$grades = Olama_School_Grade::get_grades();
$teachers = Olama_School_Teacher::get_teachers();
$is_admin = Olama_School_Permissions::can('olama_manage_plans_schedule');

$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : ($grades[0]->id ?? 0);

$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = $active_year ? $active_year->id : 0;

$sections = Olama_School_Section::get_by_grade($selected_grade_id, $selected_year_id);

// Ensure selected section belongs to the selected grade
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$section_exists_in_grade = false;
if ($selected_section_id && $sections) {
    foreach ($sections as $sec) {
        if ($sec->id == $selected_section_id) {
            $section_exists_in_grade = true;
            break;
        }
    }
}

if (!$section_exists_in_grade) {
    $selected_section_id = $sections[0]->id ?? 0;
}

$selected_teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

// Local mapping for days
$days_map = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$settings = get_option('olama_school_settings', array());
$start_day_name = $settings['start_day'] ?? 'Monday';
$last_day_name = $settings['last_day'] ?? 'Thursday';

$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();

// Locked to active semester
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$selected_semester_id = $active_semester ? intval($active_semester->id) : ($semesters[0]->id ?? 0);

$selected_schedule_type = Olama_School_Schedule::is_ramadan() ? 'ramadan' : 'normal';


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
    $schedule = Olama_School_Schedule::get_schedule($selected_section_id, $selected_semester_id, $selected_schedule_type);
}

// Subjects for dropdown
$subjects = $selected_grade_id ? Olama_School_Subject::get_by_grade($selected_grade_id, true) : [];

$scheduled_sections = Olama_School_Schedule::get_scheduled_sections($selected_schedule_type, $selected_year_id, $selected_semester_id);
?>

<?php if (isset($_GET['message'])): ?>
    <?php if ($_GET['message'] === 'schedule_saved'): ?>
        <div class="updated notice is-dismissible">
            <p><?php _e('Master schedule saved successfully.', 'olama-school'); ?></p>
        </div>
    <?php elseif ($_GET['message'] === 'import_success'): ?>
        <div class="updated notice is-dismissible">
            <p><?php echo sprintf(Olama_School_Helpers::translate('Schedule imported successfully! %d items added.'), intval($_GET['count'] ?? 0)); ?></p>
        </div>
    <?php elseif ($_GET['message'] === 'import_error_nofile'): ?>
        <div class="error notice is-dismissible">
            <p><?php echo Olama_School_Helpers::translate('Please select a file to import.'); ?></p>
        </div>
    <?php elseif ($_GET['message'] === 'import_error_invalid'): ?>
        <div class="error notice is-dismissible">
            <p><?php echo Olama_School_Helpers::translate('Invalid CSV file format.'); ?></p>
        </div>
    <?php elseif ($_GET['message'] === 'import_error_nodata'): ?>
        <div class="error notice is-dismissible">
            <p><?php echo Olama_School_Helpers::translate('No data found in CSV file.'); ?></p>
        </div>
    <?php elseif ($_GET['message'] === 'import_error_file'): ?>
        <div class="error notice is-dismissible">
            <p><?php echo Olama_School_Helpers::translate('Error processing import file.'); ?></p>
        </div>
    <?php endif; ?>
<?php endif; ?>


<div class="olama-filter-section" style="margin-bottom: 20px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <form method="get" id="olama-schedule-filter-form" class="olama-filter-row" style="margin: 0;">
            <input type="hidden" name="page" value="olama-school-plans" />
            <input type="hidden" name="tab" value="schedule" />

            <div class="olama-filter-item">
                <label><?php echo Olama_School_Helpers::translate('Academic Year'); ?></label>
                <input type="hidden" name="academic_year_id" value="<?php echo esc_attr($selected_year_id); ?>" />
                <div style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php echo esc_html($active_year ? $active_year->year_name : '—'); ?>
                    <span style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Semester', 'olama-school'); ?></label>
                <input type="hidden" name="semester_id" value="<?php echo esc_attr($selected_semester_id); ?>" />
                <div style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php echo esc_html($active_semester ? Olama_School_Helpers::translate($active_semester->semester_name) : '—'); ?>
                    <span style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Grade', 'olama-school'); ?></label>
                <select name="grade_id" onchange="document.getElementById('olama-section-select').value='0'; this.form.submit()">
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <label><?php _e('Section', 'olama-school'); ?></label>
                <select name="section_id" id="olama-section-select" onchange="this.form.submit()">
                    <option value="0"><?php _e('-- Select Section --', 'olama-school'); ?></option>
                    <?php if ($sections): ?>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>>
                                <?php echo esc_html($section->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="olama-filter-item">
                <?php echo Olama_School_Helpers::locked_filter_render(
                    Olama_School_Helpers::translate('Schedule Type'),
                    $selected_schedule_type === 'ramadan' ? Olama_School_Helpers::translate('Ramadan Schedule') : Olama_School_Helpers::translate('Normal Schedule'),
                    'schedule_type',
                    $selected_schedule_type
                ); ?>
            </div>
        </form>

        <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
            <!-- Export Schedule CSV -->
            <form method="post" action="" style="display: inline; margin: 0;">
                <?php wp_nonce_field('olama_export_schedule'); ?>
                <input type="hidden" name="olama_export_schedule" value="1" />
                <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>" />
                <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                <input type="hidden" name="schedule_type" value="<?php echo $selected_schedule_type; ?>" />
                <button type="submit" class="button" <?php echo (!$selected_section_id || !$selected_semester_id) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                    <?php echo Olama_School_Helpers::translate('Export Schedule (CSV)'); ?>
                </button>
            </form>

            <?php if ($is_admin): ?>
                <!-- Import Schedule CSV -->
                <form method="post" action="" enctype="multipart/form-data" style="display: inline-flex; gap: 5px; align-items: center; margin: 0;">
                    <?php wp_nonce_field('olama_import_schedule'); ?>
                    <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>" />
                    <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                    <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                    <input type="hidden" name="schedule_type" value="<?php echo $selected_schedule_type; ?>" />
                    <input type="file" name="olama_schedule_file" accept=".csv" required style="font-size: 12px;" <?php echo (!$selected_section_id || !$selected_semester_id) ? 'disabled' : ''; ?> />
                    <button type="submit" name="olama_import_schedule" value="1" class="button button-primary" <?php echo (!$selected_section_id || !$selected_semester_id) ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                        <?php echo Olama_School_Helpers::translate('Import Schedule'); ?>
                    </button>
                </form>
            <?php endif; ?>

            <div style="border-left: 1px solid #ddd; height: 30px;"></div>

            <!-- Download PDF Button -->
            <button type="button" class="button button-secondary" onclick="olamaPrintSchedule()">
                <span class="dashicons dashicons-pdf" style="margin-top: 4px;"></span>
                <?php echo Olama_School_Helpers::translate('Download PDF'); ?>
            </button>

            <!-- Print Schedule Button -->
            <button type="button" class="button button-secondary" onclick="window.print()">
                <span class="dashicons dashicons-printer" style="margin-top: 4px;"></span>
                <?php _e('Print Schedule', 'olama-school'); ?>
            </button>
        </div>
    </div>
</div>

<script>
function olamaPrintSchedule() {
    const container = document.querySelector('.olama-schedule-container');
    if (!container) return;

    // Convert all select elements to plain text for printing
    const scheduleTable = container.querySelector('table');
    const selects = scheduleTable.querySelectorAll('select');
    const selectReplacements = [];
    
    selects.forEach(function(select) {
        const selectedOption = select.options[select.selectedIndex];
        let selectedText = selectedOption ? selectedOption.text : '';
        
        // Don't print the placeholder "-- Select Subject --"
        if (selectedText.includes('--') || select.value === '') {
            selectedText = '';
        }
        
        const span = document.createElement('span');
        span.textContent = selectedText;
        span.className = 'print-only-text';
        
        selectReplacements.push({
            select: select,
            span: span,
            parent: select.parentNode
        });
        
        select.parentNode.replaceChild(span, select);
    });
    
    // Trigger print
    window.print();
    
    // Restore
    setTimeout(function() {
        selectReplacements.forEach(function(item) {
            item.parent.replaceChild(item.select, item.span);
        });
    }, 500);
}
</script>

<div class="olama-schedule-container" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); padding: 5px; overflow-x: auto;">
    <!-- Dedicated Print Header -->
    <div class="olama-print-header">
        <h1><?php echo esc_html(get_option('olama_school_settings', [])['school_name_ar'] ?? 'أكاديمية علماء المستقبل'); ?></h1>
        <p>
            <?php echo esc_html($grades[array_search($selected_grade_id, array_column($grades, 'id'))]->grade_name ?? ''); ?> - 
            <?php echo esc_html($sections[array_search($selected_section_id, array_column($sections, 'id'))]->section_name ?? ''); ?> - 
            <?php echo esc_html(Olama_School_Helpers::translate($semesters[array_search($selected_semester_id, array_column($semesters, 'id'))]->semester_name ?? '')); ?>
        </p>
    </div>

    <?php if ($is_admin): ?>
        <div style="display: flex; justify-content: space-between; padding: 10px; align-items: center;">
            <div>
                <?php if ($selected_schedule_type === 'ramadan' && empty($schedule)): ?>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('olama_clone_schedule'); ?>
                        <input type="hidden" name="olama_clone_schedule" value="1" />
                        <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>" />
                        <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
                        <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
                        <input type="hidden" name="from_type" value="normal" />
                        <input type="hidden" name="to_type" value="ramadan" />
                        <button type="submit" class="button">
                            <span class="dashicons dashicons-admin-page" style="margin-top: 4px;"></span>
                            <?php echo Olama_School_Helpers::translate('Clone Normal to Ramadan'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <button type="submit" form="olama-schedule-main-form" name="olama_save_bulk_schedule" value="1" class="button button-primary" <?php echo (!$selected_section_id || !$selected_semester_id) ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                <?php _e('Save Master Schedule', 'olama-school'); ?>
            </button>
        </div>
    <?php endif; ?>

    <form method="post" id="olama-schedule-main-form">
        <?php wp_nonce_field('olama_save_bulk_schedule', 'olama_schedule_nonce'); ?>

        <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>" />
        <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>" />
        <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>" />
        <input type="hidden" name="schedule_type" value="<?php echo $selected_schedule_type; ?>" />

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
                                <select name="schedule[<?php echo esc_attr($day); ?>][<?php echo $period; ?>]" style="width: 100%; font-size: 12px;" <?php disabled(!$is_admin); ?>>
                                    <option value=""><?php _e('-- Select Subject --', 'olama-school'); ?></option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject->id; ?>" <?php selected($item_subject_id, $subject->id); ?>>
                                            <?php echo esc_html($subject->subject_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($item): ?>
                                    <div style="margin-top: 5px; font-size: 11px; color: <?php echo esc_attr($item->color_code ?: '#2271b1'); ?>; font-weight: 600;">
                                        ◈ <?php _e('Scheduled', 'olama-school'); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($is_admin): ?>
            <div style="display: flex; justify-content: flex-end; padding: 20px;">
                <button type="submit" name="olama_save_bulk_schedule" value="1" class="button button-primary button-large" <?php echo (!$selected_section_id || !$selected_semester_id) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                    <?php _e('Save Master Schedule', 'olama-school'); ?>
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Schedule List Section -->
<div class="olama-card" style="margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
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
                        <td><?php echo esc_html(Olama_School_Helpers::translate($ss->semester_name)); ?></td>
                        <td><?php echo esc_html(__($ss->grade_name, 'olama-school')); ?></td>
                        <td><?php echo esc_html(__($ss->section_name, 'olama-school')); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=schedule&grade_id=' . $ss->grade_id . '&section_id=' . $ss->section_id . '&semester_id=' . $ss->semester_id . '&schedule_type=' . $selected_schedule_type); ?>" class="button button-small"><?php _e('View', 'olama-school'); ?></a>
                            <?php if ($is_admin): ?>
                                <a href="<?php echo wp_nonce_url(add_query_arg(['action' => 'delete_full_schedule', 'section_id' => $ss->section_id, 'semester_id' => $ss->semester_id, 'schedule_type' => $selected_schedule_type]), 'olama_delete_full_schedule'); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Delete this entire schedule?', 'olama-school'); ?>')"><?php _e('Delete', 'olama-school'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">
            <?php _e('No schedules defined yet. Use the filters above to create one.', 'olama-school'); ?>
        </p>
    <?php endif; ?>
</div>