<?php
/**
 * Reports View
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

if ($active_tab === 'completion') {
    // Report 1: Plan Status by Teacher
    $teacher_stats = $wpdb->get_results("
        SELECT u.display_name, 
               COUNT(p.id) as total_plans,
               SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved_plans,
               SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted_plans,
               SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft_plans
        FROM {$wpdb->users} u
        JOIN {$wpdb->prefix}olama_teachers t ON u.ID = t.id
        LEFT JOIN {$wpdb->prefix}olama_plans p ON u.ID = p.teacher_id
        GROUP BY u.ID
    ");
    ?>
    <div id="completion-report" class="olama-report-section"
        style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3>
            <?php _e('Teacher Plan Status (Current Term)', 'olama-school'); ?>
        </h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Teacher', 'olama-school'); ?></th>
                    <th><?php _e('Total Plans', 'olama-school'); ?></th>
                    <th><?php _e('Approved', 'olama-school'); ?></th>
                    <th><?php _e('Submitted', 'olama-school'); ?></th>
                    <th><?php _e('Draft', 'olama-school'); ?></th>
                    <th><?php _e('Completion %', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teacher_stats as $ts): ?>
                    <?php $perc = $ts->total_plans > 0 ? round(($ts->approved_plans / $ts->total_plans) * 100) : 0; ?>
                    <tr>
                        <td><strong><?php echo esc_html($ts->display_name); ?></strong></td>
                        <td><?php echo $ts->total_plans; ?></td>
                        <td style="color: #00a32a; font-weight: 600;"><?php echo $ts->approved_plans; ?></td>
                        <td style="color: #dba617;"><?php echo $ts->submitted_plans; ?></td>
                        <td style="color: #666;"><?php echo $ts->draft_plans; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex-grow: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                    <div
                                        style="width: <?php echo $perc; ?>%; height: 100%; background: <?php echo $perc > 80 ? '#00a32a' : ($perc > 50 ? '#dba617' : '#d63638'); ?>;">
                                    </div>
                                </div>
                                <span><?php echo $perc; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
} elseif ($active_tab === 'homework') {
    $grades = Olama_School_Grade::get_grades();
    $selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : ($grades[0]->id ?? 0);

    // Report 2: Homework Summary for selected grade
    $homework_summary = $wpdb->get_results($wpdb->prepare("
        SELECT sec.section_name, COUNT(p.id) as total_homeworks
        FROM {$wpdb->prefix}olama_sections sec
        LEFT JOIN {$wpdb->prefix}olama_plans p ON sec.id = p.section_id AND p.homework_content IS NOT NULL AND p.homework_content != ''
        WHERE sec.grade_id = %d
        GROUP BY sec.id
    ", $selected_grade_id));
    ?>
    <div id="homework-report" class="olama-report-section"
        style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3><?php _e('Homework Summary', 'olama-school'); ?></h3>

        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="olama-school-reports">
            <input type="hidden" name="tab" value="homework">
            <select name="grade_id" onchange="this.form.submit()">
                <?php foreach ($grades as $g): ?>
                    <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                        <?php echo esc_html($g->grade_name); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Section', 'olama-school'); ?></th>
                    <th><?php _e('Total Homework Assignments', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($homework_summary as $hs): ?>
                    <tr>
                        <td><strong><?php echo esc_html($hs->section_name); ?></strong></td>
                        <td><?php echo $hs->total_homeworks; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
