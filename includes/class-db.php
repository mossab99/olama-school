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
	 * Get all tables managed by this plugin
	 */
	public static function get_tables()
	{
		$tables = array(
			'olama_settings',
			'olama_academic_years',
			'olama_semesters',
			'olama_grades',
			'olama_sections',
			'olama_subjects',
			'olama_teachers',
			'olama_families',
			'olama_students',
			'olama_student_enrollment',
			'olama_plans',
			'olama_plan_questions',
			'olama_templates',
			'olama_schedule',
			'olama_curriculum_units',
			'olama_curriculum_lessons',
			'olama_curriculum_questions',
			'olama_logs',
			'olama_academic_events',
			'olama_teacher_assignments',
			'olama_teacher_office_hours',
			'olama_user_preferences',
			'olama_notifications',
			'olama_semester_exams',
			'olama_stationary',
			'olama_system_logs'
		);

		return $tables;
	}

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
				core_grade_id varchar(50) DEFAULT NULL,
				academic_source varchar(20) DEFAULT 'manual' NOT NULL,
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
				PRIMARY KEY  (id),
				UNIQUE KEY core_grade_id (core_grade_id)
			) $charset_collate;",

			'olama_sections' => "CREATE TABLE {$wpdb->prefix}olama_sections (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				core_grade_id varchar(50) DEFAULT NULL,
				core_section_id varchar(50) DEFAULT NULL,
				core_study_year varchar(20) DEFAULT NULL,
				academic_source varchar(20) DEFAULT 'manual' NOT NULL,
				section_name varchar(50) NOT NULL,
				room_number varchar(20) DEFAULT NULL,
				homeroom_teacher_id bigint(20) UNSIGNED DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  grade_id (grade_id),
				UNIQUE KEY core_grade_section (core_study_year, core_grade_id, core_section_id)
			) $charset_collate;",

			'olama_subjects' => "CREATE TABLE {$wpdb->prefix}olama_subjects (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				core_study_year varchar(20) DEFAULT NULL,
				core_grade_id varchar(50) DEFAULT NULL,
				core_subject_id varchar(50) DEFAULT NULL,
				academic_source varchar(20) DEFAULT 'manual' NOT NULL,
				subject_name varchar(100) NOT NULL,
				subject_code varchar(20) DEFAULT NULL,
				grade_id mediumint(9) NOT NULL,
				color_code varchar(7) DEFAULT NULL,
				max_weekly_plans tinyint(4) DEFAULT 0 NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY  grade_id (grade_id),
				UNIQUE KEY core_grade_subject (core_study_year, core_grade_id, core_subject_id)
			) $charset_collate;",

			'olama_teachers' => "CREATE TABLE {$wpdb->prefix}olama_teachers (
				id bigint(20) UNSIGNED NOT NULL,
				employee_id varchar(50) DEFAULT NULL,
				phone_number varchar(20) DEFAULT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_families' => "CREATE TABLE {$wpdb->prefix}olama_families (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				family_uid varchar(50) NOT NULL,
				family_name varchar(255) NOT NULL,
				father_first_name varchar(100) DEFAULT NULL,
				father_second_name varchar(100) DEFAULT NULL,
				father_third_name varchar(100) DEFAULT NULL,
				father_family_name varchar(100) DEFAULT NULL,
				father_nationality varchar(50) DEFAULT NULL,
				father_job varchar(100) DEFAULT NULL,
				father_workplace varchar(100) DEFAULT NULL,
				father_mobile varchar(20) DEFAULT NULL,
				father_email varchar(100) DEFAULT NULL,
				mother_full_name varchar(255) DEFAULT NULL,
				mother_nationality varchar(50) DEFAULT NULL,
				mother_mobile varchar(20) DEFAULT NULL,
				mother_email varchar(100) DEFAULT NULL,
				residential_area varchar(100) DEFAULT NULL,
				home_address text DEFAULT NULL,
				building_number varchar(50) DEFAULT NULL,
				apartment_number varchar(50) DEFAULT NULL,
				home_phone varchar(50) DEFAULT NULL,
				address text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY family_uid (family_uid)
			) $charset_collate;",

			'olama_students' => "CREATE TABLE {$wpdb->prefix}olama_students (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_name varchar(100) NOT NULL,
				student_uid varchar(50) NOT NULL,
				family_id varchar(50) DEFAULT NULL,
				dob date DEFAULT NULL,
				national_id varchar(50) DEFAULT NULL,
				gender varchar(20) DEFAULT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY student_uid (student_uid),
				KEY family_id (family_id)
			) $charset_collate;",

			'olama_student_enrollment' => "CREATE TABLE {$wpdb->prefix}olama_student_enrollment (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				academic_year_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				enrollment_date date DEFAULT NULL,
				status varchar(20) DEFAULT 'active' NOT NULL,
				PRIMARY KEY  (id),
				KEY student_id (student_id),
				KEY student_uid (student_uid),
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
				supervisor_feedback text DEFAULT NULL,
				teacher_response text DEFAULT NULL,
				rating tinyint(4) DEFAULT 0 NOT NULL,
				plan_type varchar(20) DEFAULT 'homework' NOT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  section_date (section_id,plan_date),
				KEY  plan_lookup (academic_year_id,section_id,plan_date),
				KEY  subject_id (subject_id),
				KEY  teacher_id (teacher_id),
				KEY  section_subject_date (section_id,subject_id,plan_date)
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
				PRIMARY KEY  (id),
				KEY teacher_subject (teacher_id, subject_id)
			) $charset_collate;",

			'olama_schedule' => "CREATE TABLE {$wpdb->prefix}olama_schedule (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				day_name varchar(20) NOT NULL,
				period_number tinyint(4) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				schedule_type varchar(20) DEFAULT 'normal' NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  schedule_slot (semester_id,section_id,day_name,period_number,schedule_type)
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
				KEY  unit_id (unit_id),
				KEY  lesson_dates (start_date, end_date)
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

			'olama_system_logs' => "CREATE TABLE {$wpdb->prefix}olama_system_logs (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				level varchar(10) NOT NULL DEFAULT 'info',
				source varchar(50) NOT NULL DEFAULT 'school',
				message text NOT NULL,
				context longtext DEFAULT NULL,
				user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  source (source),
				KEY  level (level),
				KEY  created_at (created_at),
				KEY  source_level (source, level)
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
				teacher_employee_id varchar(50) DEFAULT NULL,
				grade_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  teacher_id (teacher_id),
				KEY  teacher_employee_id (teacher_employee_id),
				KEY  section_id (section_id),
				KEY  assignment (teacher_id, section_id, subject_id),
				KEY  assignment_full (academic_year_id, grade_id, section_id)
			) $charset_collate;",

			'olama_teacher_office_hours' => "CREATE TABLE {$wpdb->prefix}olama_teacher_office_hours (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				day_name varchar(20) NOT NULL,
				available_time text NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  semester_id (semester_id),
				KEY  teacher_id (teacher_id)
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







			'olama_semester_exams' => "CREATE TABLE {$wpdb->prefix}olama_semester_exams (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				semester_id mediumint(9) NOT NULL,
				grade_id mediumint(9) DEFAULT NULL,
				exam_name varchar(100) NOT NULL,
				room_number varchar(50) DEFAULT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY  semester_id (semester_id),
				KEY  grade_id (grade_id)
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
			) $charset_collate;",

























		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($tables as $table_sql) {
			dbDelta($table_sql);
		}

		// Rename existing semesters for better naming convention
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'First Semester' WHERE semester_name = '1st Semester'");
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'Second Semester' WHERE semester_name = '2nd Semester'");
	}

	public function drop_tables()
	{
		global $wpdb;

		$tables = array(
			'olama_stationary',
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
			'olama_notifications',
		);

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
		}

		delete_option('olama_school_version');
	}
}
