<?php
/**
 * Weekly Plan Load Management View
 */
if (!defined('ABSPATH'))
    exit;

$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['manage_grade']) ? intval($_GET['manage_grade']) : 0;

$subjects = [];
if ($selected_grade_id) {
    $subjects = Olama_School_Subject::get_by_grade($selected_grade_id);
}

if (isset($_GET['message'])) {
    if ($_GET['message'] === 'plan_load_saved') {
        echo '<div class="updated notice is-dismissible"><p>' . __('Plan Load settings saved successfully.', 'olama-school') . '</p></div>';
    } elseif ($_GET['message'] === 'plan_load_warning') {
        $errors = get_transient('olama_plan_load_errors');
        if ($errors) {
            echo '<div class="notice notice-warning is-dismissible"><ul>';
            foreach ($errors as $error) {
                echo '<li><strong>' . esc_html($error) . '</strong></li>';
            }
            echo '</ul><p>' . __('Settings were saved, but some limits were adjusted to respect grade constraints.', 'olama-school') . '</p></div>';
            delete_transient('olama_plan_load_errors');
        }
    }
}
?>

<div class="olama-plan-load-container"
    style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <h1 style="margin-top: 0; color: #1e293b; font-size: 24px; font-weight: 700;">
        <span class="dashicons dashicons-chart-bar"
            style="font-size: 28px; width: 28px; height: 28px; margin-right: 10px; color: #2271b1;"></span>
        <?php _e('Plan Load Management', 'olama-school'); ?>
    </h1>
    <p class="description" style="font-size: 14px; margin-bottom: 30px;">
        <?php _e('Manage the maximum number of plans allowed per week. Define limits at the grade level or for specific subjects.', 'olama-school'); ?>
    </p>

    <form method="post">
        <?php wp_nonce_field('olama_save_plan_load', 'olama_plan_load_nonce'); ?>
        <input type="hidden" name="manage_grade_id" value="<?php echo $selected_grade_id; ?>">

        <div class="olama-card"
            style="margin-bottom: 40px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 16px; color: #334155;">
                    <?php _e('Grades & Sections Limits', 'olama-school'); ?>
                </h3>
            </div>
            <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Grade Name', 'olama-school'); ?>
                        </th>
                        <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Grade Level', 'olama-school'); ?>
                        </th>
                        <th style="padding: 12px 20px; font-weight: 700; width: 180px;">
                            <?php _e('Max Weekly Plans', 'olama-school'); ?></th>
                        <th style="padding: 12px 20px; font-weight: 700; width: 150px; text-align: center;">
                            <?php _e('Actions', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $grade):
                        $is_selected = ($selected_grade_id === intval($grade->id));
                        ?>
                        <tr style="<?php echo $is_selected ? 'background: #eff6ff;' : ''; ?>">
                            <td style="padding: 12px 20px; font-weight: 600; color: #1e293b;">
                                <?php echo esc_html($grade->grade_name); ?>
                                <input type="hidden" name="grade_name[<?php echo $grade->id; ?>]"
                                    value="<?php echo esc_attr($grade->grade_name); ?>">
                                <input type="hidden" name="grade_level[<?php echo $grade->id; ?>]"
                                    value="<?php echo esc_attr($grade->grade_level); ?>">
                            </td>
                            <td style="padding: 12px 20px;"><?php echo esc_html($grade->grade_level); ?></td>
                            <td style="padding: 12px 20px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="number" name="grade_limit[<?php echo $grade->id; ?>]"
                                        value="<?php echo intval($grade->max_weekly_plans); ?>" min="0"
                                        style="width: 70px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 4px 8px;">
                                    <span
                                        style="font-size: 11px; color: #64748b;"><?php _e('plans', 'olama-school'); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 20px; text-align: center;">
                                <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=load&manage_grade=' . $grade->id); ?>"
                                    class="button button-small <?php echo $is_selected ? 'button-primary' : ''; ?>"
                                    style="display: inline-flex; align-items: center; gap: 4px;">
                                    <span class="dashicons dashicons-list-view"
                                        style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                    <?php _e('Manage Subjects', 'olama-school'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($selected_grade_id):
            $current_grade = null;
            foreach ($grades as $g) {
                if (intval($g->id) === $selected_grade_id) {
                    $current_grade = $g;
                    break;
                }
            }
            ?>
            <div class="olama-card"
                style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; animation: slideDown 0.3s ease-out;">
                <div
                    style="background: #2271b1; padding: 15px 20px; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 16px; color: #fff;">
                        <?php printf(__('Subject Limits for %s', 'olama-school'), esc_html($current_grade->grade_name)); ?>
                    </h3>
                    <span
                        style="font-size: 12px; opacity: 0.9;"><?php _e('Overrides grade-level limit for specific subjects', 'olama-school'); ?></span>
                </div>
                <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Subject Name', 'olama-school'); ?>
                            </th>
                            <th style="padding: 12px 20px; font-weight: 700;"><?php _e('Color', 'olama-school'); ?></th>
                            <th style="padding: 12px 20px; font-weight: 700; width: 250px;">
                                <?php _e('Max Weekly Plans', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($subjects): ?>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td style="padding: 12px 20px; font-weight: 600; color: #334155;">
                                        <?php echo esc_html($subject->subject_name); ?>
                                    </td>
                                    <td style="padding: 12px 20px;">
                                        <span
                                            style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?php echo esc_attr($subject->color_code); ?>; margin-right: 5px;"></span>
                                        <code style="font-size: 11px;"><?php echo esc_html($subject->color_code); ?></code>
                                    </td>
                                    <td style="padding: 12px 20px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <input type="number" name="subject_limit[<?php echo $subject->id; ?>]"
                                                value="<?php echo intval($subject->max_weekly_plans); ?>" min="0"
                                                style="width: 70px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 4px 8px;">
                                            <span
                                                style="font-size: 11px; color: #64748b;"><?php _e('plans', 'olama-school'); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="padding: 30px; text-align: center; color: #94a3b8;">
                                    <?php _e('No subjects found for this grade.', 'olama-school'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div
            style="margin-top: 35px; padding-top: 20px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 15px;">
            <?php if ($selected_grade_id): ?>
                <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=load'); ?>"
                    class="button button-secondary"><?php _e('Close Subject Limits', 'olama-school'); ?></a>
            <?php endif; ?>
            <button type="submit" name="olama_save_plan_load" value="1" class="button button-primary button-large"
                style="height: 40px; padding: 0 25px;">
                <span class="dashicons dashicons-saved" style="margin-top: 8px;"></span>
                <?php _e('Save All Load Settings', 'olama-school'); ?>
            </button>
        </div>
    </form>
</div>
<style>
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .olama-card table tr:hover td {
        background: rgba(34, 113, 177, 0.02);
    }
</style>