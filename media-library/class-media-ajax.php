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
     * Handle video chunk upload directly to Google Drive
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
        $total_size = intval($_POST['total_size'] ?? 0);
        $start_byte = intval($_POST['start_byte'] ?? 0);

        if (empty($file_uuid) || $total_chunks <= 0 || empty($_FILES['video_chunk']) || $total_size <= 0) {
            error_log(sprintf('Upload chunk error: uuid=%s, chunks=%d, file_empty=%d, size=%d', $file_uuid, $total_chunks, empty($_FILES['video_chunk']), $total_size));
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

        try {
            $drive = new Academy_Media_Drive();
            $db    = new Academy_Media_DB();

            if ($chunk_index === 0) {
                // Initialize Google Drive Upload Session
                $settings = get_option('academy_media_library_settings', []);
                $max_size_mb = intval($settings['max_file_size'] ?? 2048);
                $max_size_bytes = $max_size_mb * 1024 * 1024;

                if ($total_size > $max_size_bytes) {
                    throw new Exception(sprintf(__('File is too large. Max size allowed: %sMB', 'olama-school'), $max_size_mb));
                }

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

                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                if (empty($extension)) $extension = 'mp4';
                
                if ($part_number > 0) {
                    $new_filename = sprintf(__('%s %s %s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, __('Part', 'olama-school'), $part_number, $lesson_name, $extension);
                } else {
                    $new_filename = sprintf(__('%s %s %s.%s', 'olama-school'), __('Lesson', 'olama-school'), $lesson_number, $lesson_name, $extension);
                }

                $path = [$academic_year, $semester, $grade, $subject, $unit_name];
                $folder_id = $drive->get_or_create_nested_folder($path);
                
                if (!$folder_id) {
                    throw new Exception(__('Failed to create folder structure on Google Drive', 'olama-school'));
                }

                $mimes = ['mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'wmv' => 'video/x-ms-wmv', 'webm' => 'video/webm'];
                $mime_type = $mimes[strtolower($extension)] ?? 'video/mp4';

                $resumeUri = $drive->init_resumable_upload($new_filename, $mime_type, $folder_id, $total_size);

                file_put_contents($temp_dir . '/meta.json', json_encode([
                    'resumeUri' => $resumeUri,
                    'folder_id' => $folder_id,
                    'record_id' => $record_id,
                    'lesson_id' => $lesson_id,
                    'unit_id' => $unit_id,
                    'part_number' => $part_number,
                    'lesson_name' => $lesson_name,
                    'unit_name' => $unit_name,
                    'grade' => $grade,
                    'subject' => $subject,
                    'semester' => $semester,
                    'academic_year' => $academic_year
                ]));
            }

            // Read meta.json
            $meta_path = $temp_dir . '/meta.json';
            if (!file_exists($meta_path)) {
                throw new Exception(__('Upload metadata not found. Please restart the upload.', 'olama-school'));
            }
            $meta = json_decode(file_get_contents($meta_path), true);
            $resumeUri = $meta['resumeUri'];

            // Upload chunk to Drive
            $chunk_data = file_get_contents($chunk_file['tmp_name']);
            $result = $drive->put_upload_chunk($resumeUri, $chunk_data, $start_byte, $total_size);
            
            // Delete the local chunk file immediately since it's now on Drive
            unlink($chunk_file['tmp_name']);

            if ($result['status'] === 'completed') {
                // Set permissions
                $drive->set_file_permissions($result['file_id']);

                // Save to DB
                $db_data = [
                    'id' => $meta['record_id'] ?: 0,
                    'lesson_id' => $meta['lesson_id'],
                    'unit_id' => $meta['unit_id'],
                    'grade' => $meta['grade'],
                    'subject' => $meta['subject'],
                    'semester' => $meta['semester'],
                    'academic_year' => $meta['academic_year'],
                    'lesson_name' => $meta['lesson_name'],
                    'unit_name' => $meta['unit_name'],
                    'part_number' => $meta['part_number'] ?: null,
                    'drive_file_id' => $result['file_id'],
                    'drive_file_url' => $result['web_view_link'],
                    'drive_folder_id' => $meta['folder_id'],
                    'upload_status' => 'completed',
                    'approval_status' => 'pending',
                    'uploader_id' => get_current_user_id(),
                    'uploaded_at' => current_time('mysql')
                ];
                $db->upsert_upload_record($db_data);

                // Clean up
                $this->recursive_rmdir($temp_dir);

                wp_send_json_success([
                    'completed' => true,
                    'message' => __('Uploaded successfully', 'olama-school'),
                    'url' => $result['web_view_link']
                ]);
            } else {
                wp_send_json_success([
                    'completed' => false,
                    'message' => sprintf(__('Chunk %d uploaded successfully', 'olama-school'), $chunk_index)
                ]);
            }

        } catch (Exception $e) {
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
