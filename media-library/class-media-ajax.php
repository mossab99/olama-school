<?php
/**
 * Media Library AJAX Handlers Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Academy_Media_AJAX
{

    public function __construct()
    {
        // Curriculum & Uploads
        add_action('wp_ajax_academy_load_media_curriculum', [$this, 'load_curriculum']);
        add_action('wp_ajax_academy_upload_media_video', [$this, 'upload_video']);
        add_action('wp_ajax_academy_upload_media_video_chunk', [$this, 'upload_video_chunk']);
        add_action('wp_ajax_academy_finalize_drive_upload', [$this, 'finalize_drive_upload']);
        add_action('wp_ajax_academy_sync_lessons_status', [$this, 'sync_lessons_status']);

        // Settings
        add_action('wp_ajax_academy_save_drive_settings', [$this, 'save_settings']);
        add_action('wp_ajax_academy_test_drive_connection', [$this, 'test_connection']);

        // Log
        add_action('wp_ajax_academy_get_upload_log', [$this, 'get_log']);
        add_action('wp_ajax_academy_delete_log_entry', [$this, 'delete_log']);

        // Approval & Comments
        add_action('wp_ajax_academy_update_media_status', [$this, 'update_media_status']);
    }

    /**
     * Load curriculum lessons with status
     */
    public function load_curriculum()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_access_media_library')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $year_id = intval($_GET['academic_year_id'] ?? 0);
        $semester_id = intval($_GET['semester_id'] ?? 0);
        $grade_id = intval($_GET['grade_id'] ?? 0);
        $subject_id = intval($_GET['subject_id'] ?? 0);

        if (!$grade_id || !$subject_id || !$semester_id) {
            wp_send_json_error(__('Missing information', 'olama-school'));
        }

        $db = new Academy_Media_DB();
        $drive = new Academy_Media_Drive();
        $data = $db->get_curriculum($year_id, $semester_id, $grade_id, $subject_id);

        // --- Existence Check ---
        foreach ($data as &$unit) {
            if (empty($unit->lessons))
                continue;
            foreach ($unit->lessons as &$lesson) {
                if ($lesson->upload_status === 'completed' && !empty($lesson->drive_file_url)) {
                    $file_id = $lesson->drive_file_id;

                    // Fallback: Extract ID from URL if missing (for legacy records)
                    if (empty($file_id)) {
                        $file_id = $drive->extract_id_from_url($lesson->drive_file_url);
                        if ($file_id) {
                            // Proactively update DB with the extracted ID for next time
                            $db->update_status($lesson->media_record_id, 'completed', [
                                'drive_file_id' => $file_id
                            ]);
                        }
                    }

                    if (!empty($file_id) && !$drive->file_exists($file_id)) {
                        $lesson->upload_status = 'none';
                        $lesson->drive_file_url = null;
                        $lesson->drive_file_id = null;
                        $lesson->approval_status = 'pending'; // Reset approval if file is gone

                        // Update DB to reflect deletion
                        $db->update_status($lesson->media_record_id, 'none', [
                            'drive_file_id' => null,
                            'drive_file_url' => null,
                            'approval_status' => 'pending'
                        ]);
                    }
                }
            }
        }

        wp_send_json_success($data);
    }

    /**
     * Handle video upload to Drive
     */
    public function upload_video()
    {
        // Prevent PHP timeout and disconnection during direct uploads
        set_time_limit(0);
        ignore_user_abort(true);

        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_media_upload_video')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $unit_id = intval($_POST['unit_id'] ?? 0);
        $record_id = intval($_POST['id'] ?? 0);
        $part_number = intval($_POST['part_number'] ?? 0);
        $lesson_name = sanitize_text_field($_POST['lesson_name'] ?? '');
        $lesson_number = sanitize_text_field($_POST['lesson_number'] ?? '');
        $unit_name = sanitize_text_field($_POST['unit_name'] ?? '');
        $grade = sanitize_text_field($_POST['grade_name'] ?? '');
        $subject = sanitize_text_field($_POST['subject_name'] ?? '');
        $semester = sanitize_text_field($_POST['semester_name'] ?? '');
        $academic_year = sanitize_text_field($_POST['academic_year_name'] ?? '');

        if (empty($_FILES['video_file'])) {
            wp_send_json_error(__('No file was uploaded', 'olama-school'));
        }

        $file = $_FILES['video_file'];

        try {
            $drive = new Academy_Media_Drive();
            $db = new Academy_Media_DB();

            // 1. File Type & Size Validation
            $settings = get_option('academy_media_library_settings', []);
            $max_size_mb = intval($settings['max_file_size'] ?? 2048);
            $max_size_bytes = $max_size_mb * 1024 * 1024;

            if ($file['size'] > $max_size_bytes) {
                throw new Exception(sprintf(__('File is too large. Max size allowed: %sMB', 'olama-school'), $max_size_mb));
            }

            $allowed_types = [
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
                'video/x-ms-wmv',
                'video/webm'
            ];
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception(__('Only video files are allowed.', 'olama-school'));
            }


            // 2. Rename File to: Lesson {number} (Part {part}) {name}
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'mp4'; // Default to mp4 if unknown
            }
            
            if ($part_number > 0) {
                // Format: Lesson 1 Part 1 Title
                $new_filename = sprintf(__('%s %s %s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, __('Part', 'olama-school'), $part_number, $lesson_name, $extension);
            } else {
                // Format: Lesson 1 Title
                $new_filename = sprintf(__('%s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, $lesson_name, $extension);
            }

            // 3. Get/Create Folder Structure
            $path = [$academic_year, $semester, $grade, $subject, $unit_name];
            $folder_id = $drive->get_or_create_nested_folder($path);

            if (!$folder_id) {
                throw new Exception(__('Failed to create folder structure on Google Drive', 'olama-school'));
            }

            // 4. Upload to Drive
            $result = $drive->upload_video($file['tmp_name'], $new_filename, $file['type'], $folder_id);

            // 5. Save to DB
            $db_data = [
                'id' => $record_id,
                'lesson_id' => $lesson_id,
                'unit_id' => $unit_id,
                'grade' => $grade,
                'subject' => $subject,
                'semester' => $semester,
                'academic_year' => $academic_year,
                'lesson_name' => $lesson_name,
                'unit_name' => $unit_name,
                'part_number' => $part_number ?: null,
                'drive_file_id' => $result['file_id'],
                'drive_file_url' => $result['web_view_link'],
                'drive_folder_id' => $folder_id,
                'upload_status' => 'completed',
                'approval_status' => 'pending', // Default
                'uploader_id' => get_current_user_id(),
                'uploaded_at' => current_time('mysql')
            ];

            $db->upsert_upload_record($db_data);

            wp_send_json_success([
                'message' => __('Uploaded successfully', 'olama-school'),
                'url' => $result['web_view_link']
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Update Media Status (Approve/Reject/Comment)
     */
    public function update_media_status()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_media_approve_video')) {
            wp_send_json_error(__('Unauthorized to perform this action', 'olama-school'));
        }

        $media_id = intval($_POST['media_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : null;

        if (!$media_id || !in_array($status, ['approved', 'rejected', 'pending'])) {
            wp_send_json_error(__('Invalid data', 'olama-school'));
        }

        $db = new Academy_Media_DB();
        $db->update_approval_status($media_id, $status, $comment);

        wp_send_json_success(__('Status updated successfully', 'olama-school'));
    }

    /**
     * Sync lessons status with Drive
     */
    public function sync_lessons_status()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_media_upload_video')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $year_id = intval($_POST['academic_year_id'] ?? 0);
        $semester_id = intval($_POST['semester_id'] ?? 0);
        $grade_id = intval($_POST['grade_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);

        $year_name = trim(sanitize_text_field($_POST['academic_year_name'] ?? ''));
        $semester_name = trim(sanitize_text_field($_POST['semester_name'] ?? ''));
        $grade_name = trim(sanitize_text_field($_POST['grade_name'] ?? ''));
        $subject_name = trim(sanitize_text_field($_POST['subject_name'] ?? ''));

        if (!$grade_id || !$subject_id || !$semester_id) {
            wp_send_json_error(__('Missing information', 'olama-school'));
        }

        try {
            $db = new Academy_Media_DB();
            $drive = new Academy_Media_Drive();
            $data = $db->get_curriculum($year_id, $semester_id, $grade_id, $subject_id);

            $match_count = 0;
            $total_lessons = 0;

            foreach ($data as $unit) {
                if (empty($unit->lessons))
                    continue;

                // 1. Get/Create Folder for this unit
                $path = [$year_name, $semester_name, $grade_name, $subject_name, $unit->unit_name];
                try {
                    $folder_id = $drive->get_or_create_nested_folder($path);
                    if (!$folder_id)
                        continue;

                    // 2. List files in this unit folder
                    $drive_files = $drive->get_files_in_folder($folder_id);

                    // 3. Match lessons with files
                    foreach ($unit->lessons as $lesson) {
                        $total_lessons++;
                        $found = false;
                        $match_data = [];

                        // Normalize lesson name for matching: درس {number} {title}
                        $lesson_number = trim($lesson->lesson_number ?? '');
                        $lesson_title = trim($lesson->lesson_title ?? '');
                        $lesson_match_name = sprintf('%s %s %s', __('Lesson', 'olama-school'), $lesson_number, $lesson_title);
                        $lesson_match_name = preg_replace('/\s+/', ' ', trim($lesson_match_name));

                        foreach ($drive_files as $file) {
                            // Strip extension and normalize drive filename
                            $drive_file_name = preg_replace('/\.[^.]+$/', '', $file->name);
                            $drive_file_name = preg_replace('/\s+/', ' ', trim($drive_file_name));

                            if (mb_strtolower($lesson_match_name) === mb_strtolower($drive_file_name)) {
                                $found = true;
                                $match_data = [
                                    'upload_status' => 'completed',
                                    'drive_file_id' => $file->id,
                                    'drive_file_url' => $file->webViewLink,
                                    'drive_folder_id' => $folder_id
                                ];
                                break;
                            }
                        }

                        if ($found) {
                            $db_data = array_merge([
                                'lesson_id' => $lesson->id,
                                'unit_id' => $unit->id,
                                'grade' => $grade_name,
                                'subject' => $subject_name,
                                'semester' => $semester_name,
                                'academic_year' => $year_name,
                                'lesson_name' => $lesson->lesson_title,
                                'unit_name' => $unit->unit_name,
                                'uploaded_at' => current_time('mysql')
                            ], $match_data);

                            $db->upsert_upload_record($db_data);
                            $match_count++;
                        } else {
                            // Only update to "no video" if it was already in the DB or we want to force it
                            // For simplicity, let's always update status if not found
                            $db_data = [
                                'lesson_id' => $lesson->id,
                                'unit_id' => $unit->id,
                                'grade' => $grade_name,
                                'subject' => $subject_name,
                                'semester' => $semester_name,
                                'academic_year' => $year_name,
                                'lesson_name' => $lesson->lesson_title,
                                'unit_name' => $unit->unit_name,
                                'upload_status' => 'none',
                                'drive_file_id' => null,
                                'drive_file_url' => null,
                                'drive_folder_id' => $folder_id,
                                'approval_status' => 'pending' // Reset approval
                            ];
                            $db->upsert_upload_record($db_data);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Sync Error for unit ' . $unit->unit_name . ': ' . $e->getMessage());
                    continue;
                }
            }

            wp_send_json_success(sprintf(__('Sync completed. Found %d matches out of %d lessons.', 'olama-school'), $match_count, $total_lessons));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Save Drive Settings
     */
    public function save_settings()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $current_settings = get_option('academy_media_library_settings', []);

        $client_id = sanitize_text_field($_POST['client_id'] ?? '');
        $client_secret = sanitize_text_field($_POST['client_secret'] ?? '');
        $refresh_token = $current_settings['refresh_token'] ?? '';
        $access_token = $current_settings['access_token'] ?? null;

        // If credentials changed, clear the refresh token and access token as they are tied to the old credentials
        if (($current_settings['client_id'] ?? '') !== $client_id || ($current_settings['client_secret'] ?? '') !== $client_secret) {
            $refresh_token = '';
            $access_token = null;
        }

        $settings = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'root_folder_id' => trim(sanitize_text_field($_POST['root_folder_id'] ?? ''), " \t\n\r\0\x0B."),
            'max_file_size' => intval($_POST['max_file_size'] ?? 100),
            'refresh_token' => $refresh_token,
            'access_token' => $access_token
        ];

        update_option('academy_media_library_settings', $settings);
        wp_send_json_success(__('Settings saved', 'olama-school'));
    }

    /**
     * Test connection
     */
    public function test_connection()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $drive = new Academy_Media_Drive();
        $result = $drive->test_connection();

        if ($result['success']) {
            wp_send_json_success(sprintf(__('Connection successful! Root folder: %s', 'olama-school'), $result['folder_name']));
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Get Log
     */
    public function get_log()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $db = new Academy_Media_DB();
        $page = intval($_GET['paged'] ?? 1);
        $filters = [
            'grade' => $_GET['grade'] ?? '',
            'subject' => $_GET['subject'] ?? '',
            'status' => $_GET['status'] ?? '',
            'academic_year' => $_GET['academic_year'] ?? ''
        ];

        $data = $db->get_log($filters, $page);
        wp_send_json_success($data);
    }

    /**
     * Delete log entry
     */
    public function delete_log()
    {
        check_ajax_referer('olama_admin_nonce', 'nonce');

        $id = intval($_POST['id'] ?? 0);
        $db = new Academy_Media_DB();
        $db->delete_log($id);

        wp_send_json_success();
    }

    /**
     * Handle video chunk upload
     */
    public function upload_video_chunk()
    {
        // Prevent PHP timeout and disconnection during large uploads
        set_time_limit(0);
        ignore_user_abort(true);

        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_media_upload_video')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $file_uuid = sanitize_file_name($_POST['file_uuid'] ?? '');
        $chunk_index = intval($_POST['chunk_index'] ?? 0);
        $total_chunks = intval($_POST['total_chunks'] ?? 0);
        $filename = sanitize_file_name($_POST['filename'] ?? '');

        if (empty($file_uuid) || $total_chunks <= 0 || empty($_FILES['video_chunk'])) {
            wp_send_json_error(__('Invalid chunk upload parameters', 'olama-school'));
        }

        $chunk_file = $_FILES['video_chunk'];

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/academy-media-temp/' . $file_uuid;

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Run garbage collection
        $this->cleanup_temp_uploads();

        // Save chunk
        $chunk_path = $temp_dir . '/chunk_' . $chunk_index;
        if (!move_uploaded_file($chunk_file['tmp_name'], $chunk_path)) {
            wp_send_json_error(__('Failed to save chunk', 'olama-school'));
        }

        // Check if all chunks are uploaded
        $chunks_uploaded = 0;
        for ($i = 0; $i < $total_chunks; $i++) {
            if (file_exists($temp_dir . '/chunk_' . $i)) {
                $chunks_uploaded++;
            }
        }

        if ($chunks_uploaded === $total_chunks) {
            // All chunks uploaded! Merge them.
            $merged_file_path = $temp_dir . '/merged_' . $filename;
            $out = fopen($merged_file_path, 'wb');
            if (!$out) {
                wp_send_json_error(__('Failed to open output file for merging', 'olama-school'));
            }

            // FIX: Use 1MB buffer instead of 4KB
            $bufferSize = 1024 * 1024;
            for ($i = 0; $i < $total_chunks; $i++) {
                $chunk_file_path = $temp_dir . '/chunk_' . $i;
                $in = fopen($chunk_file_path, 'rb');
                if ($in) {
                    while ($buff = fread($in, $bufferSize)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    unlink($chunk_file_path); // remove chunk file immediately
                }
            }
            fclose($out);

            // Return immediately — don't touch Google Drive here
            wp_send_json_success([
                'completed' => false,
                'ready' => true,
                'file_path' => $merged_file_path,
                'temp_dir' => $temp_dir,
                'filename' => $filename,
                'message' => __('All chunks received. Finalizing...', 'olama-school')
            ]);
        } else {
            wp_send_json_success([
                'completed' => false,
                'message' => sprintf(__('Chunk %d uploaded successfully', 'olama-school'), $chunk_index)
            ]);
        }
    }

    /**
     * Finalize upload to Google Drive after all chunks are merged
     */
    public function finalize_drive_upload()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        check_ajax_referer('olama_admin_nonce', 'nonce');

        if (!Olama_School_Permissions::can('olama_media_upload_video')) {
            wp_send_json_error(__('Unauthorized access', 'olama-school'));
        }

        $merged_file_path = sanitize_text_field($_POST['merged_file_path'] ?? '');
        $temp_dir         = sanitize_text_field($_POST['temp_dir'] ?? '');
        $filename         = sanitize_text_field($_POST['filename'] ?? '');

        // Validate the path is inside our temp directory
        $upload_dir = wp_upload_dir();
        $expected_base = wp_normalize_path(trailingslashit($upload_dir['basedir']) . 'academy-media-temp');
        $normalized_path = wp_normalize_path($merged_file_path);
        if (strpos($normalized_path, $expected_base) !== 0) {
            wp_send_json_error(__('Invalid file path', 'olama-school'));
        }

        if (!file_exists($merged_file_path)) {
            wp_send_json_error(__('Merged file not found', 'olama-school'));
        }

        try {
            $lesson_id      = intval($_POST['lesson_id'] ?? 0);
            $unit_id        = intval($_POST['unit_id'] ?? 0);
            $record_id      = intval($_POST['id'] ?? 0);
            $part_number    = intval($_POST['part_number'] ?? 0);
            $lesson_name    = sanitize_text_field($_POST['lesson_name'] ?? '');
            $lesson_number  = sanitize_text_field($_POST['lesson_number'] ?? '');
            $unit_name      = sanitize_text_field($_POST['unit_name'] ?? '');
            $grade          = sanitize_text_field($_POST['grade_name'] ?? '');
            $subject        = sanitize_text_field($_POST['subject_name'] ?? '');
            $semester       = sanitize_text_field($_POST['semester_name'] ?? '');
            $academic_year  = sanitize_text_field($_POST['academic_year_name'] ?? '');

            $drive = new Academy_Media_Drive();
            $db    = new Academy_Media_DB();

            // 1. File Type & Size Validation
            $settings = get_option('academy_media_library_settings', []);
            $max_size_mb = intval($settings['max_file_size'] ?? 2048);
            $max_size_bytes = $max_size_mb * 1024 * 1024;
            $merged_file_size = filesize($merged_file_path);

            if ($merged_file_size > $max_size_bytes) {
                throw new Exception(sprintf(__('File is too large. Max size allowed: %sMB', 'olama-school'), $max_size_mb));
            }

            // Detect mime type
            $mime_type = '';
            if (function_exists('mime_content_type')) {
                $mime_type = mime_content_type($merged_file_path);
            }

            // Fallback mime type
            if (empty($mime_type)) {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $mimes = [
                    'mp4' => 'video/mp4',
                    'mov' => 'video/quicktime',
                    'avi' => 'video/x-msvideo',
                    'mkv' => 'video/x-matroska',
                    'wmv' => 'video/x-ms-wmv',
                    'webm' => 'video/webm'
                ];
                $mime_type = $mimes[strtolower($extension)] ?? 'video/mp4';
            }

            $allowed_types = [
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
                'video/x-ms-wmv',
                'video/webm'
            ];
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception(__('Only video files are allowed.', 'olama-school'));
            }

            // 2. Rename File
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'mp4';
            }

            if ($part_number > 0) {
                $new_filename = sprintf(__('%s %s %s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, __('Part', 'olama-school'), $part_number, $lesson_name, $extension);
            } else {
                $new_filename = sprintf(__('%s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, $lesson_name, $extension);
            }

            // 3. Get/Create Folder Structure
            $path = [$academic_year, $semester, $grade, $subject, $unit_name];
            $folder_id = $drive->get_or_create_nested_folder($path);

            if (!$folder_id) {
                throw new Exception(__('Failed to create folder structure on Google Drive', 'olama-school'));
            }

            // 4. Upload to Drive
            $result = $drive->upload_video($merged_file_path, $new_filename, $mime_type, $folder_id);

            // 5. Save to DB
            $db_data = [
                'id' => $record_id,
                'lesson_id' => $lesson_id,
                'unit_id' => $unit_id,
                'grade' => $grade,
                'subject' => $subject,
                'semester' => $semester,
                'academic_year' => $academic_year,
                'lesson_name' => $lesson_name,
                'unit_name' => $unit_name,
                'part_number' => $part_number ?: null,
                'drive_file_id' => $result['file_id'],
                'drive_file_url' => $result['web_view_link'],
                'drive_folder_id' => $folder_id,
                'upload_status' => 'completed',
                'approval_status' => 'pending',
                'uploader_id' => get_current_user_id(),
                'uploaded_at' => current_time('mysql')
            ];

            $db->upsert_upload_record($db_data);

            // Clean up
            unlink($merged_file_path);
            $this->recursive_rmdir($temp_dir);

            wp_send_json_success([
                'completed' => true,
                'message' => __('Uploaded successfully', 'olama-school'),
                'url' => $result['web_view_link']
            ]);

        } catch (Exception $e) {
            if (file_exists($merged_file_path)) {
                unlink($merged_file_path);
            }
            $this->recursive_rmdir($temp_dir);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Cleanup old temporary upload directories (garbage collection)
     */
    private function cleanup_temp_uploads()
    {
        $upload_dir = wp_upload_dir();
        $temp_root = $upload_dir['basedir'] . '/academy-media-temp';

        if (!file_exists($temp_root)) {
            return;
        }

        $files = scandir($temp_root);
        $now = time();

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $temp_root . '/' . $file;
            if (is_dir($path)) {
                if ($now - filemtime($path) > 86400) {
                    $this->recursive_rmdir($path);
                }
            }
        }
    }

    /**
     * Recursively delete directory and its contents
     */
    private function recursive_rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== "." && $object !== "..") {
                    if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                        $this->recursive_rmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
