<?php
/**
 * Media Library Page View
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('academy_media_library_settings', []);
$active_year = Olama_School_Academic::get_active_year();
$active_semester = Olama_School_Academic::get_active_semester();
$grades = Olama_School_Grade::get_grades();
?>

<div class="wrap academy-media-library-wrap">
    <h1>
        <?php _e('Media Library', 'olama-school'); ?>
    </h1>

    <h2 class="nav-tab-wrapper">
        <?php if (Olama_School_Permissions::can('olama_media_upload_video')): ?>
            <a href="#upload" class="nav-tab nav-tab-active" data-tab="upload">
                <?php _e('Upload Video', 'olama-school'); ?>
            </a>
        <?php endif; ?>

        <?php if (Olama_School_Permissions::can('olama_media_view_logs')): ?>
            <a href="#log" class="nav-tab" data-tab="log">
                <?php _e('Upload Log', 'olama-school'); ?>
            </a>
        <?php endif; ?>

        <?php if (Olama_School_Permissions::can('olama_media_drive_settings')): ?>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <?php _e('Drive Settings', 'olama-school'); ?>
            </a>
        <?php endif; ?>
    </h2>

    <div class="tab-content-container">
        <!-- Upload Tab -->
        <?php if (Olama_School_Permissions::can('olama_media_upload_video')): ?>
            <div id="tab-upload" class="tab-content active">
                <div class="media-filter-bar card">
                    <div class="filter-group">
                        <label>
                            <?php _e('Academic Year', 'olama-school'); ?>
                        </label>
                        <input type="text" value="<?php echo esc_attr($active_year->year_name ?? ''); ?>" readonly
                            class="regular-text">
                        <input type="hidden" id="filter-year-id" value="<?php echo esc_attr($active_year->id ?? ''); ?>">
                        <input type="hidden" id="filter-year-name"
                            value="<?php echo esc_attr($active_year->year_name ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>
                            <?php _e('Semester', 'olama-school'); ?>
                        </label>
                        <input type="text" value="<?php echo esc_attr($active_semester->semester_name ?? ''); ?>" readonly
                            class="regular-text">
                        <input type="hidden" id="filter-semester"
                            value="<?php echo esc_attr($active_semester->id ?? ''); ?>">
                        <input type="hidden" id="filter-semester-name"
                            value="<?php echo esc_attr($active_semester->semester_name ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>
                            <?php _e('Grade', 'olama-school'); ?>
                        </label>
                        <select id="filter-grade">
                            <option value="">
                                <?php _e('-- Select Grade --', 'olama-school'); ?>
                            </option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?php echo $grade->id; ?>"
                                    data-name="<?php echo esc_attr($grade->grade_name); ?>">
                                    <?php echo $grade->grade_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>
                            <?php _e('Subject', 'olama-school'); ?>
                        </label>
                        <select id="filter-subject" disabled>
                            <option value="">
                                <?php _e('-- Select Subject --', 'olama-school'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="button" id="btn-load-curriculum" class="button button-primary">
                            <?php _e('Load Curriculum', 'olama-school'); ?>
                        </button>
                        <button type="button" id="btn-sync-status" class="button">
                            <?php _e('Sync Lesson Status', 'olama-school'); ?>
                        </button>
                    </div>
                </div>

                <div id="curriculum-container" class="curriculum-list">
                    <div class="notice notice-info">
                        <p>
                            <?php _e('Select filters and click Load Curriculum to start uploading videos.', 'olama-school'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Log Tab -->
        <?php if (Olama_School_Permissions::can('olama_media_view_logs')): ?>
            <div id="tab-log" class="tab-content">
                <div class="log-filter card">
                    <select id="log-filter-status">
                        <option value="">
                            <?php _e('All Statuses', 'olama-school'); ?>
                        </option>
                        <option value="completed">
                            <?php _e('Completed', 'olama-school'); ?>
                        </option>
                        <option value="pending">
                            <?php _e('Pending', 'olama-school'); ?>
                        </option>
                        <option value="failed">
                            <?php _e('Failed', 'olama-school'); ?>
                        </option>
                    </select>
                    <button type="button" id="btn-refresh-log" class="button">
                        <?php _e('Refresh', 'olama-school'); ?>
                    </button>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Date', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Lesson', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Grade/Subject', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Status', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Drive Link', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Actions', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="log-table-body">
                        <tr>
                            <td colspan="6" align="center">
                                <?php _e('Loading logs...', 'olama-school'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div id="log-pagination" class="tablenav-pages"></div>
            </div>
        <?php endif; ?>

        <!-- Settings Tab -->
        <?php if (Olama_School_Permissions::can('olama_media_drive_settings')): ?>
            <div id="tab-settings" class="tab-content">
                <div class="card">
                    <h2>
                        <?php _e('Google Drive Settings', 'olama-school'); ?>
                    </h2>
                    <form id="drive-settings-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="client_id"><?php _e('Client ID', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="client_id" name="client_id"
                                        value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label
                                        for="client_secret"><?php _e('Client Secret', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="client_secret" name="client_secret"
                                        value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>"
                                        class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Redirect URI', 'olama-school'); ?></label></th>
                                <td>
                                    <input type="text"
                                        value="<?php echo esc_attr(admin_url('admin.php?page=academy-media-library')); ?>"
                                        class="large-text" readonly onclick="this.select()">
                                    <p class="description">
                                        <?php _e('Copy this URL and add it to "Authorized redirect URIs" in the Google Cloud Console.', 'olama-school'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="root_folder_id"><?php _e('Root Folder ID', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="root_folder_id" name="root_folder_id"
                                        value="<?php echo esc_attr($settings['root_folder_id'] ?? ''); ?>"
                                        class="regular-text">
                                    <p class="description">
                                        <?php _e('The folder ID on Google Drive where videos will be stored.', 'olama-school'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="max_file_size"><?php _e('Max Upload Size (MB)', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="max_file_size" name="max_file_size"
                                        value="<?php echo esc_attr($settings['max_file_size'] ?? 100); ?>"
                                        class="small-text">
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e('Save Settings', 'olama-school'); ?>
                            </button>
                            <span id="settings-status"></span>
                        </p>
                    </form>

                    <hr>
                    <h2><?php _e('Authentication Status', 'olama-school'); ?></h2>
                    <?php
                    $refresh_token = $settings['refresh_token'] ?? '';
                    $client_id = $settings['client_id'] ?? '';
                    $client_secret = $settings['client_secret'] ?? '';

                    if ($client_id && $client_secret):
                        $drive = new Academy_Media_Drive();
                        
                        if ($refresh_token):
                            $email = $drive->get_authenticated_email();
                            ?>
                            <div class="status-box status-success">
                                <span class="status-icon">✅</span>
                                <div class="status-text">
                                    <strong><?php _e('Connected!', 'olama-school'); ?></strong>
                                    <?php if ($email): ?>
                                        <br><span><?php printf(__('Connected as: %s', 'olama-school'), '<code>' . esc_html($email) . '</code>'); ?></span>
                                    <?php else: ?>
                                        <br><span><?php _e('Refresh token saved.', 'olama-school'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p>
                                <button type="button" id="btn-test-connection" class="button">
                                    <?php _e('Test Connection', 'olama-school'); ?>
                                </button>
                            </p>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('You must authenticate with your Google account to enable uploading.', 'olama-school'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <p>
                            <a href="<?php echo esc_url($drive->get_auth_url()); ?>" class="button button-primary">
                                <?php echo $refresh_token ? __('Re-authenticate with Google', 'olama-school') : __('Authenticate with Google', 'olama-school'); ?>
                            </a>
                        </p>

                    <?php else: ?>
                        <div class="notice notice-info inline">
                            <p><?php _e('Please save the Client ID and Client Secret to proceed with authentication.', 'olama-school'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Preview Modal -->
<div id="video-preview-modal" class="academy-modal-overlay" style="display: none;">
    <div class="academy-modal">
        <div class="academy-modal-header">
            <h3 id="modal-video-title"></h3>
            <button type="button" class="academy-modal-close">&times;</button>
        </div>
        <div class="academy-modal-body">
            <div class="iframe-container">
                <iframe id="video-preview-iframe" src="" width="100%" height="480" frameborder="0"
                    allow="autoplay; encrypted-media" allowfullscreen></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Comment Modal -->
<div id="comment-modal" class="academy-modal-overlay" style="display: none;">
    <div class="academy-modal">
        <div class="academy-modal-header">
            <h3><?php _e('Notes', 'olama-school'); ?></h3>
            <button type="button" class="academy-modal-close">&times;</button>
        </div>
        <div class="academy-modal-body">
            <textarea id="comment-modal-text" rows="5" style="width:100%;"
                placeholder="<?php _e('Write your notes here...', 'olama-school'); ?>"></textarea>
            <input type="hidden" id="comment-media-id">
        </div>
        <div class="academy-modal-footer">
            <button type="button" id="btn-save-comment"
                class="button button-primary"><?php _e('Save', 'olama-school'); ?></button>
            <button type="button" class="academy-modal-close button"><?php _e('Close', 'olama-school'); ?></button>
        </div>
    </div>
</div>

<!-- Hidden common file input -->
<input type="file" id="media-video-input" style="display:none;" accept="video/*" multiple>