<?php
/**
 * Bulk Upload View for Curriculum Management
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$selected_year_id = $active_year ? intval($active_year->id) : 0;
$semesters = $selected_year_id ? Olama_School_Academic::get_semesters($selected_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($selected_year_id);
$default_semester_id = $active_semester ? intval($active_semester->id) : 0;
?>

<div class="olama-bulk-upload-wrapper">
    <?php
    // Display messages
    if ($import_message = get_transient('olama_bulk_import_message')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($import_message) . '</p></div>';
        delete_transient('olama_bulk_import_message');
    }
    if ($import_error = get_transient('olama_bulk_import_error')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($import_error) . '</p></div>';
        delete_transient('olama_bulk_import_error');
    }
    ?>

    <!-- 1. Upload Section -->
    <div class="olama-card" style="margin-bottom: 25px;">
        <h2
            style="margin-top: 0; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
            <span class="dashicons dashicons-upload" style="color: #6366f1;"></span>
            <?php echo Olama_School_Helpers::translate('Upload Curriculum Data'); ?>
        </h2>

        <div style="margin-bottom: 20px;">
            <!-- Filters Lined Up -->
            <div
                style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 25px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">

                <?php
                $active_year_name = '—';
                $years = Olama_School_Academic::get_years();
                foreach ($years as $year) {
                    if ($year->id == $selected_year_id) {
                        $active_year_name = $year->year_name;
                        break;
                    }
                }
                $active_semester_name = '—';
                foreach ($semesters as $sem) {
                    if ($sem->id == $default_semester_id) {
                        $active_semester_name = $sem->semester_name;
                        break;
                    }
                }

                echo Olama_School_Helpers::locked_filter_render(Olama_School_Helpers::translate('Academic Year'), $active_year_name, 'academic_year_id', $selected_year_id);
                echo Olama_School_Helpers::locked_filter_render(Olama_School_Helpers::translate('Semester'), $active_semester_name, 'semester_id', $default_semester_id);
                ?>

                <div style="flex: 1; min-width: 180px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Grade'); ?>
                    </label>
                    <select id="bulk-grade" class="olama-select">
                        <option value="">
                            <?php echo Olama_School_Helpers::translate('-- Select Grade --'); ?>
                        </option>
                        <?php foreach ($grades as $grade): ?>
                            <option value="<?php echo $grade->id; ?>">
                                <?php echo esc_html($grade->grade_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- File Selection and Upload Row -->
            <div
                style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap; padding: 25px; background: #fff; border: 2px dashed #e2e8f0; border-radius: 12px; transition: all 0.3s ease;">
                <div style="flex: 2; min-width: 300px;">
                    <label class="olama-label"
                        style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px; color: #1e293b; font-size: 1.1em;">
                        <span class="dashicons dashicons-media-spreadsheet"
                            style="font-size: 20px; width: 20px; height: 20px; color: #6366f1;"></span>
                        <?php echo Olama_School_Helpers::translate('Select File'); ?>
                    </label>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <input type="file" id="bulk-upload-file" accept=".xlsx,.xls,.csv"
                            style="display: block; flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; cursor: pointer;"
                            disabled>

                        <button type="button" id="bulk-upload-btn" class="button button-primary button-large" disabled
                            style="height: 46px; padding: 0 30px; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2); transition: transform 0.2s;">
                            <span class="dashicons dashicons-cloud-upload"
                                style="margin-top: 10px; margin-right: 5px;"></span>
                            <?php echo Olama_School_Helpers::translate('Upload and Process'); ?>
                        </button>
                    </div>
                    <p class="description"
                        style="margin-top: 12px; font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-info"
                            style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php echo Olama_School_Helpers::translate('Supported formats: Excel (.xlsx, .xls) or CSV (.csv)'); ?>
                    </p>
                </div>
                <div id="bulk-upload-spinner" class="spinner" style="float: none; margin: 0; scale: 1.5;"></div>
            </div>
        </div>
    </div>

    <!-- 2. Instructions Section -->
    <div class="olama-card" style="margin-bottom: 25px; background: #fff; border: 1px solid #e2e8f0;">
        <h3
            style="margin-top: 0; color: #1e293b; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
            <span class="dashicons dashicons-info" style="color: #6366f1;"></span>
            <?php echo Olama_School_Helpers::translate('Bulk Upload Instructions'); ?>
        </h3>
        <div
            style="line-height: 1.8; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; padding: 0 10px;">
            <div>
                <p
                    style="font-weight: 700; color: #475569; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-media-document"
                        style="font-size: 18px; width: 18px; height: 18px;"></span>
                    <?php echo Olama_School_Helpers::translate('File Format:'); ?>
                </p>
                <ul style="margin: 0; padding-left: 20px; color: #64748b;">
                    <li style="margin-bottom: 8px;">
                        <?php echo Olama_School_Helpers::translate('For Excel files (.xlsx): Each sheet represents one subject'); ?>
                    </li>
                    <li style="margin-bottom: 0;">
                        <?php echo Olama_School_Helpers::translate('For CSV files (.csv): Each file represents one subject'); ?>
                    </li>
                </ul>
            </div>

            <div>
                <p
                    style="font-weight: 700; color: #475569; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-editor-ul"
                        style="font-size: 18px; width: 18px; height: 18px;"></span>
                    <?php echo Olama_School_Helpers::translate('Required Columns:'); ?>
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Unit #'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Unit Name'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Learning Objectives'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Lesson #'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Lesson Title'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Video URL'); ?></code>
                    <code
                        style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;"><?php echo Olama_School_Helpers::translate('Number of Periods'); ?></code>
                </div>
                <p style="margin-top: 15px;">
                    <a href="<?php echo OLAMA_SCHOOL_URL . 'templates/curriculum-template.csv'; ?>"
                        class="button button-secondary" download
                        style="border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-download"></span>
                        <?php echo Olama_School_Helpers::translate('Download CSV Template'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- 3. Results Container -->
    <div id="bulk-upload-results" class="olama-card" style="display: none; border-top: 4px solid #10b981;">
        <h2 style="margin-top: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
            <span class="dashicons dashicons-yes-alt"
                style="color: #10b981; font-size: 28px; width: 28px; height: 28px;"></span>
            <?php echo Olama_School_Helpers::translate('Upload Results'); ?>
        </h2>
        <div id="bulk-upload-results-content"></div>
    </div>
</div>

<style>
    .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9em;
        color: #555;
    }

    .olama-select {
        width: 100%;
        height: 35px;
    }

    .bulk-result-summary {
        background: #f0f9ff;
        border-left: 4px solid #3b82f6;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .bulk-result-summary.success {
        background: #f0fdf4;
        border-left-color: #22c55e;
    }

    .bulk-result-summary.error {
        background: #fef2f2;
        border-left-color: #ef4444;
    }

    .bulk-result-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .bulk-result-table th {
        background: #f1f5f9;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
    }

    .bulk-result-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
    }

    .bulk-result-table tr:hover {
        background: #f8fafc;
    }

    .result-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .result-badge.success {
        background: #dcfce7;
        color: #166534;
    }

    .result-badge.error {
        background: #fee2e2;
        color: #991b1b;
    }

    .result-badge.warning {
        background: #fef3c7;
        color: #92400e;
    }

    .error-list {
        margin: 10px 0;
        padding-left: 20px;
        color: #991b1b;
    }

    .error-list li {
        margin-bottom: 5px;
    }
</style>