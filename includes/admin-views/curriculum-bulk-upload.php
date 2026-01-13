<?php
/**
 * Bulk Upload View for Curriculum Management
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();
?>

<div class="wrap olama-school-wrap">
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

    <!-- Instructions Card -->
    <div class="olama-card" style="margin-bottom: 20px; background: #f8fafc;">
        <h3 style="margin-top: 0; color: #1e293b;">
            <span class="dashicons dashicons-info" style="color: #3b82f6;"></span>
            <?php echo Olama_School_Helpers::translate('Bulk Upload Instructions'); ?>
        </h3>
        <div style="line-height: 1.8;">
            <p><strong>
                    <?php echo Olama_School_Helpers::translate('File Format:'); ?>
                </strong></p>
            <ul style="margin-left: 20px;">
                <li>
                    <?php echo Olama_School_Helpers::translate('For Excel files (.xlsx): Each sheet represents one subject'); ?>
                </li>
                <li>
                    <?php echo Olama_School_Helpers::translate('For CSV files (.csv): Each file represents one subject'); ?>
                </li>
            </ul>

            <p><strong>
                    <?php echo Olama_School_Helpers::translate('Required Columns:'); ?>
                </strong></p>
            <ul style="margin-left: 20px;">
                <li><code>Unit #</code> -
                    <?php echo Olama_School_Helpers::translate('Unit number'); ?>
                </li>
                <li><code>Unit Name</code> -
                    <?php echo Olama_School_Helpers::translate('Unit name'); ?>
                </li>
                <li><code>Objectives</code> -
                    <?php echo Olama_School_Helpers::translate('Learning objectives'); ?>
                </li>
                <li><code>Lesson #</code> -
                    <?php echo Olama_School_Helpers::translate('Lesson number'); ?>
                </li>
                <li><code>Lesson Title</code> -
                    <?php echo Olama_School_Helpers::translate('Lesson title'); ?>
                </li>
                <li><code>Video URL</code> -
                    <?php echo Olama_School_Helpers::translate('Video URL (optional)'); ?>
                </li>
                <li><code>Number of Periods</code> -
                    <?php echo Olama_School_Helpers::translate('Number of periods'); ?>
                </li>
            </ul>

            <p>
                <a href="<?php echo OLAMA_SCHOOL_URL . 'templates/curriculum-template.csv'; ?>"
                    class="button button-secondary" download>
                    <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                    <?php echo Olama_School_Helpers::translate('Download CSV Template'); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="olama-card" style="margin-bottom: 20px;">
        <h2 style="margin-top: 0; color: #1e293b;">
            <?php echo Olama_School_Helpers::translate('Upload Curriculum Data'); ?>
        </h2>

        <div style="margin-bottom: 30px;">
            <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label class="olama-label">
                        <?php echo Olama_School_Helpers::translate('Semester'); ?>
                    </label>
                    <select id="bulk-semester" class="olama-select">
                        <option value="">
                            <?php echo Olama_School_Helpers::translate('-- Select Semester --'); ?>
                        </option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo $sem->id; ?>">
                                <?php echo esc_html($sem->semester_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 200px;">
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

            <div style="margin-bottom: 20px;">
                <label class="olama-label">
                    <?php echo Olama_School_Helpers::translate('Select File'); ?>
                </label>
                <input type="file" id="bulk-upload-file" accept=".xlsx,.xls,.csv"
                    style="display: block; margin-top: 10px;" disabled>
                <p class="description" style="margin-top: 8px;">
                    <?php echo Olama_School_Helpers::translate('Supported formats: Excel (.xlsx, .xls) or CSV (.csv)'); ?>
                </p>
            </div>

            <div>
                <button type="button" id="bulk-upload-btn" class="button button-primary button-large" disabled>
                    <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                    <?php echo Olama_School_Helpers::translate('Upload and Process'); ?>
                </button>
                <span id="bulk-upload-spinner" class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
            </div>
        </div>
    </div>

    <!-- Results Container -->
    <div id="bulk-upload-results" class="olama-card" style="display: none;">
        <h2 style="margin-top: 0; color: #1e293b;">
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