<?php
/**
 * Stationary Management View
 */
if (!defined('ABSPATH')) {
    exit;
}

$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = $active_year ? intval($active_year->id) : 0;
?>

<div class="olama-stationary-wrap">
    <div class="olama-header-section" style="margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.5em;">
            <?php echo Olama_School_Helpers::translate('Stationary'); ?>
        </h2>
        <p class="description">
            <?php echo Olama_School_Helpers::translate('Define required notebooks and stationary for each grade.'); ?>
        </p>
    </div>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'stationary_saved'): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php echo Olama_School_Helpers::translate('Stationary requirements saved successfully.'); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="olama-filter-bar"
        style="background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px;">
        <form method="get" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="olama-school-academic">
            <input type="hidden" name="tab" value="stationary">

            <?php
            $year_name = '—';
            foreach ($years as $year) {
                if ($year->id == $selected_year_id) {
                    $year_name = $year->year_name;
                    break;
                }
            }
            echo Olama_School_Helpers::locked_filter_render(Olama_School_Helpers::translate('Academic Year'), $year_name, 'academic_year_id', $selected_year_id);
            ?>

            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label">
                    <?php echo Olama_School_Helpers::translate('Grade'); ?>
                </label>
                <select name="grade_id" class="olama-select" onchange="this.form.submit()">
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>>
                            <?php echo esc_html($g->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="button button-secondary">
                    <?php _e('Search', 'olama-school'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Main Content Form -->
    <div class="olama-card"
        style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <form method="post" action="">
            <?php wp_nonce_field('olama_save_stationary', 'olama_stationary_nonce_field'); ?>
            <input type="hidden" name="olama_save_stationary" value="1">
            <input type="hidden" name="academic_year_id" value="<?php echo $selected_year_id; ?>">
            <input type="hidden" name="grade_id" value="<?php echo $selected_grade_id; ?>">

            <div style="padding: 30px;">
                <div style="margin-bottom: 25px;">
                    <label class="olama-label"
                        style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span class="dashicons dashicons-book" style="color: #6366f1;"></span>
                        <strong>
                            <?php echo Olama_School_Helpers::translate('Required Notebooks'); ?>
                        </strong>
                    </label>
                    <textarea name="notebooks" rows="5" class="olama-textarea"
                        placeholder="<?php echo Olama_School_Helpers::translate('List required notebooks...'); ?>"
                        style="width: 100%; min-height: 120px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 12px;"><?php echo esc_textarea($stationary_data->notebooks ?? ''); ?></textarea>
                </div>

                <div style="margin-bottom: 25px;">
                    <label class="olama-label"
                        style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span class="dashicons dashicons-forms" style="color: #6366f1;"></span>
                        <strong>
                            <?php echo Olama_School_Helpers::translate('Required Stationary'); ?>
                        </strong>
                    </label>
                    <textarea name="stationary" rows="5" class="olama-textarea"
                        placeholder="<?php echo Olama_School_Helpers::translate('List other stationary items...'); ?>"
                        style="width: 100%; min-height: 120px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 12px;"><?php echo esc_textarea($stationary_data->stationary ?? ''); ?></textarea>
                </div>

                <div style="margin-bottom: 25px;">
                    <label class="olama-label"
                        style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span class="dashicons dashicons-admin-comments" style="color: #6366f1;"></span>
                        <strong>
                            <?php echo Olama_School_Helpers::translate('Class Teacher Notes'); ?>
                        </strong>
                    </label>
                    <textarea name="teacher_notes" rows="5" class="olama-textarea"
                        placeholder="<?php echo Olama_School_Helpers::translate('Additional notes from the class teacher...'); ?>"
                        style="width: 100%; min-height: 120px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 12px;"><?php echo esc_textarea($stationary_data->teacher_notes ?? ''); ?></textarea>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 20px 30px; border-top: 1px solid #e2e8f0; text-align: right;">
                <button type="submit" class="button button-primary button-large"
                    style="height: 46px; padding: 0 35px; border-radius: 8px; font-weight: 700; box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);">
                    <span class="dashicons dashicons-saved" style="margin-top: 10px; margin-right: 5px;"></span>
                    <?php echo Olama_School_Helpers::translate('Save Stationary'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .olama-stationary-wrap {
        max-width: 900px;
        margin-top: 20px;
    }

    .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1e293b;
        font-size: 1.1em;
    }

    .olama-select {
        height: 42px !important;
        border-radius: 6px !important;
        border-color: #cbd5e1 !important;
        width: 100%;
    }

    .olama-textarea:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 1px #6366f1 !important;
        outline: none;
    }

    <?php if (Olama_School_Helpers::is_arabic()): ?>
        .olama-stationary-wrap {
            direction: rtl;
        }

        .olama-stationary-wrap div[style*="text-align: right"] {
            text-align: left !important;
        }

        .dashicons-saved {
            margin-right: 0 !important;
            margin-left: 5px !important;
        }

    <?php endif; ?>
</style>