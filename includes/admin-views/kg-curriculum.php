<?php
/**
 * KG Curriculum Admin View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="olama-kg-curriculum-wrap">
    <div class="olama-header-section" style="margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?php echo Olama_School_Helpers::translate('KG Evaluation'); ?>
        </h2>
        <p class="description">
            <?php echo Olama_School_Helpers::translate('Manage and create evaluations for KG students.'); ?>
        </p>
    </div>

    <!-- Filter Bar: Year and Grade -->
    <div class="olama-card"
        style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="olama-school-evaluation">
            <input type="hidden" name="tab" value="kg_curriculum">

            <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label class="olama-label"><?php echo Olama_School_Helpers::translate('Academic Year'); ?></label>
                    <select name="academic_year_id" onchange="this.form.submit()" style="width: 100%;">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y->id; ?>" <?php selected($selected_year_id, $y->id); ?>>
                                <?php echo esc_html($y->year_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 200px;">
                    <label class="olama-label"><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
                    <select name="grade_id" onchange="this.form.submit()" style="width: 100%;">
                        <?php foreach ($kg_grades as $g): ?>
                            <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                                <?php echo esc_html($g->grade_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; text-align: right;">
                    <button type="button" class="button button-primary"
                        onclick="jQuery('#add-template-form').toggle();">
                        <span class="dashicons dashicons-plus" style="margin-top: 4px;"></span>
                        <?php _e('Create New Evaluation', 'olama-school'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Add Template Form -->
    <div id="add-template-form" class="olama-card"
        style="display: none; background: #f8fafc; padding: 25px; border: 2px dashed #6366f1; border-radius: 8px; margin-bottom: 25px;">
        <h3 style="margin-top:0;"><?php _e('Create New Evaluation Template', 'olama-school'); ?></h3>
        <form method="post" action="">
            <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
            <input type="hidden" name="olama_kg_action" value="save_template">
            <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
            <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>">

            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <div style="flex: 3;">
                    <label
                        class="olama-label"><?php _e('Evaluation Title (e.g., Progress Report Q1)', 'olama-school'); ?></label>
                    <input type="text" name="template_name" required
                        style="width: 100%; height: 40px; border-radius: 6px; font-size: 1.1em;">
                </div>
                <button type="submit" class="button button-primary"
                    style="height: 40px; padding: 0 30px; font-weight: 600;">
                    <?php _e('Confirm & Create', 'olama-school'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Master View: List of Evaluations -->
    <?php if (!$selected_template_id): ?>
        <div class="olama-card" style="background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="padding: 15px;"><?php _e('Evaluation Title', 'olama-school'); ?></th>
                        <th style="padding: 15px;"><?php _e('Created Date', 'olama-school'); ?></th>
                        <th style="padding: 15px; text-align: right;"><?php _e('Actions', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="3" style="padding: 30px; text-align: center; color: #94a3b8;">
                                <?php _e('No evaluations created yet for this grade.', 'olama-school'); ?>
                            </td>
                        </tr>
                    <?php else:
                        foreach ($templates as $t): ?>
                            <tr>
                                <td style="padding: 15px;"><strong><?php echo esc_html($t->template_name); ?></strong></td>
                                <td style="padding: 15px;">
                                    <?php echo date_i18n(get_option('date_format'), strtotime($t->created_at)); ?>
                                </td>
                                <td style="padding: 15px; text-align: right;">
                                    <a href="<?php echo add_query_arg('template_id', $t->id); ?>" class="button button-secondary">
                                        <span class="dashicons dashicons-layout" style="margin-top: 4px;"></span>
                                        <?php _e('Manage Structure', 'olama-school'); ?>
                                    </a>
                                    <form method="post" action="" style="display: inline;"
                                        onsubmit="return confirm('<?php _e('Delete this evaluation?', 'olama-school'); ?>')">
                                        <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                        <input type="hidden" name="olama_kg_action" value="delete_template">
                                        <input type="hidden" name="id" value="<?php echo $t->id; ?>">
                                        <button type="submit" class="button button-link-delete"
                                            style="color: #ef4444;"><?php _e('Delete', 'olama-school'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Detail View: Managing Specific Evaluation Structure -->
    <?php else: ?>
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #1e293b; color: #fff; padding: 15px 25px; border-radius: 8px;">
            <div>
                <h3 style="margin: 0; color: #fff;"><?php _e('Managing:', 'olama-school'); ?>
                    <?php echo esc_html($current_template->template_name); ?>
                </h3>
                <p style="margin: 5px 0 0; opacity: 0.8; font-size: 0.9em;">
                    <?php _e('Add domains and indicators for this report.', 'olama-school'); ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary" onclick="jQuery('#add-domain-form').toggle();">
                    <?php echo Olama_School_Helpers::translate('Add Domain'); ?>
                </button>
                <a href="<?php echo remove_query_arg('template_id'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 4px;"></span>
                    <?php _e('Back to List', 'olama-school'); ?>
                </a>
            </div>
        </div>

        <!-- Add Domain Form -->
        <div id="add-domain-form" class="olama-card"
            style="display: none; background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
            <form method="post" action="">
                <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                <input type="hidden" name="olama_kg_action" value="save_domain">
                <input type="hidden" name="template_id" value="<?php echo $selected_template_id; ?>">

                <div style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 2;">
                        <label class="olama-label"><?php echo Olama_School_Helpers::translate('Domain Title'); ?></label>
                        <input type="text" name="title_ar" required style="width: 100%;">
                    </div>
                    <div style="flex: 1;">
                        <label class="olama-label"><?php echo Olama_School_Helpers::translate('Sort Order'); ?></label>
                        <input type="number" name="sort_order" value="0" style="width: 100%;">
                    </div>
                    <button type="submit" class="button button-primary"><?php _e('Save', 'olama-school'); ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Curriculum List (Nested) -->
    <?php if ($selected_template_id): ?>
        <div class="kg-curriculum-list">
            <?php if (empty($curriculum)): ?>
                <p class="description">
                    <?php _e('No curriculum defined for this grade yet.', 'olama-school'); ?>
                </p>
            <?php else: ?>
                <?php foreach ($curriculum as $domain): ?>
                    <div class="kg-domain-item olama-card"
                        style="margin-bottom: 30px; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden;">
                        <div class="kg-domain-header"
                            style="background: #f1f5f9; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1;">
                            <h3 style="margin: 0; color: #1e293b;">
                                <?php echo esc_html($domain->title_ar); ?>
                            </h3>
                            <div class="kg-actions">
                                <button type="button" class="button button-small"
                                    onclick="jQuery('#add-cat-<?php echo $domain->id; ?>').toggle();">
                                    <?php echo Olama_School_Helpers::translate('Add Category'); ?>
                                </button>
                                <form method="post" action="" style="display: inline;"
                                    onsubmit="return confirm('<?php _e('Are you sure you want to delete this domain and all its contents?', 'olama-school'); ?>')">
                                    <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                    <input type="hidden" name="olama_kg_action" value="delete_domain">
                                    <input type="hidden" name="id" value="<?php echo $domain->id; ?>">
                                    <button type="submit" class="button button-link-delete" style="color: #ef4444;">
                                        <?php _e('Delete', 'olama-school'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="kg-domain-body" style="padding: 20px;">
                            <!-- Add Category Form -->
                            <div id="add-cat-<?php echo $domain->id; ?>"
                                style="display: none; background: #fff; padding: 15px; border: 1px dashed #6366f1; border-radius: 8px; margin-bottom: 20px;">
                                <form method="post" action="">
                                    <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                    <input type="hidden" name="olama_kg_action" value="save_category">
                                    <input type="hidden" name="domain_id" value="<?php echo $domain->id; ?>">
                                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                                        <div style="flex: 2;">
                                            <label>
                                                <?php echo Olama_School_Helpers::translate('Category Title'); ?>
                                            </label>
                                            <input type="text" name="title_ar" required style="width: 100%;">
                                        </div>
                                        <button type="submit" class="button button-secondary">
                                            <?php _e('Add', 'olama-school'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <?php foreach ($domain->categories as $category): ?>
                                <div class="kg-category-item"
                                    style="margin-bottom: 20px; border-left: 4px solid #6366f1; padding-left: 20px; <?php echo Olama_School_Helpers::is_arabic() ? 'border-left:0; border-right:4px solid #6366f1; padding-left:0; padding-right:20px;' : ''; ?>">
                                    <div class="kg-category-header"
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                        <h4 style="margin: 0; color: #475569;">
                                            <?php echo esc_html($category->title_ar); ?>
                                        </h4>
                                        <div class="kg-actions">
                                            <button type="button" class="button button-small"
                                                onclick="jQuery('#add-ind-<?php echo $category->id; ?>').toggle();">
                                                <?php echo Olama_School_Helpers::translate('Add Indicator'); ?>
                                            </button>
                                            <form method="post" action="" style="display: inline;"
                                                onsubmit="return confirm('<?php _e('Delete this category?', 'olama-school'); ?>')">
                                                <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                                <input type="hidden" name="olama_kg_action" value="delete_category">
                                                <input type="hidden" name="id" value="<?php echo $category->id; ?>">
                                                <button type="submit" class="button button-link-delete" style="color: #ef4444;">
                                                    <?php _e('Delete', 'olama-school'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Add Indicator Form -->
                                    <div id="add-ind-<?php echo $category->id; ?>"
                                        style="display: none; background: #fff; padding: 15px; border: 1px dashed #6366f1; border-radius: 8px; margin-bottom: 15px;">
                                        <form method="post" action="">
                                            <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                            <input type="hidden" name="olama_kg_action" value="save_indicator">
                                            <input type="hidden" name="category_id" value="<?php echo $category->id; ?>">
                                            <div style="display: flex; gap: 10px; align-items: flex-end;">
                                                <div style="flex: 2;">
                                                    <label>
                                                        <?php echo Olama_School_Helpers::translate('Indicator Text'); ?>
                                                    </label>
                                                    <textarea name="indicator_text" required style="width: 100%;" rows="2"></textarea>
                                                </div>
                                                <button type="submit" class="button button-secondary">
                                                    <?php _e('Add', 'olama-school'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <table class="widefat striped" style="border: none; box-shadow: none;">
                                        <tbody>
                                            <?php foreach ($category->indicators as $indicator): ?>
                                                <tr>
                                                    <td style="padding: 10px 0; border: none;">
                                                        <?php echo esc_html($indicator->indicator_text); ?>
                                                    </td>
                                                    <td style="text-align: right; width: 60px; border: none;">
                                                        <form method="post" action=""
                                                            onsubmit="return confirm('<?php _e('Delete this indicator?', 'olama-school'); ?>')">
                                                            <?php wp_nonce_field('olama_kg_curriculum_action', 'olama_kg_curriculum_action'); ?>
                                                            <input type="hidden" name="olama_kg_action" value="delete_indicator">
                                                            <input type="hidden" name="id" value="<?php echo $indicator->id; ?>">
                                                            <button type="submit" class="button button-link"
                                                                style="color: #ef4444; font-size: 12px; padding: 0; min-height: auto;">
                                                                <span class="dashicons dashicons-no-alt" style="font-size: 16px;"></span>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .olama-kg-curriculum-wrap {
        max-width: 1000px;
        margin-top: 20px;
    }

    .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .button-link-delete:hover {
        border-color: transparent !important;
        background: transparent !important;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-kg-curriculum-wrap {
            direction: rtl;
        }

    <?php endif; ?>
</style>