<?php
/**
 * Database Schema Class
 */

if (!defined('ABSPATH')) {
	exit;
}

class Olama_School_DB
{

	/**
	 * Create database tables
	 * 
	 */
	public function create_tables()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tables = array(
			'olama_settings' => "CREATE TABLE {$wpdb->prefix}olama_settings (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				setting_name varchar(100) NOT NULL,
				setting_value longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  setting_name (setting_name)
			) $charset_collate;",

			'olama_academic_years' => "CREATE TABLE {$wpdb->prefix}olama_academic_years (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				year_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_semesters' => "CREATE TABLE {$wpdb->prefix}olama_semesters (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_grades' => "CREATE TABLE {$wpdb->prefix}olama_grades (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_name varchar(50) NOT NULL,
				grade_level varchar(20) NOT NULL,
				periods_count tinyint(4) DEFAULT 8 NOT NULL,
				max_weekly_plans tinyint(4) DEFAULT 0 NOT NULL,
				max_sun tinyint(4) DEFAULT 0 NOT NULL,
				max_mon tinyint(4) DEFAULT 0 NOT NULL,
				max_tue tinyint(4) DEFAULT 0 NOT NULL,
				max_wed tinyint(4) DEFAULT 0 NOT NULL,
				max_thu tinyint(4) DEFAULT 0 NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_sections' => "CREATE TABLE {$wpdb->prefix}olama_sections (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_name varchar(50) NOT NULL,
				room_number varchar(20) DEFAULT NULL,
				homeroom_teacher_id bigint(20) UNSIGNED DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  grade_id (grade_id)
			) $charset_collate;",

