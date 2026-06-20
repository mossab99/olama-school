<?php
/**
 * Media Library Google Drive API Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Academy_Media_Drive
{

    private $service;
    private $root_folder_id;

    public function __construct()
    {
        $settings = get_option('academy_media_library_settings', []);
        $this->root_folder_id = trim($settings['root_folder_id'] ?? '', " \t\n\r\0\x0B.");

        $client = $this->get_client();
        if ($client) {
            $access_token = $settings['access_token'] ?? null;
            if ($access_token) {
                $client->setAccessToken($access_token);
            }

            if ($client->isAccessTokenExpired()) {
                $refresh_token = $settings['refresh_token'] ?? '';
                if ($refresh_token) {
                    try {
                        $new_token = $client->fetchAccessTokenWithRefreshToken($refresh_token);
                        if ($new_token && !isset($new_token['error'])) {
                            // Merge and save new token details into settings
                            $settings = get_option('academy_media_library_settings', []);
                            $settings['access_token'] = $new_token;
                            // Ensure the refresh token is kept if Google didn't return a new one in this response
                            if (!isset($settings['access_token']['refresh_token']) && $refresh_token) {
                                $settings['access_token']['refresh_token'] = $refresh_token;
                            }
                            update_option('academy_media_library_settings', $settings);
                        }
                    } catch (Exception $e) {
                        error_log('Google Drive Token Refresh Error: ' . $e->getMessage());
                    }
                }
            }
            $this->service = new Google_Service_Drive($client);
        }
    }

    /**
     * Get configured Google Client
     */
    public function get_client()
    {
        $settings = get_option('academy_media_library_settings', []);
        $client_id = $settings['client_id'] ?? '';
        $client_secret = $settings['client_secret'] ?? '';

        if (empty($client_id) || empty($client_secret)) {
            return null;
        }

        if (!class_exists('Google_Client')) {
            return null;
        }

        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri(admin_url('admin.php?page=academy-media-library'));

        return $client;
    }

    /**
     * Authenticate with Auth Code
     */
    public function authenticate($code)
    {
        $client = $this->get_client();
        if (!$client) {
            return new WP_Error('missing_creds', __('Client ID or Secret missing.', 'olama-school'));
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                return new WP_Error('auth_error', $token['error_description'] ?? $token['error']);
            }

            $settings = get_option('academy_media_library_settings', []);
            $settings['access_token'] = $token;
            
            if (isset($token['refresh_token'])) {
                $settings['refresh_token'] = $token['refresh_token'];
                update_option('academy_media_library_settings', $settings);
                return true;
            } else {
                // If we already have a refresh token in settings, we can proceed even if Google didn't return a new one (sometimes happens if consent wasn't forced)
                if (!empty($settings['refresh_token'])) {
                    update_option('academy_media_library_settings', $settings);
                    return true;
                }
                return new WP_Error('no_refresh_token', __('No refresh token returned. Revoke access and try again.', 'olama-school'));
            }
        } catch (Exception $e) {
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Get Google Auth URL
     */
    public function get_auth_url()
    {
        $client = $this->get_client();
        if (!$client)
            return '#';

        try {
            return $client->createAuthUrl();
        } catch (Exception $e) {
            return '#';
        }
    }

    /**
     * Get the email of the authenticated Google account
     */
    public function get_authenticated_email()
    {
        if (!$this->service)
            return null;

        try {
            $client = $this->service->getClient();
            if (!$client->getAccessToken())
                return null;

            $oauth2 = new Google_Service_Oauth2($client);
            $userinfo = $oauth2->userinfo->get();
            return $userinfo->email;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Test connection to Google Drive
     */
    public function test_connection()
    {
        if (!$this->service) {
            return ['success' => false, 'error' => __('Invalid or missing credentials', 'olama-school')];
        }

        if (!$this->service->getClient()->getAccessToken()) {
            return ['success' => false, 'error' => __('Not authenticated. Please click "Authenticate with Google" in settings.', 'olama-school')];
        }

        if (empty($this->root_folder_id)) {
            return ['success' => false, 'error' => __('Root Folder ID is missing', 'olama-school')];
        }

        try {
            $folder = $this->service->files->get($this->root_folder_id, ['fields' => 'id, name']);
            return ['success' => true, 'folder_name' => $folder->name];
        } catch (Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $msg = $error['error']['message'] ?? $e->getMessage();
            return ['success' => false, 'error' => sprintf(__('Drive Error: %s', 'olama-school'), $msg)];
        }
    }

    /**
     * Get or create a nested folder structure
     * @param array $path_parts List of folder names in order
     * @return string Final folder ID
     */
    public function get_or_create_nested_folder($path_parts)
    {
        if (empty($this->root_folder_id)) {
            throw new Exception(__('Root Folder ID is missing in settings', 'olama-school'));
        }

        $parent_id = $this->root_folder_id;

        foreach ($path_parts as $folder_name) {
            $parent_id = $this->get_or_create_single_folder($folder_name, $parent_id);
        }

        return $parent_id;
    }

    /**
     * Get or create a single folder under a parent
     */
    private function get_or_create_single_folder($folder_name, $parent_id)
    {
        if (!$this->service)
            throw new Exception(__('Google Drive service not initialized', 'olama-school'));

        $query = "name = '" . str_replace("'", "\\'", $folder_name) . "' 
                  and '" . $parent_id . "' in parents 
                  and mimeType = 'application/vnd.google-apps.folder' 
                  and trashed = false";

        try {
            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name)',
                'pageSize' => 1,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ]);

            if (count($response->files) > 0) {
                return $response->files[0]->id;
            }

            // Create new folder
            $file_metadata = new Google_Service_Drive_DriveFile([
                'name' => $folder_name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parent_id]
            ]);
            $folder = $this->service->files->create($file_metadata, [
                'fields' => 'id',
                'supportsAllDrives' => true
            ]);

            return $folder->id;
        } catch (Exception $e) {
            $error = json_decode($e->getMessage(), true);
            $msg = $error['error']['message'] ?? $e->getMessage();
            throw new Exception(sprintf(__('Drive Error: %s', 'olama-school'), $msg));
        }
    }

    /**
     * Get files in a specific folder
     */
    public function get_files_in_folder($folder_id)
    {
        if (!$this->service || empty($folder_id)) {
            return [];
        }

        try {
            $folder_id_safe = str_replace("'", "\\'", $folder_id);
            $query = "'$folder_id_safe' in parents and trashed = false";
            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name, mimeType, webViewLink)',
                'pageSize' => 1000,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true
            ]);

            return $response->files;
        } catch (Exception $e) {
            error_log('Drive Error in get_files_in_folder: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a file exists on Drive
     */
    public function file_exists($file_id)
    {
        if (!$this->service || empty($file_id))
            return false;

        try {
            $file = $this->service->files->get($file_id, [
                'fields' => 'id, trashed',
                'supportsAllDrives' => true
            ]);
            return $file && !$file->trashed;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get or create a folder for a unit (Legacy)
     */
    public function get_or_create_unit_folder($unit_name)
    {
        return $this->get_or_create_nested_folder([$unit_name]);
    }

    /**
     * Helper to extract File ID from Google Drive URL
     */
    public function extract_id_from_url($url)
    {
        if (empty($url))
            return null;

        // Pattern for d/FILE_ID/view or id=FILE_ID
        if (preg_match('/[-\w]{25,}/', $url, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Upload video with support for resumable uploads
     */
    public function upload_video($tmp_path, $filename, $mime_type, $folder_id)
    {
        if (!$this->service) {
            throw new Exception(__('Google Drive service not initialized', 'olama-school'));
        }

        // Prevent PHP timeout during large uploads to Google Drive
        set_time_limit(0);
        ignore_user_abort(true);

        $file_metadata = new Google_Service_Drive_DriveFile([
            'name' => $filename,
            'parents' => [$folder_id]
        ]);

        $file_size = filesize($tmp_path);
        $chunk_size = 5 * 1024 * 1024; // 5MB chunks for Drive upload

        // Resumable upload for files > 5MB
        if ($file_size > 5 * 1024 * 1024) {
            $client = $this->service->getClient();
            $client->setDefer(true);
            $request = $this->service->files->create($file_metadata, [
                'fields' => 'id, webViewLink',
                'supportsAllDrives' => true
            ]);

            $media = new Google_Http_MediaFileUpload(
                $client,
                $request,
                $mime_type,
                null,
                true,
                $chunk_size
            );
            $media->setFileSize($file_size);

            $status = false;
            $handle = fopen($tmp_path, "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunk_size);
                $status = $media->nextChunk($chunk);
            }
            fclose($handle);
            $client->setDefer(false);
            $file = $status;
        } else {
            // Simple upload for small files
            $file = $this->service->files->create($file_metadata, [
                'data' => file_get_contents($tmp_path),
                'mimeType' => $mime_type,
                'uploadType' => 'multipart',
                'fields' => 'id, webViewLink',
                'supportsAllDrives' => true
            ]);
        }

        if ($file && isset($file->id)) {
            // Set permissions to anyone with the link can view
            $permission = new Google_Service_Drive_Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $this->service->permissions->create($file->id, $permission);

            // Re-fetch to get webViewLink if not already there
            if (!isset($file->webViewLink)) {
                $file = $this->service->files->get($file->id, ['fields' => 'id, webViewLink']);
            }

            return [
                'file_id' => $file->id,
                'web_view_link' => $file->webViewLink
            ];
        }

        throw new Exception(__('Upload failed', 'olama-school'));
    }
}
