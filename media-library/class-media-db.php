<?php
/**
 * Media Library Database Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Academy_Media_DB
{

    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'academy_media_uploads';
    }

    /**
     * Initialize DB tables
     */
    public function init()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lesson_id INT UNSIGNED NOT NULL,
            unit_id INT UNSIGNED NOT NULL,
            grade VARCHAR(50),
            subject VARCHAR(100),
            semester VARCHAR(50),
            academic_year VARCHAR(20),
            lesson_name VARCHAR(255),
            unit_name VARCHAR(255),
            drive_file_id VARCHAR(255) NULL,
            drive_file_url VARCHAR(500) NULL,
            drive_folder_id VARCHAR(255) NULL,
            upload_status VARCHAR(20) DEFAULT 'pending',
            uploaded_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY lesson_id (lesson_id),
            KEY unit_id (unit_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get curriculum with upload status
     */
    public function get_curriculum($academic_year_id, $semester_id, $grade_id, $subject_id)
    {
        global $wpdb;

        $units_table = $wpdb->prefix . 'olama_curriculum_units';
        $lessons_table = $wpdb->prefix . 'olama_curriculum_lessons';

        // Fetch units
        $units = $wpdb->get_results($wpdb->prepare(
            "SELECT id, unit_number, unit_name 
            FROM $units_table 
            WHERE grade_id = %d AND subject_id = %d AND semester_id = %d 
            ORDER BY CAST(unit_number AS UNSIGNED) ASC",
            $grade_id,
            $subject_id,
            $semester_id
        ));

        if (empty($units)) {
            return [];
        }

        foreach ($units as &$unit) {
            $unit->lessons = $wpdb->get_results($wpdb->prepare(
                "SELECT l.id, l.lesson_number, l.lesson_title, 
                        m.upload_status, m.drive_file_url, m.drive_file_id, m.id as media_record_id
                FROM $lessons_table l
                LEFT JOIN $this->table_name m ON l.id = m.lesson_id
                WHERE l.unit_id = %d
                ORDER BY CAST(l.lesson_number AS UNSIGNED) ASC",
                $unit->id
            ));
        }

        return $units;
    }

    /**
     * Upsert upload record
     */
    public function upsert_upload_record($data)
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE lesson_id = %d",
            $data['lesson_id']
        ));

        if ($existing) {
            $wpdb->update($this->table_name, $data, ['id' => $existing]);
            return $existing;
        } else {
            $wpdb->insert($this->table_name, $data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Update status
     */
    public function update_status($id, $status, $extras = [])
    {
        global $wpdb;
        $data = array_merge(['upload_status' => $status], $extras);
        return $wpdb->update($this->table_name, $data, ['id' => $id]);
    }

    /**
     * Get upload log
     */
    public function get_log($filters = [], $page = 1, $per_page = 20)
    {
        global $wpdb;

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['grade'])) {
            $where[] = "grade = %s";
            $params[] = $filters['grade'];
        }
        if (!empty($filters['subject'])) {
            $where[] = "subject = %s";
            $params[] = $filters['subject'];
        }
        if (!empty($filters['status'])) {
            $where[] = "upload_status = %s";
            $params[] = $filters['status'];
        }
        if (!empty($filters['academic_year'])) {
            $where[] = "academic_year = %s";
            $params[] = $filters['academic_year'];
        }

        $where_sql = implode(" AND ", $where);
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $this->table_name 
            WHERE $where_sql 
            ORDER BY created_at DESC 
            LIMIT %d, %d",
            array_merge($params, [$offset, $per_page])
        ));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $this->table_name WHERE $where_sql",
            $params
        ));

        return [
            'items' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Delete log entry
     */
    public function delete_log($id)
    {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $id]);
    }
}