			'olama_subjects' => "CREATE TABLE {$wpdb->prefix}olama_subjects (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				subject_name varchar(100) NOT NULL,
				subject_code varchar(20) DEFAULT NULL,
				grade_id mediumint(9) NOT NULL,
				color_code varchar(7) DEFAULT NULL,
				max_weekly_plans tinyint(4) DEFAULT 0 NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY  grade_id (grade_id)
			) $charset_collate;",

			'olama_teachers' => "CREATE TABLE {$wpdb->prefix}olama_teachers (
				id bigint(20) UNSIGNED NOT NULL,
				employee_id varchar(50) DEFAULT NULL,
				phone_number varchar(20) DEFAULT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_students' => "CREATE TABLE {$wpdb->prefix}olama_students (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_name varchar(100) NOT NULL,
				student_uid varchar(50) NOT NULL,
				family_id varchar(50) DEFAULT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_student_enrollment' => "CREATE TABLE {$wpdb->prefix}olama_student_enrollment (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_id mediumint(9) NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				enrollment_date date DEFAULT NULL,
				status varchar(20) DEFAULT 'active' NOT NULL,
				PRIMARY KEY  (id),
				KEY student_id (student_id),
				KEY academic_year_id (academic_year_id),
				KEY section_id (section_id)
			) $charset_collate;",

			'olama_plans' => "CREATE TABLE {$wpdb->prefix}olama_plans (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				plan_date date NOT NULL,
				period_number tinyint(4) NOT NULL,
				unit_id mediumint(9) DEFAULT NULL,
				lesson_id mediumint(9) DEFAULT NULL,
				curriculum_id mediumint(9) DEFAULT NULL,
				custom_topic text DEFAULT NULL,
				homework_sb varchar(255) DEFAULT NULL,
				homework_eb varchar(255) DEFAULT NULL,
				homework_nb text DEFAULT NULL,
				homework_ws text DEFAULT NULL,
				teacher_notes text DEFAULT NULL,
				rating tinyint(4) DEFAULT 0 NOT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  section_date (section_id,plan_date),
				KEY  plan_lookup (academic_year_id,section_id,plan_date),
				KEY  subject_id (subject_id),
				KEY  teacher_id (teacher_id)
			) $charset_collate;",

			'olama_plan_questions' => "CREATE TABLE {$wpdb->prefix}olama_plan_questions (
				plan_id mediumint(9) NOT NULL,
				question_id mediumint(9) NOT NULL,
				PRIMARY KEY  (plan_id,question_id)
			) $charset_collate;",

			'olama_templates' => "CREATE TABLE {$wpdb->prefix}olama_templates (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_name varchar(100) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				template_data longtext NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_schedule' => "CREATE TABLE {$wpdb->prefix}olama_schedule (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				day_name varchar(20) NOT NULL,
				period_number tinyint(4) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  schedule_slot (semester_id,section_id,day_name,period_number)
			) $charset_collate;",

			'olama_curriculum_units' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_units (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				unit_number varchar(10) NOT NULL,
				unit_name varchar(255) NOT NULL,
				objectives text DEFAULT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  unit_lookup (grade_id,subject_id,semester_id)
			) $charset_collate;",

			'olama_curriculum_lessons' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_lessons (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				unit_id mediumint(9) NOT NULL,
				lesson_number varchar(10) DEFAULT NULL,
				lesson_title text NOT NULL,
				video_url varchar(255) DEFAULT NULL,
				periods tinyint(4) DEFAULT 1 NOT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  unit_id (unit_id)
			) $charset_collate;",

			'olama_curriculum_questions' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_questions (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				lesson_id mediumint(9) NOT NULL,
				question_number varchar(10) DEFAULT NULL,
				question text NOT NULL,
				answer text DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  lesson_id (lesson_id)
			) $charset_collate;",

			'olama_logs' => "CREATE TABLE {$wpdb->prefix}olama_logs (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				action varchar(255) NOT NULL,
				details text DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  user_id (user_id),
				KEY  created_at (created_at)
			) $charset_collate;",

			'olama_academic_events' => "CREATE TABLE {$wpdb->prefix}olama_academic_events (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				event_description text NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_teacher_assignments' => "CREATE TABLE {$wpdb->prefix}olama_teacher_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  teacher_id (teacher_id),
				KEY  section_id (section_id),
				KEY  assignment (teacher_id, section_id, subject_id)
			) $charset_collate;",

			'olama_teacher_office_hours' => "CREATE TABLE {$wpdb->prefix}olama_teacher_office_hours (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				day_name varchar(20) NOT NULL,
				available_time text NOT NULL,
				PRIMARY KEY  (id),
				KEY  teacher_id (teacher_id)
			) $charset_collate;",

			'olama_exams' => "CREATE TABLE {$wpdb->prefix}olama_exams (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				evaluation_type varchar(50) NOT NULL,
				exam_date date NOT NULL,
				description text NOT NULL,
				student_book_material text NOT NULL,
				workbook_material text DEFAULT NULL,
				exercise_book_material text DEFAULT NULL,
				notebook_material text DEFAULT NULL,
				teacher_notes text NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  grade_subject (grade_id,subject_id)
			) $charset_collate;",

			'olama_user_preferences' => "CREATE TABLE {$wpdb->prefix}olama_user_preferences (
				user_id bigint(20) UNSIGNED NOT NULL,
				preference_key varchar(100) NOT NULL,
				preference_value longtext,
				PRIMARY KEY (user_id, preference_key)
			) $charset_collate;",

			'olama_notifications' => "CREATE TABLE {$wpdb->prefix}olama_notifications (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				notification_type varchar(50) NOT NULL,
				message text NOT NULL,
				is_read tinyint(1) DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY is_read (is_read)
			) $charset_collate;",

			'olama_ev_templates' => "CREATE TABLE {$wpdb->prefix}olama_ev_templates (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				semester_id mediumint(9) DEFAULT NULL,
				template_name varchar(255) NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY year_grade (academic_year_id, grade_id)
			) $charset_collate;",

			'olama_ev_domains' => "CREATE TABLE {$wpdb->prefix}olama_ev_domains (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_id mediumint(9) NOT NULL,
				grade_id mediumint(9) DEFAULT NULL,
				title_ar varchar(255) NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY template_id (template_id)
			) $charset_collate;",

			'olama_ev_categories' => "CREATE TABLE {$wpdb->prefix}olama_ev_categories (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				domain_id mediumint(9) NOT NULL,
				title_ar varchar(255) NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY domain_id (domain_id)
			) $charset_collate;",

			'olama_ev_indicators' => "CREATE TABLE {$wpdb->prefix}olama_ev_indicators (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				category_id mediumint(9) NOT NULL,
				indicator_text text NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY category_id (category_id)
			) $charset_collate;",

			'olama_ev_records' => "CREATE TABLE {$wpdb->prefix}olama_ev_records (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_id mediumint(9) NOT NULL,
				student_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY template_id (template_id),
				KEY student_id (student_id),
				KEY teacher_id (teacher_id),
				KEY year_semester (academic_year_id, semester_id)
			) $charset_collate;",

			'olama_ev_scores' => "CREATE TABLE {$wpdb->prefix}olama_ev_scores (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				evaluation_id mediumint(9) NOT NULL,
				indicator_id mediumint(9) NOT NULL,
				score tinyint(1) DEFAULT NULL,
				notes text DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY evaluation_id (evaluation_id),
				KEY indicator_id (indicator_id)
			) $charset_collate;",

			'olama_stationary' => "CREATE TABLE {$wpdb->prefix}olama_stationary (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				notebooks text DEFAULT NULL,
				stationary text DEFAULT NULL,
				teacher_notes text DEFAULT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY grade_year (academic_year_id, grade_id)
			) $charset_collate;"


		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($tables as $table_sql) {
			dbDelta($table_sql);
		}

		$this->ensure_schema_updates();

		// Rename existing semesters for better naming convention
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'First Semester' WHERE semester_name = '1st Semester'");
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'Second Semester' WHERE semester_name = '2nd Semester'");
	}

	/**
	 * Ensure critical schema updates that dbDelta might miss
	 */
	private function ensure_schema_updates()
	{
		global $wpdb;

		// Check if academic_year_id column exists in olama_sections
		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_sections LIKE 'academic_year_id'"
		);

		if (empty($column_exists)) {
			// Add the missing column
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_sections 
				ADD COLUMN academic_year_id mediumint(9) NOT NULL AFTER id"
			);

			// Add the index if it doesn't exist
			$index_exists = $wpdb->get_results(
				"SHOW INDEX FROM {$wpdb->prefix}olama_sections WHERE Key_name = 'academic_year_id'"
			);

			if (empty($index_exists)) {
				$wpdb->query(
					"ALTER TABLE {$wpdb->prefix}olama_sections 
					ADD KEY academic_year_id (academic_year_id)"
				);
			}
		}

		// Check if 'assignment' index exists in olama_teacher_assignments
		$assignment_index = $wpdb->get_results(
			"SHOW INDEX FROM {$wpdb->prefix}olama_teacher_assignments WHERE Key_name = 'assignment'"
		);

		if (empty($assignment_index)) {
			// Add the assignment index if it doesn't exist
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_teacher_assignments 
				ADD KEY assignment (teacher_id, section_id, subject_id)"
			);
		}

		// Check if is_active column exists in olama_subjects
		$subject_active_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_subjects LIKE 'is_active'"
		);

		if (empty($subject_active_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_subjects 
				ADD COLUMN is_active tinyint(1) DEFAULT 1 NOT NULL"
			);
		}

		// Ensure student schema updates
		$student_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_students");
		$col_names = wp_list_pluck($student_cols, 'Field');

		if (!in_array('family_id', $col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_students ADD COLUMN family_id varchar(50) DEFAULT NULL AFTER student_uid");
		}

		// EV Template Migration (one-time rename)
		$kg_ev_tables = array(
			'olama_kg_templates' => 'olama_ev_templates',
			'olama_kg_domains' => 'olama_ev_domains',
			'olama_kg_categories' => 'olama_ev_categories',
			'olama_kg_indicators' => 'olama_ev_indicators',
			'olama_kg_evaluations' => 'olama_ev_records',
			'olama_kg_evaluation_scores' => 'olama_ev_scores'
		);

		foreach ($kg_ev_tables as $old_name => $new_name) {
			$old_full = $wpdb->prefix . $old_name;
			$new_full = $wpdb->prefix . $new_name;
			if ($wpdb->get_var("SHOW TABLES LIKE '$old_full'") === $old_full) {
				if ($wpdb->get_var("SHOW TABLES LIKE '$new_full'") !== $new_full) {
					$wpdb->query("RENAME TABLE $old_full TO $new_full");
				} else {
					// Both exist, just drop the old one (or keep it, but rename is better)
					$wpdb->query("DROP TABLE $old_full");
				}
			}
		}
	}

	public function drop_tables()
	{
		global $wpdb;

		$tables = array(
			'olama_ev_scores',
			'olama_ev_records',
			'olama_ev_indicators',
			'olama_ev_categories',
			'olama_ev_domains',
			'olama_ev_templates',
			'olama_stationary',
			'olama_exams',
			'olama_teacher_office_hours',
			'olama_teacher_assignments',
			'olama_academic_events',
			'olama_logs',
			'olama_curriculum_questions',
			'olama_curriculum_lessons',
			'olama_curriculum_units',
			'olama_schedule',
			'olama_templates',
			'olama_plan_questions',
			'olama_plans',
			'olama_students',
			'olama_teachers',
			'olama_subjects',
			'olama_sections',
			'olama_grades',
			'olama_semesters',
			'olama_academic_years',
			'olama_settings',
			'olama_user_preferences',
			'olama_notifications'
		);

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
		}

		delete_option('olama_school_version');
	}
}