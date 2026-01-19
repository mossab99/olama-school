<?php
/**
 * Olama School Importer Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Importer
{
    /**
     * Import plans from CSV
     */
    public static function import_plans_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_import_action')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!current_user_can('olama_import_export_data')) {
            wp_die(__('You do not have permission to import data.', 'olama-school'));
        }

        if (empty($_FILES['olama_import_file']['tmp_name'])) {
            wp_die(__('Please upload a valid CSV file.', 'olama-school'));
        }

        $file = $_FILES['olama_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle !== false) {
            // Skip BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // Get headers and map them to indices
            $headers = fgetcsv($handle);
            if (!$headers) {
                wp_die(__('Invalid CSV header.', 'olama-school'));
            }

            $map = array();
            $fields = array(
                'Plan ID' => 'id',
                'Date' => 'plan_date',
                'Period' => 'period_number',
                'Grade' => 'grade_name',
                'Section' => 'section_name',
                'Subject' => 'subject_name',
                'Teacher' => 'teacher_name',
                'Custom Topic' => 'custom_topic',
                'Status' => 'status'
            );

            foreach ($headers as $index => $header) {
                $header = trim($header);
                if (isset($fields[$header])) {
                    $map[$fields[$header]] = $index;
                }
            }

            $imported_count = 0;
            while (($data = fgetcsv($handle)) !== false) {
                // Ensure we have enough data
                if (count($data) < 5)
                    continue;

                $plan_row = array();
                foreach ($map as $field => $index) {
                    $plan_row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                }

                $plan_date = !empty($plan_row['plan_date']) ? $plan_row['plan_date'] : current_time('mysql', false);
                $period_number = intval($plan_row['period_number'] ?? 1);
                $grade_id = self::get_id_by_name($wpdb->prefix . 'olama_grades', 'grade_name', $plan_row['grade_name'] ?? '');
                $section_id = self::get_id_by_name($wpdb->prefix . 'olama_sections', 'section_name', $plan_row['section_name'] ?? '');
                $subject_id = self::get_id_by_name($wpdb->prefix . 'olama_subjects', 'subject_name', $plan_row['subject_name'] ?? '');

                $teacher_id = 0;
                if (!empty($plan_row['teacher_name'])) {
                    $teacher_user = get_user_by('login', $plan_row['teacher_name']);
                    if (!$teacher_user) {
                        $teacher_user = get_user_by('slug', sanitize_title($plan_row['teacher_name']));
                    }
                    $teacher_id = $teacher_user ? $teacher_user->ID : 0;
                }

                $custom_topic = $plan_row['custom_topic'] ?? '';
                $status = !empty($plan_row['status']) ? $plan_row['status'] : 'draft';

                if ($section_id && $subject_id) {
                    $wpdb->insert($wpdb->prefix . 'olama_plans', array(
                        'section_id' => $section_id,
                        'subject_id' => $subject_id,
                        'teacher_id' => $teacher_id,
                        'plan_date' => $plan_date,
                        'period_number' => $period_number,
                        'custom_topic' => $custom_topic,
                        'status' => $status,
                        'created_at' => current_time('mysql'),
                    ));
                    $imported_count++;
                }
            }
            fclose($handle);

            // Log activity
            if (class_exists('Olama_School_Logger')) {
                Olama_School_Logger::log('data_import', sprintf('CSV import completed: %d plans imported', $imported_count));
            }

            set_transient('olama_import_message', sprintf(__('%d plans imported successfully.', 'olama-school'), $imported_count), 30);
        }

        wp_redirect(admin_url('admin.php?page=olama-data-management&import=success'));
        exit;
    }

    /**
     * Import students from CSV
     */
    public static function import_students_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_import_students')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!current_user_can('olama_manage_academic_structure')) {
            wp_die(__('You do not have permission to import students.', 'olama-school'));
        }

        if (empty($_FILES['olama_import_file']['tmp_name'])) {
            wp_die(__('Please upload a valid CSV file.', 'olama-school'));
        }

        $file = $_FILES['olama_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle !== false) {
            // Skip BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                wp_die(__('Invalid CSV header.', 'olama-school'));
            }

            $map = array();
            $fields = array(
                'Name' => 'student_name',
                'ID Number' => 'student_id_number',
                'Grade' => 'grade_name',
                'Section' => 'section_name',
            );

            foreach ($headers as $index => $header) {
                $header = trim($header);
                if (isset($fields[$header])) {
                    $map[$fields[$header]] = $index;
                }
            }

            $imported_count = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $row = array();
                foreach ($map as $field => $index) {
                    $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                }

                if (empty($row['student_name']) || empty($row['student_id_number'])) {
                    continue;
                }

                $grade_id = self::get_id_by_name($wpdb->prefix . 'olama_grades', 'grade_name', $row['grade_name'] ?? '');
                $section_id = self::get_id_by_name($wpdb->prefix . 'olama_sections', 'section_name', $row['section_name'] ?? '');

                if ($grade_id && $section_id) {
                    $student_data = array(
                        'student_name' => $row['student_name'],
                        'student_id_number' => $row['student_id_number'],
                        'section_id' => $section_id,
                    );

                    // Add current academic year if possible
                    if (class_exists('Olama_School_Academic')) {
                        $active_year = Olama_School_Academic::get_active_year();
                        $student_data['academic_year_id'] = $active_year ? $active_year->id : 0;
                    }

                    $student_id = Olama_School_Student::add_student($student_data);
                    if ($student_id) {
                        $imported_count++;
                    }
                }
            }
            fclose($handle);

            set_transient('olama_import_message', sprintf(__('%d students imported successfully.', 'olama-school'), $imported_count), 30);
        }

        wp_redirect(admin_url('admin.php?page=olama-school-users&import=success'));
        exit;
    }

    /**
     * Import curriculum from CSV
     */
    public static function import_curriculum_csv($semester_id, $grade_id, $subject_id)
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_import_curriculum')) {
            set_transient('olama_import_error', __('Security check failed.', 'olama-school'), 30);
            wp_redirect(admin_url('admin.php?page=olama-school-curriculum'));
            exit;
        }

        // Check permissions
        if (!current_user_can('olama_manage_curriculum')) {
            set_transient('olama_import_error', __('You do not have permission to import curriculum data.', 'olama-school'), 30);
            wp_redirect(admin_url('admin.php?page=olama-school-curriculum'));
            exit;
        }

        if (empty($_FILES['olama_import_file']['tmp_name'])) {
            set_transient('olama_import_error', __('Please upload a valid CSV file.', 'olama-school'), 30);
            wp_redirect(admin_url('admin.php?page=olama-school-curriculum'));
            exit;
        }

        $semester_id = intval($semester_id);
        $grade_id = intval($grade_id);
        $subject_id = intval($subject_id);

        if (!$semester_id || !$grade_id || !$subject_id) {
            set_transient('olama_import_error', __('Invalid parameters for import.', 'olama-school'), 30);
            wp_redirect(admin_url('admin.php?page=olama-school-curriculum'));
            exit;
        }

        $file = $_FILES['olama_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle !== false) {
            // Skip BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                wp_die(__('Invalid CSV header.', 'olama-school'));
            }

            // Map headers (case-insensitive and flexible)
            $map = array();
            $fields_map = array(
                'unit_number' => array('unit #', 'unit number', 'ط±ظ‚ظ… ط§ظ„ظˆط­ط¯ط©'),
                'unit_name' => array('unit name', 'ط§ط³ظ… ط§ظ„ظˆط­ط¯ط©'),
                'objectives' => array('objectives', 'learning objectives', 'ط§ظ„ط£ظ‡ط¯ط§ظپ'),
                'lesson_number' => array('lesson #', 'lesson number', 'ط±ظ‚ظ… ط§ظ„ط¯ط±ط³'),
                'lesson_title' => array('lesson title', 'lesson name', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط¯ط±ط³', 'lesson tit'),
                'video_url' => array('video url', 'link', 'ط±ط§ط¨ط· ط§ظ„ظپظٹط¯ظٹظˆ')
            );

            foreach ($headers as $index => $header) {
                $header_clean = strtolower(trim($header));
                foreach ($fields_map as $field_key => $variations) {
                    if (in_array($header_clean, $variations)) {
                        $map[$field_key] = $index;
                        break;
                    }
                }
            }

            // Validate mandatory columns
            if (!isset($map['unit_number']) || !isset($map['unit_name'])) {
                set_transient('olama_import_error', __('Required columns (Unit #, Unit Name) are missing or misnamed in the CSV.', 'olama-school'), 30);
                wp_redirect(admin_url('admin.php?page=olama-school-curriculum&semester_id=' . $semester_id . '&grade_id=' . $grade_id . '&subject_id=' . $subject_id));
                exit;
            }

            $units_count = 0;
            $lessons_count = 0;
            $current_unit_id = 0;
            $last_unit_number = '';

            while (($data = fgetcsv($handle)) !== false) {
                // Initialize row with default values to prevent undefined key warnings
                $row = array(
                    'unit_number' => '',
                    'unit_name' => '',
                    'objectives' => '',
                    'lesson_number' => '',
                    'lesson_title' => '',
                    'video_url' => ''
                );

                foreach ($map as $field => $index) {
                    $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                }

                if (empty($row['unit_number'])) {
                    continue;
                }

                // If unit number changed, create/get new unit
                if ($row['unit_number'] !== $last_unit_number) {
                    $unit_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d AND grade_id = %d AND subject_id = %d AND unit_number = %s",
                        $semester_id,
                        $grade_id,
                        $subject_id,
                        $row['unit_number']
                    ));

                    if ($unit_id) {
                        $wpdb->update($wpdb->prefix . 'olama_curriculum_units', array(
                            'unit_name' => $row['unit_name'],
                            'objectives' => $row['objectives']
                        ), array('id' => $unit_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'olama_curriculum_units', array(
                            'semester_id' => $semester_id,
                            'grade_id' => $grade_id,
                            'subject_id' => $subject_id,
                            'unit_number' => $row['unit_number'],
                            'unit_name' => $row['unit_name'],
                            'objectives' => $row['objectives']
                        ));
                        $unit_id = $wpdb->insert_id;
                        $units_count++;
                    }
                    $current_unit_id = $unit_id;
                    $last_unit_number = $row['unit_number'];
                }

                // Handle lesson
                if (!empty($row['lesson_number'])) {
                    $lesson_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}olama_curriculum_lessons WHERE unit_id = %d AND lesson_number = %s",
                        $current_unit_id,
                        $row['lesson_number']
                    ));

                    if ($lesson_id) {
                        $wpdb->update($wpdb->prefix . 'olama_curriculum_lessons', array(
                            'lesson_title' => $row['lesson_title'],
                            'video_url' => $row['video_url']
                        ), array('id' => $lesson_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'olama_curriculum_lessons', array(
                            'unit_id' => $current_unit_id,
                            'lesson_number' => $row['lesson_number'],
                            'lesson_title' => $row['lesson_title'],
                            'video_url' => $row['video_url']
                        ));
                        $lessons_count++;
                    }
                }
            }
            fclose($handle);

            // Log activity
            if (class_exists('Olama_School_Logger')) {
                Olama_School_Logger::log('curriculum_import', sprintf('Curriculum import completed: %d units and %d lessons processed.', $units_count, $lessons_count));
            }

            set_transient('olama_import_message', sprintf(__('%d units and %d lessons processed successfully.', 'olama-school'), $units_count, $lessons_count), 30);
        }

        wp_redirect(admin_url('admin.php?page=olama-school-curriculum&semester_id=' . $semester_id . '&grade_id=' . $grade_id . '&subject_id=' . $subject_id . '&import=success'));
        exit;
    }

    /**
     * Helper to find ID by name in a table
     */
    private static function get_id_by_name($table, $column, $name)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE $column = %s", $name));
    }

    /**
     * Import subjects from CSV
     */
    public static function import_subjects_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_import_subjects')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!current_user_can('olama_manage_academic_structure')) {
            wp_die(__('You do not have permission to import subjects.', 'olama-school'));
        }

        if (empty($_FILES['olama_import_file']['tmp_name'])) {
            wp_die(__('Please upload a valid CSV file.', 'olama-school'));
        }

        $file = $_FILES['olama_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle !== false) {
            // Skip BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                wp_die(__('Invalid CSV header.', 'olama-school'));
            }

            // Map headers
            $map = array();
            $fields = array(
                'Subject Name' => 'subject_name',
                'Subject Code' => 'subject_code',
                'Grade Name' => 'grade_name',
                'Color Code' => 'color_code'
            );

            foreach ($headers as $index => $header) {
                $header = trim($header);
                if (isset($fields[$header])) {
                    $map[$fields[$header]] = $index;
                }
            }

            $imported_count = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $row = array();
                foreach ($map as $field => $index) {
                    $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                }

                if (empty($row['subject_name']) || empty($row['grade_name'])) {
                    continue;
                }

                $grade_id = self::get_id_by_name($wpdb->prefix . 'olama_grades', 'grade_name', $row['grade_name']);

                if (!$grade_id) {
                    // Automatically create missing grade
                    $grade_level = $row['grade_name'];
                    // Try to extract numerical level
                    if (preg_match('/(\d+)/', $row['grade_name'], $matches)) {
                        $grade_level = $matches[1];
                    }

                    $wpdb->insert($wpdb->prefix . 'olama_grades', array(
                        'grade_name' => $row['grade_name'],
                        'grade_level' => $grade_level,
                        'periods_count' => 8
                    ));
                    $grade_id = $wpdb->insert_id;
                }

                if ($grade_id) {
                    // Check if subject already exists for this grade
                    $subject_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}olama_subjects WHERE subject_name = %s AND grade_id = %d",
                        $row['subject_name'],
                        $grade_id
                    ));

                    $subject_data = array(
                        'subject_name' => $row['subject_name'],
                        'subject_code' => $row['subject_code'],
                        'grade_id' => $grade_id,
                        'color_code' => !empty($row['color_code']) ? $row['color_code'] : '#3498db',
                    );

                    if ($subject_id) {
                        $wpdb->update($wpdb->prefix . 'olama_subjects', $subject_data, array('id' => $subject_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'olama_subjects', $subject_data);
                    }
                    $imported_count++;
                }
            }
            fclose($handle);

            // Log activity
            if (class_exists('Olama_School_Logger')) {
                Olama_School_Logger::log('subjects_import', sprintf('CSV import completed: %d subjects processed.', $imported_count));
            }

            set_transient('olama_import_message', sprintf(__('%d subjects processed successfully.', 'olama-school'), $imported_count), 30);
        }

        wp_redirect(admin_url('admin.php?page=olama-school-academic&tab=subjects&import=success'));
        exit;
    }

    /**
     * Import grades and sections from CSV
     */
    public static function import_grades_sections_csv()
    {
        global $wpdb;

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olama_import_grades')) {
            wp_die(__('Security check failed.', 'olama-school'));
        }

        // Check permissions
        if (!current_user_can('olama_manage_academic_structure')) {
            wp_die(__('You do not have permission to import grade data.', 'olama-school'));
        }

        if (empty($_FILES['olama_import_file']['tmp_name'])) {
            wp_die(__('Please upload a valid CSV file.', 'olama-school'));
        }

        $file = $_FILES['olama_import_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle !== false) {
            // Skip BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);
            if (!$headers) {
                wp_die(__('Invalid CSV header.', 'olama-school'));
            }

            // Map headers
            $map = array();
            $fields = array(
                'Grade Name' => 'grade_name',
                'Grade Level' => 'grade_level',
                'Periods/Day' => 'periods_count',
                'Section Name' => 'section_name',
                'Room Number' => 'room_number'
            );

            foreach ($headers as $index => $header) {
                $header = trim($header);
                if (isset($fields[$header])) {
                    $map[$fields[$header]] = $index;
                }
            }

            $imported_grades = 0;
            $imported_sections = 0;
            $grade_cache = array();

            while (($data = fgetcsv($handle)) !== false) {
                $row = array();
                foreach ($map as $field => $index) {
                    $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                }

                if (empty($row['grade_name'])) {
                    continue;
                }

                // Handle Grade
                if (!isset($grade_cache[$row['grade_name']])) {
                    $grade_id = self::get_id_by_name($wpdb->prefix . 'olama_grades', 'grade_name', $row['grade_name']);

                    $grade_data = array(
                        'grade_name' => $row['grade_name'],
                        'grade_level' => !empty($row['grade_level']) ? $row['grade_level'] : $row['grade_name'],
                        'periods_count' => !empty($row['periods_count']) ? intval($row['periods_count']) : 8,
                    );

                    if ($grade_id) {
                        $wpdb->update($wpdb->prefix . 'olama_grades', $grade_data, array('id' => $grade_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'olama_grades', $grade_data);
                        $grade_id = $wpdb->insert_id;
                        $imported_grades++;
                    }
                    $grade_cache[$row['grade_name']] = $grade_id;
                }

                $grade_id = $grade_cache[$row['grade_name']];

                // Handle Section
                if (!empty($row['section_name'])) {
                    $section_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}olama_sections WHERE section_name = %s AND grade_id = %d",
                        $row['section_name'],
                        $grade_id
                    ));

                    $section_data = array(
                        'grade_id' => $grade_id,
                        'section_name' => $row['section_name'],
                        'room_number' => $row['room_number'] ?? '',
                    );

                    if ($section_id) {
                        $wpdb->update($wpdb->prefix . 'olama_sections', $section_data, array('id' => $section_id));
                    } else {
                        $wpdb->insert($wpdb->prefix . 'olama_sections', $section_data);
                        $imported_sections++;
                    }
                }
            }
            fclose($handle);

            // Log activity
            if (class_exists('Olama_School_Logger')) {
                Olama_School_Logger::log('grades_import', sprintf('CSV import completed: %d grades and %d sections processed.', $imported_grades, $imported_sections));
            }

            set_transient('olama_import_message', sprintf(__('%d grades and %d sections processed successfully.', 'olama-school'), $imported_grades, $imported_sections), 30);
        }

        wp_redirect(admin_url('admin.php?page=olama-school-academic&tab=grades&import=success'));
        exit;
    }

    /**
     * Import curriculum from Excel/CSV file for multiple subjects
     * Handles CSV-only format where each row can specify subject via sheet name matching
     */
    public static function import_bulk_curriculum($semester_id, $grade_id, $file_data)
    {
        global $wpdb;

        $semester_id = intval($semester_id);
        $grade_id = intval($grade_id);

        if (!$semester_id || !$grade_id) {
            return array(
                'success' => false,
                'message' => __('Invalid semester or grade parameters.', 'olama-school')
            );
        }

        $results = array();
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));

        if ($file_extension === 'csv') {
            $results = self::process_csv_bulk($semester_id, $grade_id, $file_data['tmp_name']);
        } elseif (in_array($file_extension, array('xlsx', 'xls'))) {
            // Process Excel file using PHPSpreadsheet
            $results = self::process_excel_bulk($semester_id, $grade_id, $file_data['tmp_name']);
        } else {
            return array(
                'success' => false,
                'message' => __('Unsupported file format. Please upload Excel (.xlsx, .xls) or CSV (.csv) file.', 'olama-school')
            );
        }

        // Log activity
        if (class_exists('Olama_School_Logger')) {
            $total_units = array_sum(array_column($results, 'units_count'));
            $total_lessons = array_sum(array_column($results, 'lessons_count'));
            Olama_School_Logger::log('bulk_curriculum_import', sprintf(
                'Bulk curriculum import completed: %d subjects, %d units, %d lessons processed.',
                count($results),
                $total_units,
                $total_lessons
            ));
        }

        return array(
            'success' => true,
            'results' => $results
        );
    }

    /**
     * Process CSV file with curriculum data for multiple subjects
     * CSV should have all subjects' data in one file
     */
    private static function process_csv_bulk($semester_id, $grade_id, $file_path)
    {
        global $wpdb;

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return array();
        }

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return array();
        }

        // Map headers
        $map = array();
        $fields_map = array(
            'subject_name' => array('subject', 'subject name', 'ط§ظ„ظ…ط§ط¯ط©', 'ط§ط³ظ… ط§ظ„ظ…ط§ط¯ط©', 'ط§ط³ظ… ظ…ط§ط¯ظ‡', 'subject_name'),
            'unit_number' => array('unit #', 'unit number', 'ط±ظ‚ظ… ط§ظ„ظˆط­ط¯ط©', 'ظˆط­ط¯ط©', 'ط±ظ‚ظ… ظˆط­ط¯ظ‡', 'unit', 'unit_no', 'unit_number'),
            'unit_name' => array('unit name', 'ط§ط³ظ… ط§ظ„ظˆط­ط¯ط©', 'ط§ط³ظ… ظˆط­ط¯ظ‡', 'unit_name'),
            'objectives' => array('objectives', 'learning objectives', 'ط§ظ„ط£ظ‡ط¯ط§ظپ', 'ط§ظ‡ط¯ط§ظپ', 'objective'),
            'lesson_number' => array('lesson #', 'lesson number', 'ط±ظ‚ظ… ط§ظ„ط¯ط±ط³', 'ط¯ط±ط³', 'ط±ظ‚ظ… ط¯ط±ط³', 'lesson', 'lesson_no', 'lesson_number'),
            'lesson_title' => array('lesson title', 'lesson name', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط¯ط±ط³', 'ط¹ظ†ظˆط§ظ† ط¯ط±ط³', 'lesson_title'),
            'video_url' => array('video url', 'link', 'ط±ط§ط¨ط· ط§ظ„ظپظٹط¯ظٹظˆ', 'ظپظٹط¯ظٹظˆ', 'video', 'url'),
            'periods' => array('number of periods', 'periods', 'ط¹ط¯ط¯ ط§ظ„ط­طµطµ', 'ط­طµطµ', 'ط¹ط¯ط¯ ط­طµطµ', 'period_count')
        );

        foreach ($headers as $index => $header) {
            // Even more aggressive normalization: remove non-alphanumeric/non-Arabic except #
            $header_clean = strtolower(trim($header));
            $header_clean = preg_replace('/[^\p{L}\p{N}#\s]/u', '', $header_clean); // Keep letters, numbers, #, and spaces
            $header_clean = preg_replace('/\s+/', ' ', $header_clean);

            foreach ($fields_map as $field_key => $variations) {
                if (in_array($header_clean, $variations)) {
                    $map[$field_key] = $index;
                    break;
                }
            }
        }

        // Log detected columns for debugging
        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('bulk_import_debug', 'Detected CSV columns: ' . json_encode($map));
        }

        // Validate mandatory columns - only unit_number is required
        if (!isset($map['unit_number'])) {
            fclose($handle);
            return array(
                array(
                    'subject_name' => 'Unknown',
                    'units_count' => 0,
                    'lessons_count' => 0,
                    'errors' => array(__('Required column (Unit #) is missing.', 'olama-school'))
                )
            );
        }

        // Group data by subject
        $subjects_data = array();
        $current_subject = null;

        while (($data = fgetcsv($handle)) !== false) {
            $row = array(
                'subject_name' => '',
                'unit_number' => '',
                'unit_name' => '',
                'objectives' => '',
                'lesson_number' => '',
                'lesson_title' => '',
                'video_url' => '',
                'periods' => '1'
            );

            foreach ($map as $field => $index) {
                $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
            }

            if (empty($row['unit_number'])) {
                continue;
            }

            // Determine subject - if subject column exists, use it; otherwise group all as single subject
            if (isset($map['subject_name']) && !empty($row['subject_name'])) {
                $current_subject = $row['subject_name'];
            } elseif ($current_subject === null) {
                $current_subject = 'Default Subject';
            }

            if (!isset($subjects_data[$current_subject])) {
                $subjects_data[$current_subject] = array();
            }

            $subjects_data[$current_subject][] = $row;
        }

        fclose($handle);

        // Process each subject's data
        $results = array();
        foreach ($subjects_data as $subject_name => $rows) {
            $result = self::import_subject_curriculum($semester_id, $grade_id, $subject_name, $rows);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Import curriculum data for a single subject
     */
    private static function import_subject_curriculum($semester_id, $grade_id, $subject_name, $rows)
    {
        global $wpdb;

        $errors = array();
        $units_count = 0;
        $lessons_count = 0;

        // Get or create subject
        $subject_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}olama_subjects WHERE subject_name = %s AND grade_id = %d",
            $subject_name,
            $grade_id
        ));

        if (!$subject_id) {
            // Create new subject
            $wpdb->insert($wpdb->prefix . 'olama_subjects', array(
                'subject_name' => $subject_name,
                'subject_code' => mb_substr($subject_name, 0, 10, 'UTF-8'),
                'grade_id' => $grade_id,
                'color_code' => '#3b82f6'
            ));
            $subject_id = $wpdb->insert_id;

            if (!$subject_id) {
                return array(
                    'subject_name' => $subject_name,
                    'units_count' => 0,
                    'lessons_count' => 0,
                    'errors' => array(__('Failed to create subject.', 'olama-school'))
                );
            }
        }

        if (class_exists('Olama_School_Logger')) {
            Olama_School_Logger::log('bulk_import_debug', sprintf('Processing subject: %s (ID: %d), Rows: %d', $subject_name, $subject_id, count($rows)));
        }

        // Process rows
        $current_unit_id = 0;
        $last_unit_number = '';

        foreach ($rows as $row) {
            // Handle unit
            if ($row['unit_number'] !== $last_unit_number) {
                // Use unit_number as unit_name if unit_name is empty
                $unit_name_value = !empty($row['unit_name']) ? $row['unit_name'] : $row['unit_number'];

                $unit_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}olama_curriculum_units WHERE semester_id = %d AND grade_id = %d AND subject_id = %d AND unit_number = %s",
                    $semester_id,
                    $grade_id,
                    $subject_id,
                    $row['unit_number']
                ));

                if ($unit_id) {
                    $wpdb->update($wpdb->prefix . 'olama_curriculum_units', array(
                        'unit_name' => $unit_name_value,
                        'objectives' => $row['objectives']
                    ), array('id' => $unit_id));
                } else {
                    $wpdb->insert($wpdb->prefix . 'olama_curriculum_units', array(
                        'semester_id' => $semester_id,
                        'grade_id' => $grade_id,
                        'subject_id' => $subject_id,
                        'unit_number' => $row['unit_number'],
                        'unit_name' => $unit_name_value,
                        'objectives' => $row['objectives']
                    ));
                    $unit_id = $wpdb->insert_id;
                }
                $units_count++; // Increment for both new and updated units

                $current_unit_id = $unit_id;
                $last_unit_number = $row['unit_number'];
            }

            // Handle lesson
            if (!empty($row['lesson_number']) && $current_unit_id) {
                $lesson_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}olama_curriculum_lessons WHERE unit_id = %d AND lesson_number = %s",
                    $current_unit_id,
                    $row['lesson_number']
                ));

                if ($lesson_id) {
                    $wpdb->update($wpdb->prefix . 'olama_curriculum_lessons', array(
                        'lesson_title' => $row['lesson_title'],
                        'video_url' => $row['video_url'],
                        'periods' => intval($row['periods'])
                    ), array('id' => $lesson_id));
                } else {
                    $wpdb->insert($wpdb->prefix . 'olama_curriculum_lessons', array(
                        'unit_id' => $current_unit_id,
                        'lesson_number' => $row['lesson_number'],
                        'lesson_title' => $row['lesson_title'],
                        'video_url' => $row['video_url'],
                        'periods' => intval($row['periods'])
                    ));
                }
                $lessons_count++; // Increment for both new and updated lessons
            }
        }

        return array(
            'subject_name' => $subject_name,
            'units_count' => $units_count,
            'lessons_count' => $lessons_count,
            'errors' => $errors
        );
    }

    /**
     * Process Excel file with curriculum data for multiple subjects
     * Each sheet represents one subject
     */
    private static function process_excel_bulk($semester_id, $grade_id, $file_path)
    {
        global $wpdb;

        try {
            // Check if PHPSpreadsheet is available
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                return array(
                    array(
                        'subject_name' => 'Error',
                        'units_count' => 0,
                        'lessons_count' => 0,
                        'errors' => array(__('PHPSpreadsheet library not found. Please install via Composer.', 'olama-school'))
                    )
                );
            }

            // Load the Excel file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $results = array();

            // Process each sheet as a subject
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $subject_name = $sheet->getTitle();
                $rows = array();

                // Get all rows from the sheet
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Read header row
                $headers = array();
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $headers[$col] = trim($sheet->getCell($col . '1')->getValue());
                }

                // Map headers to field names
                $map = array();
                $fields_map = array(
                    'subject_name' => array('subject', 'subject name', 'ط§ظ„ظ…ط§ط¯ط©', 'ط§ط³ظ… ط§ظ„ظ…ط§ط¯ط©', 'ط§ط³ظ… ظ…ط§ط¯ظ‡'),
                    'unit_number' => array('unit #', 'unit number', 'ط±ظ‚ظ… ط§ظ„ظˆط­ط¯ط©', 'ظˆط­ط¯ط©', 'ط±ظ‚ظ… ظˆط­ط¯ظ‡'),
                    'unit_name' => array('unit name', 'ط§ط³ظ… ط§ظ„ظˆط­ط¯ط©', 'ط§ط³ظ… ظˆط­ط¯ظ‡'),
                    'objectives' => array('objectives', 'learning objectives', 'ط§ظ„ط£ظ‡ط¯ط§ظپ', 'ط§ظ‡ط¯ط§ظپ'),
                    'lesson_number' => array('lesson #', 'lesson number', 'ط±ظ‚ظ… ط§ظ„ط¯ط±ط³', 'ط¯ط±ط³', 'ط±ظ‚ظ… ط¯ط±ط³'),
                    'lesson_title' => array('lesson title', 'lesson name', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط¯ط±ط³', 'ط¹ظ†ظˆط§ظ† ط¯ط±ط³'),
                    'video_url' => array('video url', 'link', 'ط±ط§ط¨ط· ط§ظ„ظپظٹط¯ظٹظˆ', 'ظپظٹط¯ظٹظˆ'),
                    'periods' => array('number of periods', 'periods', 'ط¹ط¯ط¯ ط§ظ„ط­طµطµ', 'ط­طµطµ', 'ط¹ط¯ط¯ ط­طµطµ')
                );

                foreach ($headers as $col => $header) {
                    // Even more aggressive normalization
                    $header_clean = strtolower(trim($header));
                    $header_clean = preg_replace('/[^\p{L}\p{N}#\s]/u', '', $header_clean);
                    $header_clean = preg_replace('/\s+/', ' ', $header_clean);

                    foreach ($fields_map as $field_key => $variations) {
                        if (in_array($header_clean, $variations)) {
                            $map[$field_key] = $col;
                            break;
                        }
                    }
                }

                if (class_exists('Olama_School_Logger')) {
                    Olama_School_Logger::log('bulk_import_debug', sprintf('Sheet: %s, Detected columns: %s', $subject_name, json_encode($map)));
                }

                // Validate required columns - only unit_number is required
                if (!isset($map['unit_number'])) {
                    $results[] = array(
                        'subject_name' => $subject_name,
                        'units_count' => 0,
                        'lessons_count' => 0,
                        'errors' => array(__('Required column (Unit #) is missing in sheet: ' . $subject_name, 'olama-school'))
                    );
                    continue;
                }

                // Read data rows
                for ($row = 2; $row <= $highestRow; $row++) {
                    $rowData = array(
                        'subject_name' => '',
                        'unit_number' => '',
                        'unit_name' => '',
                        'objectives' => '',
                        'lesson_number' => '',
                        'lesson_title' => '',
                        'video_url' => '',
                        'periods' => '1'
                    );

                    foreach ($map as $field => $col) {
                        $cellValue = $sheet->getCell($col . $row)->getValue();
                        $rowData[$field] = trim((string) $cellValue);
                    }

                    if (!empty($rowData['unit_number'])) {
                        $rows[] = $rowData;
                    }
                }

                // Process this subject's data
                if (!empty($rows)) {
                    $result = self::import_subject_curriculum($semester_id, $grade_id, $subject_name, $rows);
                    $results[] = $result;
                }
            }

            return $results;

        } catch (\Exception $e) {
            return array(
                array(
                    'subject_name' => 'Error',
                    'units_count' => 0,
                    'lessons_count' => 0,
                    'errors' => array(__('Error reading Excel file: ', 'olama-school') . $e->getMessage())
                )
            );
        }
    }
}