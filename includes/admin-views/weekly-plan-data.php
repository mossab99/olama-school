<?php
/**
 * Weekly Plan Data Management View
 */
if (!defined('ABSPATH'))
    exit;

if ($message = get_transient('olama_import_message')) {
    echo '<div class="updated"><p>' . esc_html($message) . '</p></div>';
    delete_transient('olama_import_message');
}
?>

<div class="olama-data-management-container">
    <div class="grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
        <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php _e('Export Data', 'olama-school'); ?></h2>
            <p><?php _e('Download all weekly plans and schedule data in CSV format.', 'olama-school'); ?></p>
            <form method="post">
                <?php wp_nonce_field('olama_export_action'); ?>
                <input type="hidden" name="olama_export" value="true">
                <?php submit_button(__('Download CSV Export', 'olama-school'), 'secondary'); ?>
            </form>
        </div>

        <div style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php _e('Import Data', 'olama-school'); ?></h2>
            <p><?php _e('Upload a CSV file to bulk import weekly plans or curriculum units.', 'olama-school'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('olama_import_action'); ?>
                <input type="file" name="olama_import_file" accept=".csv" required
                    style="margin-bottom: 15px; display: block;">
                <?php submit_button(__('Upload & Import', 'olama-school'), 'secondary'); ?>
            </form>
        </div>
    </div>
</div>