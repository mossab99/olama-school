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
				UNIQUE KEY setting_name (setting_name)
			) $charset_collate;",

			'olama_academic_years' => "CREATE TABLE {$wpdb->prefix}olama_academic_years (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				year_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_semesters' => "CREATE TABLE {$wpdb->prefix}olama_semesters (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				PRIMARY KEY  (id),
				KEY academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_grades' => "CREATE TABLE {$wpdb->prefix}olama_grades (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_name varchar(50) NOT NULL,
				grade_level varchar(20) NOT NULL,
				periods_count tinyint(4) DEFAULT 8,
				max_weekly_plans tinyint(4) DEFAULT 0,
				is_active tinyint(1) DEFAULT 1,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_sections' => "CREATE TABLE {$wpdb->prefix}olama_sections (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_id mediumint(9) NOT NULL,
				section_name varchar(50) NOT NULL,
				room_number varchar(20),
				homeroom_teacher_id bigint(20) UNSIGNED,
				PRIMARY KEY  (id),
				KEY grade_id (grade_id)
			) $charset_collate;",

			'olama_subjects' => "CREATE TABLE {$wpdb->prefix}olama_subjects (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				subject_name varchar(100) NOT NULL,
				subject_code varchar(20),
				grade_id mediumint(9) NOT NULL,
				color_code varchar(7),
				max_weekly_plans tinyint(4) DEFAULT 0,
				PRIMARY KEY  (id),
				KEY grade_id (grade_id)
			) $charset_collate;",

			'olama_teachers' => "CREATE TABLE {$wpdb->prefix}olama_teachers (
				id bigint(20) UNSIGNED NOT NULL,
				employee_id varchar(50),
				phone_number varchar(20),
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_students' => "CREATE TABLE {$wpdb->prefix}olama_students (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_name varchar(100) NOT NULL,
				student_uid varchar(50) NOT NULL,
				section_id mediumint(9) NOT NULL,
				parent_contact varchar(100),
				is_active tinyint(1) DEFAULT 1,
				PRIMARY KEY  (id),
				KEY section_id (section_id)
			) $charset_collate;",

			'olama_curriculum' => "CREATE TABLE {$wpdb->prefix}olama_curriculum (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				unit_number varchar(10) NOT NULL,
				unit_name varchar(255) NOT NULL,
				lesson_number varchar(10),
				lesson_title text NOT NULL,
				objectives text,
				pages varchar(50),
				duration tinyint(4) DEFAULT 1,
				resources text,
				PRIMARY KEY  (id),
				KEY curriculum_lookup (grade_id, subject_id, semester_id)
			) $charset_collate;",

			'olama_plans' => "CREATE TABLE {$wpdb->prefix}olama_plans (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				plan_date date NOT NULL,
				period_number tinyint(4) NOT NULL,
				unit_id mediumint(9),
				lesson_id mediumint(9),
				curriculum_id mediumint(9),
				custom_topic text,
				homework_sb varchar(255),
				homework_eb varchar(255),
				homework_nb text,
				homework_ws text,
				teacher_notes text,
				rating tinyint(4) DEFAULT 0,
				status varchar(20) DEFAULT 'draft',
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY section_date (section_id, plan_date),
				KEY subject_id (subject_id),
				KEY teacher_id (teacher_id)
			) $charset_collate;",

			'olama_plan_questions' => "CREATE TABLE {$wpdb->prefix}olama_plan_questions (
				plan_id mediumint(9) NOT NULL,
				question_id mediumint(9) NOT NULL,
				PRIMARY KEY  (plan_id, question_id)
			) $charset_collate;",

			'olama_templates' => "CREATE TABLE {$wpdb->prefix}olama_templates (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_name varchar(100) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				template_data longtext NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
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
				UNIQUE KEY schedule_slot (semester_id, section_id, day_name, period_number)
			) $charset_collate;",

			'olama_curriculum_units' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_units (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				unit_number varchar(10) NOT NULL,
				unit_name varchar(255) NOT NULL,
				objectives text,
				start_date date,
				end_date date,
				PRIMARY KEY  (id),
				KEY unit_lookup (grade_id, subject_id, semester_id)
			) $charset_collate;",

			'olama_curriculum_lessons' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_lessons (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				unit_id mediumint(9) NOT NULL,
				lesson_number varchar(10),
				lesson_title text NOT NULL,
				video_url varchar(255),
				periods tinyint(4) DEFAULT 1,
				start_date date,
				end_date date,
				PRIMARY KEY  (id),
				KEY unit_id (unit_id)
			) $charset_collate;",

			'olama_curriculum_questions' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_questions (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				lesson_id mediumint(9) NOT NULL,
				question_number varchar(10),
				question text NOT NULL,
				answer text,
				PRIMARY KEY  (id),
				KEY lesson_id (lesson_id)
			) $charset_collate;",

			'olama_logs' => "CREATE TABLE {$wpdb->prefix}olama_logs (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				action varchar(255) NOT NULL,
				details text,
				ip_address varchar(45),
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY created_at (created_at)
			) $charset_collate;",
			'olama_academic_events' => "CREATE TABLE {$wpdb->prefix}olama_academic_events (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				event_description text NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				PRIMARY KEY  (id),
				KEY academic_year_id (academic_year_id)
			) $charset_collate;",
			'olama_teacher_assignments' => "CREATE TABLE {$wpdb->prefix}olama_teacher_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY assignment (teacher_id, section_id, subject_id),
				KEY teacher_id (teacher_id),
				KEY section_id (section_id)
			) $charset_collate;",
			'olama_teacher_office_hours' => "CREATE TABLE {$wpdb->prefix}olama_teacher_office_hours (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				day_name varchar(20) NOT NULL,
				available_time text NOT NULL,
				PRIMARY KEY  (id),
				KEY teacher_id (teacher_id)
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
				workbook_material text,
				exercise_book_material text,
				notebook_material text,
				teacher_notes text NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY year_semester (academic_year_id, semester_id),
				KEY grade_subject (grade_id, subject_id)
			) $charset_collate;"
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($tables as $table_sql) {
			dbDelta($table_sql);
		}
	}
}
