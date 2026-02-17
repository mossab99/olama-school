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
        <?php _e('مكتبة الوسائط', 'olama-school'); ?>
    </h1>

    <h2 class="nav-tab-wrapper">
        <?php if (Olama_School_Permissions::can('olama_media_upload_video')): ?>
            <a href="#upload" class="nav-tab nav-tab-active" data-tab="upload">
                <?php _e('رفع فيديو', 'olama-school'); ?>
            </a>
        <?php endif; ?>

        <?php if (Olama_School_Permissions::can('olama_media_drive_settings')): ?>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <?php _e('إعدادات الدرايف', 'olama-school'); ?>
            </a>
        <?php endif; ?>

        <?php if (Olama_School_Permissions::can('olama_media_view_logs')): ?>
            <a href="#log" class="nav-tab" data-tab="log">
                <?php _e('سجل الرفع', 'olama-school'); ?>
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
                            <?php _e('السنة الأكاديمية', 'olama-school'); ?>
                        </label>
                        <input type="text" value="<?php echo esc_attr($active_year->year_name ?? ''); ?>" readonly
                            class="regular-text">
                        <input type="hidden" id="filter-year-id" value="<?php echo esc_attr($active_year->id ?? ''); ?>">
                        <input type="hidden" id="filter-year-name"
                            value="<?php echo esc_attr($active_year->year_name ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label>
                            <?php _e('الفصل الدراسي', 'olama-school'); ?>
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
                            <?php _e('الصف', 'olama-school'); ?>
                        </label>
                        <select id="filter-grade">
                            <option value="">
                                <?php _e('-- اختر الصف --', 'olama-school'); ?>
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
                            <?php _e('المادة', 'olama-school'); ?>
                        </label>
                        <select id="filter-subject" disabled>
                            <option value="">
                                <?php _e('-- اختر المادة --', 'olama-school'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="button" id="btn-load-curriculum" class="button button-primary">
                            <?php _e('تحميل المناهج', 'olama-school'); ?>
                        </button>
                        <button type="button" id="btn-sync-status" class="button">
                            <?php _e('تزامن حالة الدروس', 'olama-school'); ?>
                        </button>
                    </div>
                </div>

                <div id="curriculum-container" class="curriculum-list">
                    <div class="notice notice-info">
                        <p>
                            <?php _e('اختر الفلاتر واضغط على تحميل المناهج لبدء رفع الفيديوهات.', 'olama-school'); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Settings Tab -->
        <?php if (Olama_School_Permissions::can('olama_media_drive_settings')): ?>
            <div id="tab-settings" class="tab-content">
                <div class="card">
                    <h2>
                        <?php _e('إعدادات جوجل درايف', 'olama-school'); ?>
                    </h2>
                    <form id="drive-settings-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="client_id"><?php _e('معرف العميل (Client ID)', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="client_id" name="client_id"
                                        value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label
                                        for="client_secret"><?php _e('سر العميل (Client Secret)', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="client_secret" name="client_secret"
                                        value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>"
                                        class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('رابط إعادة التوجيه (Redirect URI)', 'olama-school'); ?></label></th>
                                <td>
                                    <input type="text"
                                        value="<?php echo esc_attr(admin_url('admin.php?page=academy-media-library')); ?>"
                                        class="large-text" readonly onclick="this.select()">
                                    <p class="description">
                                        <?php _e('انسخ هذا الرابط وأضفه إلى "Authorized redirect URIs" في كونسول جوجل كلاود.', 'olama-school'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="root_folder_id"><?php _e('معرف المجلد الرئيسي', 'olama-school'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="root_folder_id" name="root_folder_id"
                                        value="<?php echo esc_attr($settings['root_folder_id'] ?? ''); ?>"
                                        class="regular-text">
                                    <p class="description">
                                        <?php _e('معرف المجلد على جوجل درايف حيث سيتم تخزين الفيديوهات.', 'olama-school'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="max_file_size"><?php _e('أقصى حجم للرفع (MB)', 'olama-school'); ?></label>
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
                                <?php _e('حفظ الإعدادات', 'olama-school'); ?>
                            </button>
                            <span id="settings-status"></span>
                        </p>
                    </form>

                    <hr>
                    <h2><?php _e('حالة المصادقة', 'olama-school'); ?></h2>
                    <?php
                    $refresh_token = $settings['refresh_token'] ?? '';
                    $client_id = $settings['client_id'] ?? '';
                    $client_secret = $settings['client_secret'] ?? '';

                    if ($settings['refresh_token'] ?? ''):
                        $drive = new Academy_Media_Drive();
                        $email = $drive->get_authenticated_email();
                        ?>
                        <div class="status-box status-success">
                            <span class="status-icon">✅</span>
                            <div class="status-text">
                                <strong><?php _e('متصل!', 'olama-school'); ?></strong>
                                <?php if ($email): ?>
                                    <br><span><?php printf(__('متصل كـ: %s', 'olama-school'), '<code>' . esc_html($email) . '</code>'); ?></span>
                                <?php else: ?>
                                    <br><span><?php _e('رمز التحديث محفوظ.', 'olama-school'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p>
                            <button type="button" id="btn-test-connection" class="button">
                                <?php _e('فحص الاتصال', 'olama-school'); ?>
                            </button>
                        </p>
                    <?php elseif ($client_id && $client_secret):
                        $drive = new Academy_Media_Drive();
                        $auth_url = $drive->get_auth_url();
                        ?>
                        <div class="notice notice-warning inline">
                            <p><?php _e('يجب عليك المصادقة مع حساب جوجل الخاص بك لتمكين الرفع.', 'olama-school'); ?>
                            </p>
                            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                                <?php _e('المصادقة مع جوجل', 'olama-school'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-info inline">
                            <p><?php _e('يرجى حفظ معرف العميل وسر العميل للمتابعة مع المصادقة.', 'olama-school'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Log Tab -->
        <?php if (Olama_School_Permissions::can('olama_media_view_logs')): ?>
            <div id="tab-log" class="tab-content">
                <div class="log-filter card">
                    <select id="log-filter-status">
                        <option value="">
                            <?php _e('كل الحالات', 'olama-school'); ?>
                        </option>
                        <option value="completed">
                            <?php _e('مكتمل', 'olama-school'); ?>
                        </option>
                        <option value="pending">
                            <?php _e('قيد الانتظار', 'olama-school'); ?>
                        </option>
                        <option value="failed">
                            <?php _e('فشل', 'olama-school'); ?>
                        </option>
                    </select>
                    <button type="button" id="btn-refresh-log" class="button">
                        <?php _e('تحديث', 'olama-school'); ?>
                    </button>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('التاريخ', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('الدرس', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('الصف/المادة', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('الحالة', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('رابط الدرايف', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('إجراءات', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="log-table-body">
                        <tr>
                            <td colspan="6" align="center">
                                <?php _e('جاري تحميل السجلات...', 'olama-school'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div id="log-pagination" class="tablenav-pages"></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden common file input -->
<input type="file" id="media-video-input" style="display:none;" accept="video/*">