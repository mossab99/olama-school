<?php
/**
 * Weekly Plan Comparison View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;

$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : (isset($_GET['compare_grade_id']) ? intval($_GET['compare_grade_id']) : (isset($grades[0]->id) ? intval($grades[0]->id) : 0));
$sections = Olama_School_Section::get_by_grade($selected_grade_id);

$sec1_id = 0;
if (!empty($sections)) {
    $sec1_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : (isset($_GET['sec1']) ? intval($_GET['sec1']) : intval($sections[0]->id));

    // Validate section belongs to the selected grade
    $is_valid_sec1 = false;
    foreach ($sections as $sec) {
        if (intval($sec->id) === $sec1_id) {
            $is_valid_sec1 = true;
            break;
        }
    }
    if (!$is_valid_sec1) {
        $sec1_id = intval($sections[0]->id);
    }
}

$sec2_id = isset($_GET['sec2']) ? intval($_GET['sec2']) : ($sections[1]->id ?? 0);

// Fetch subjects for this grade
$subjects = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_subjects WHERE grade_id = %d AND is_active = 1", $selected_grade_id));

// Locked to active year and semester
$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;
$active_sem = $active_year_id ? Olama_School_Academic::get_active_semester($active_year_id) : null;
$active_semester_id = $active_sem ? intval($active_sem->id) : 0;
?>

<div class="olama-comparison-container">
    <p><?php _e('Compare progress across sections for the same grade.', 'olama-school'); ?></p>

    <div style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ccd0d4;">
        <form method="get" class="olama-filter-row">
            <input type="hidden" name="page" value="olama-school-plans">
            <input type="hidden" name="tab" value="comparison">
            <div class="olama-filter-item">
                <label><?php echo Olama_School_Helpers::translate('Academic Year'); ?></label>
                <input type="hidden" name="academic_year_id" value="<?php echo esc_attr($active_year_id); ?>" />
                <div
                    style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php echo esc_html($active_year ? $active_year->year_name : '—'); ?>
                    <span
                        style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>
            <div class="olama-filter-item">
                <label><?php _e('Semester', 'olama-school'); ?></label>
                <input type="hidden" name="semester_id" value="<?php echo esc_attr($active_semester_id); ?>" />
                <div
                    style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed;">
                    <?php echo esc_html($active_sem ? Olama_School_Helpers::translate($active_sem->semester_name) : '—'); ?>
                    <span
                        style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo Olama_School_Helpers::translate('Active'); ?>)</span>
                </div>
            </div>
            <div class="olama-filter-item">
                <label><?php _e('Grade', 'olama-school'); ?></label>
                <select name="compare_grade_id" onchange="this.form.submit()">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                            <?php echo esc_html($g->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($sections): ?>
                <div class="olama-filter-item">
                    <label><?php _e('Section 1', 'olama-school'); ?></label>
                    <select name="sec1">
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($sec1_id, $s->id); ?>>
                                <?php echo esc_html($s->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-filter-item">
                    <label><?php _e('Section 2', 'olama-school'); ?></label>
                    <select name="sec2">
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($sec2_id, $s->id); ?>>
                                <?php echo esc_html($s->section_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="button button-primary"><?php _e('Compare', 'olama-school'); ?></button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($sec1_id && $sec2_id): ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div
                style="background: #fff; padding: 25px; border-radius: 8px; border-top: 4px solid #2271b1; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h2><?php echo esc_html(Olama_School_Section::get_section($sec1_id)->section_name ?? Olama_School_Helpers::translate('Section 1')); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('Subject', 'olama-school'); ?></th>
                            <th><?php _e('Current Progress', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $sub):
                            $latest_plan = $wpdb->get_row($wpdb->prepare(
                                "SELECT p.*, u.unit_name, l.lesson_title 
                                 FROM {$wpdb->prefix}olama_plans p
                                 LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id
                                 LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id
                                 WHERE p.section_id = %d AND p.subject_id = %d 
                                 AND p.academic_year_id = %d AND p.semester_id = %d
                                 ORDER BY p.plan_date DESC LIMIT 1",
                                $sec1_id,
                                $sub->id,
                                $active_year_id,
                                $active_semester_id
                            ));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($sub->subject_name); ?></strong></td>
                                <td><?php echo $latest_plan ? esc_html(($latest_plan->unit_name ?? '') . ' - ' . ($latest_plan->lesson_title ?? '')) : '<i style="color:#999">' . Olama_School_Helpers::translate('No data') . '</i>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div
                style="background: #fff; padding: 25px; border-radius: 8px; border-top: 4px solid #d63638; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h2><?php echo esc_html(Olama_School_Section::get_section($sec2_id)->section_name ?? Olama_School_Helpers::translate('Section 2')); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php _e('Subject', 'olama-school'); ?></th>
                            <th><?php _e('Current Progress', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $sub):
                            $latest_plan = $wpdb->get_row($wpdb->prepare(
                                "SELECT p.*, u.unit_name, l.lesson_title 
                                 FROM {$wpdb->prefix}olama_plans p
                                 LEFT JOIN {$wpdb->prefix}olama_curriculum_units u ON p.unit_id = u.id
                                 LEFT JOIN {$wpdb->prefix}olama_curriculum_lessons l ON p.lesson_id = l.id
                                 WHERE p.section_id = %d AND p.subject_id = %d 
                                 AND p.academic_year_id = %d AND p.semester_id = %d
                                 ORDER BY p.plan_date DESC LIMIT 1",
                                $sec2_id,
                                $sub->id,
                                $active_year_id,
                                $active_semester_id
                            ));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($sub->subject_name); ?></strong></td>
                                <td><?php echo $latest_plan ? esc_html(($latest_plan->unit_name ?? '') . ' - ' . ($latest_plan->lesson_title ?? '')) : '<i style="color:#999">' . Olama_School_Helpers::translate('No data') . '</i>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>